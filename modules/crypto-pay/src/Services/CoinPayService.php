<?php

namespace Modules\CryptoPay\Services;

use App\Support\SafeHttpClient;
use App\Models\User;
use CoinPayments\Api\InvoicesApi;
use CoinPayments\CoinPaymentsClient;
use CoinPayments\Runtime\WebhookVerifier;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Miran\Mksine\Models\Post;
use Modules\CryptoPay\Models\CryptoOrder;

/**
 * CoinPayments 加密支付服务：付费文章购买。
 *
 * 凭据一律存于 settings 表（后台录入），源码不落任何密钥。
 *
 * 安全约束：
 * - 服务端调用 CoinPayments API 前，经 SafeHttpClient 校验目标 URL
 *   （仅 https、拒绝内网/保留地址、域名白名单 a-api.coinpayments.net）；
 * - Webhook 一律先经 SDK WebhookVerifier HMAC 签名校验（含时间戳漂移校验）。
 */
final class CoinPayService
{
    private ?CoinPaymentsClient $client = null;

    public const BASE_URL = 'https://a-api.coinpayments.net';

    /** settings 键名（存配置项名称，非凭据本体；设置页表单也引用这些键）。 */
    public const S_CLIENT_ID = 'crypto_pay_client_id';

    public const S_API_PRIVATE = 'crypto_pay_api_private';

    public const S_HOOK_SIGNING = 'crypto_pay_hook_signing';

    public const S_MOCK = 'crypto_pay_mock_mode';

    public const S_CHANNEL = 'crypto_pay_channel';

    // ------------------------------------------------------------------
    // 配置
    // ------------------------------------------------------------------

    public static function setting(string $key): ?string
    {
        try {
            if (! DB::table('settings')->exists()) {
                return null;
            }
        } catch (\Throwable) {
            return null;
        }

        $value = \Miran\Mksine\Models\Setting::query()->where('key', $key)->value('value');

        return ($value !== null && $value !== '') ? (string) $value : null;
    }

    public static function mockMode(): bool
    {
        return self::setting(self::S_MOCK) === '1';
    }

    /** 支付通道：coinpayments（默认）/ xcash。Mock 模式优先。 */
    public static function channel(): string
    {
        return self::setting(self::S_CHANNEL) === 'xcash' ? 'xcash' : 'coinpayments';
    }

    public function configured(): bool
    {
        return self::setting(self::S_CLIENT_ID) !== null
            && self::setting(self::S_API_PRIVATE) !== null;
    }

    private function client(): CoinPaymentsClient
    {
        if ($this->client === null) {
            // SSRF 约束：发请求前校验目标（https + 官方域白名单 + 拒绝内网）
            SafeHttpClient::assertUrlAllowed(self::BASE_URL, ['a-api.coinpayments.net']);

            $this->client = new CoinPaymentsClient(
                self::setting(self::S_CLIENT_ID),
                self::setting(self::S_API_PRIVATE),
                self::BASE_URL,
            );
        }

        return $this->client;
    }

    // ------------------------------------------------------------------
    // 定价与访问判定
    // ------------------------------------------------------------------

    public function priceFor(int $postId): ?float
    {
        $price = DB::table('crypto_post_prices')->where('post_id', $postId)->value('price');

        return ($price !== null && (float) $price > 0) ? (float) $price : null;
    }

    public function setPrice(int $postId, float $price): void
    {
        DB::table('crypto_post_prices')->updateOrInsert(
            ['post_id' => $postId],
            ['price' => $price, 'currency' => 'USD', 'updated_at' => now(), 'created_at' => now()],
        );
    }

    public function hasAccess(?User $user, int $postId): bool
    {
        // 游客：按当前会话匹配已支付订单（同一浏览器会话内可见已解锁文章）
        if (! $user) {
            $sessionId = session()->getId();

            return $sessionId !== '' && CryptoOrder::query()
                ->where('session_id', $sessionId)
                ->where('post_id', $postId)
                ->where('status', 'paid')
                ->exists();
        }

        // 作者本人免费
        $authorId = Post::query()->whereKey($postId)->value('author_id');
        if ($authorId && (int) $authorId === (int) $user->getAuthIdentifier()) {
            return true;
        }

        return CryptoOrder::query()
            ->where('user_id', $user->getAuthIdentifier())
            ->where('post_id', $postId)
            ->where('status', 'paid')
            ->exists();
    }

    /**
     * 注册/登录后认领该会话下的游客订单（避免丢单）：把 session 绑定的
     * 游客订单归属到新用户。
     */
    public function claimGuestOrders(string $sessionId, User $user): int
    {
        if ($sessionId === '') {
            return 0;
        }

        return CryptoOrder::query()
            ->whereNull('user_id')
            ->where('session_id', $sessionId)
            ->update(['user_id' => $user->getAuthIdentifier(), 'updated_at' => now()]);
    }

    public function pendingOrderFor(User $user, int $postId): ?CryptoOrder
    {
        return CryptoOrder::query()
            ->where('user_id', $user->getAuthIdentifier())
            ->where('post_id', $postId)
            ->where('status', 'pending')
            ->latest()
            ->first();
    }

    // ------------------------------------------------------------------
    // 下单
    // ------------------------------------------------------------------

    /**
     * 创建某篇付费文章的订单（登录用户或游客），返回（订单, 收银台跳转地址）。
     * 游客订单绑定 session_id + ip；注册后自动认领归属。
     *
     * @return array{0: CryptoOrder, 1: string}
     *
     * @throws \RuntimeException 未配置 / 已拥有 / API 失败
     */
    public function createCheckout(?User $user, Post $post, ?string $guestEmail = null): array
    {
        if ($this->hasAccess($user, (int) $post->id)) {
            throw new \RuntimeException('您已解锁该文章');
        }

        $price = $this->priceFor((int) $post->id);
        if ($price === null) {
            throw new \RuntimeException('该文章不是付费内容');
        }

        $orderNo = 'CP-'.strtoupper(Str::random(12));
        $sessionId = session()->getId();
        $channel = self::mockMode() ? 'mock' : self::channel();

        $invoiceId = null;
        $checkoutUrl = null;

        if ($channel === 'mock') {
            // Mock 模式：本地联调，收银台指向内置模拟支付页
            $invoiceId = 'MOCK-'.$orderNo;
            $checkoutUrl = route('crypto-pay.mock.checkout', ['orderNo' => $orderNo]);
        } elseif ($channel === 'xcash') {
            // Xcash：HMAC 签名创建账单 → pay_url
            //
            // 修正两处历史缺陷：
            // 1) 回调地址曾写成 /crypto-pay/xcash/webhook，而实际注册的是
            //    /crypto-pay/webhook（CryptoPayPlugin::boot），导致回调 404；
            // 2) return_url 曾引用不存在的 CmsPostUrlHelper::for()，走到本分支
            //    必然抛出 Class not found。
            [$invoiceId, $checkoutUrl] = app(XcashClient::class)->createInvoice(
                $orderNo,
                'Unlock article: '.mb_substr((string) $post->title, 0, 32),
                (string) $price,
                'USD',
                url('/crypto-pay/webhook'),
                $this->postUrlFor($post),
            );
        } elseif ($this->configured()) {
            $invoices = $this->client()->invoices;

            /** @var InvoicesApi $invoices */
            $result = $invoices->postMerchantInvoicesV2([
                'amount' => [
                    'currency' => 'USD',
                    'value' => number_format($price, 2, '.', ''),
                ],
                'displayDescription' => 'Unlock article: '.mb_substr((string) $post->title, 0, 80),
                'metadata' => [
                    'orderNo' => $orderNo,
                    'postId' => (int) $post->id,
                    'userId' => $user?->getAuthIdentifier(),
                ],
                'links' => [
                    'returnUrl' => url('/'),
                    'cancelUrl' => url('/'),
                ],
            ]);

            $data = $result['data'] ?? $result ?? [];
            $invoiceId = (string) ($data['invoiceId'] ?? $data['id'] ?? '');
            $checkoutUrl = (string) ($data['checkoutUrl'] ?? $data['url'] ?? '');

            if ($invoiceId === '' || $checkoutUrl === '') {
                throw new \RuntimeException('CoinPayments 响应缺少发票信息');
            }
        } else {
            throw new \RuntimeException('支付未配置：请先在后台填写支付通道凭证或开启 Mock 模式');
        }

        $order = CryptoOrder::create([
            'order_no' => $orderNo,
            'user_id' => $user?->getAuthIdentifier(),
            'session_id' => $sessionId,
            'ip' => request()?->ip(),
            'guest_email' => $guestEmail,
            'channel' => $channel,
            'post_id' => $post->id,
            'amount' => $price,
            'fiat_currency' => 'USD',
            'invoice_id' => $invoiceId,
            'checkout_url' => $checkoutUrl,
            'status' => 'pending',
        ]);

        return [$order, $checkoutUrl];
    }

    // ------------------------------------------------------------------
    // 状态推进
    // ------------------------------------------------------------------

    /**
     * 标记订单已支付并授予访问权（幂等、并发安全）。
     *
     * 用条件更新 `WHERE status != 'paid'` 抢占状态推进：并发回调或
     * 「webhook + 主动轮询」同时到达时，只有一个请求会真正改到行（P1-2）。
     */
    public function markPaid(CryptoOrder $order, array $payload = []): bool
    {
        $affected = CryptoOrder::query()
            ->whereKey($order->id)
            ->where('status', '!=', 'paid')
            ->update([
                'status' => 'paid',
                'paid_at' => now(),
                'payload' => $payload ?: $order->payload,
                'updated_at' => now(),
            ]);

        return $affected > 0;
    }

    /**
     * 主动查询 CoinPayments 发票状态（webhook 不可达时的兜底）。
     * 返回 true 表示状态有更新且已支付。
     */
    public function refreshFromApi(CryptoOrder $order): bool
    {
        if ($order->status === 'paid' || self::mockMode() || ! $this->configured() || ! $order->invoice_id) {
            return false;
        }

        try {
            $result = $this->client()->invoices->getMerchantInvoicesByIdV1($order->invoice_id);
            $data = $result['data'] ?? $result ?? [];
            $state = strtolower((string) ($data['state'] ?? ''));

            if (in_array($state, ['completed', 'paid'], true)) {
                return $this->markPaid($order, ['source' => 'api-poll', 'state' => $state]);
            }
        } catch (\Throwable) {
            // 查询失败静默；下次再试
        }

        return false;
    }

    // ------------------------------------------------------------------
    // Webhook 签名校验（SDK WebhookVerifier，HMAC + 时间戳漂移检查）
    // ------------------------------------------------------------------

    public function verifyWebhook(string $method, string $url, string $rawBody, array $headers): bool
    {
        $signingValue = self::setting(self::S_HOOK_SIGNING);

        if ($signingValue === null) {
            return false;
        }

        return WebhookVerifier::verify(
            $method,
            $url,
            $rawBody,
            $headers,
            $signingValue,
            self::setting(self::S_CLIENT_ID),
        )->ok;
    }

    /** 按 invoiceId 找订单。 */
    public function orderByInvoice(string $invoiceId): ?CryptoOrder
    {
        return CryptoOrder::query()->where('invoice_id', $invoiceId)->first();
    }

    /**
     * 文章前台地址（支付完成后的同步回跳地址）。
     *
     * CMS 模块启用时按其固定链接设置生成；未启用时回退到内核默认文章路由。
     * 不引入 cms 模块内部类的硬依赖，避免模块未启用时抛出类不存在。
     */
    private function postUrlFor(Post $post): string
    {
        if (module_enabled('cms') && class_exists(\Modules\CMS\Support\CmsPermalink::class)) {
            try {
                return url(\Modules\CMS\Support\CmsPermalink::postUrl($post));
            } catch (\Throwable) {
                // 固定链接设置异常时回退
            }
        }

        return url('/post/'.$post->slug);
    }
}
