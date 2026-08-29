<?php

declare(strict_types=1);

namespace Modules\Invite\Services;

use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Modules\Invite\Models\InviteCode;
use Modules\Invite\Models\InviteOrder;

/**
 * 邀请码服务：生成 / 注册校验 / 金币购买 / 加密购买（Xcash 通道 + Mock 模式）。
 * 购买开关与价格由后台"站点设置→邀请码"控制。
 */
final class InviteService
{
    // ------------------------------------------------------------------
    // 生成 / 核销
    // ------------------------------------------------------------------

    /**
     * 批量生成邀请码。
     *
     * @return list<InviteCode>
     */
    public function generate(int $count, string $source = 'admin', ?int $createdBy = null, ?int $expiresDays = null, ?float $paidAmount = null, ?int $goldSpent = null): array
    {
        $count = max(1, min(100, $count));
        $batchId = (string) Str::uuid();
        $codes = [];

        DB::transaction(function () use (&$codes, $count, $source, $createdBy, $expiresDays, $paidAmount, $goldSpent, $batchId) {
            for ($i = 0; $i < $count; $i++) {
                $codes[] = InviteCode::create([
                    'code' => $this->uniqueCode(),
                    'source' => $source,
                    'paid_amount' => $paidAmount,
                    'gold_spent' => $goldSpent,
                    'created_by' => $createdBy,
                    'expires_at' => $expiresDays ? now()->addDays($expiresDays) : null,
                    'status' => 'active',
                    'batch_id' => $batchId,
                ]);
            }
        });

        return $codes;
    }

    /**
     * 注册时校验邀请码（开关开启时必填、必须可用）。
     *
     * @throws ValidationException
     */
    public function validateForRegistration(?string $code): void
    {
        $code = trim((string) $code);

        if ($code === '') {
            throw ValidationException::withMessages([
                'invite_code' => '本站注册需要邀请码，请向管理员或好友获取。',
            ]);
        }

        $invite = InviteCode::query()->where('code', strtoupper($code))->first();

        if (! $invite || ! $invite->isUsable()) {
            throw ValidationException::withMessages([
                'invite_code' => '邀请码无效、已使用或已过期。',
            ]);
        }
    }

    /** 注册成功后核销（幂等：已使用直接返回）。 */
    public function consume(string $code, int $userId): void
    {
        $invite = InviteCode::query()
            ->where('code', strtoupper(trim($code)))
            ->where('status', 'active')
            ->first();

        if (! $invite) {
            return;
        }

        $invite->forceFill([
            'status' => 'used',
            'used_by' => $userId,
            'used_at' => now(),
        ])->save();
    }

    /** 注册是否需要邀请码（模块启用且后台开关开启）。 */
    public static function registrationRequired(): bool
    {
        if (! class_exists(self::class) || ! module_enabled('invite')) {
            return false;
        }

        return \App\Support\SiteSettings::bool('invite_registration_enabled');
    }

    // ------------------------------------------------------------------
    // 金币购买
    // ------------------------------------------------------------------

    public static function goldPrice(): int
    {
        return max(0, (int) \App\Support\SiteSettings::get('invite_gold_price', '100'));
    }

    /**
     * 金币购买一枚邀请码。
     *
     * @return InviteCode
     *
     * @throws \RuntimeException 未开启 / 余额不足
     */
    public function purchaseWithGold(Authenticatable $user): InviteCode
    {
        if (! \App\Support\SiteSettings::bool('invite_allow_gold')) {
            throw new \RuntimeException('金币购买未开放。');
        }

        $price = self::goldPrice();
        $wallet = app(\Modules\Economy\Services\WalletService::class);

        $code = DB::transaction(function () use ($user, $wallet, $price) {
            // 先扣金币（不足抛异常回滚），再生成邀请码
            if ($price > 0) {
                $wallet->apply($user, 'gold', 'debit', $price, 'invite_purchase', Str::uuid()->toString(), '购买邀请码');
            }

            return $this->generate(1, 'gold', (int) $user->getAuthIdentifier(), null, null, $price)[0];
        });

        return $code;
    }

    // ------------------------------------------------------------------
    // 加密购买（Xcash / Mock）
    // ------------------------------------------------------------------

    public static function cryptoPrice(): float
    {
        return round(max(0.0, (float) \App\Support\SiteSettings::get('invite_crypto_price', '1.99')), 2);
    }

    /**
     * 创建加密/法币支付订单，返回 [InviteOrder, payUrl, kind]。
     *
     * @param  string|null  $method  用户选择的支付方式（alipay/wxpay），仅 CNY 通道有效
     * @return array{0: InviteOrder, 1: string, 2: string}
     *
     * @throws \RuntimeException
     */
    public function purchaseWithCrypto(Authenticatable $user, ?string $method = null): array
    {
        if (! \App\Support\SiteSettings::bool('invite_allow_crypto')) {
            throw new \RuntimeException('加密购买未开放。');
        }

        $amount = self::cryptoPrice();
        $orderNo = 'INV-'.strtoupper(Str::random(12));

        [$channel, $invoiceId, $payUrl, $kind] = array_values(
            \Modules\CryptoPay\Services\PaymentGateway::checkout(
                $orderNo,
                'Invite code purchase',
                $amount,
                'USD',
                [
                    'notify' => url('/invite/notify'),
                    'return' => url('/invite'),
                    'cancel' => url('/invite'),
                    'mock' => route('invite.mock.checkout', ['orderNo' => $orderNo]),
                ],
                $method,
            ),
        );

        $order = InviteOrder::create([
            'order_no' => $orderNo,
            'user_id' => $user->getAuthIdentifier(),
            'amount' => $amount,
            'fiat_currency' => 'USD',
            'channel' => $channel,
            'invoice_id' => $invoiceId,
            'checkout_url' => $payUrl,
            'status' => 'pending',
        ]);

        return [$order, $payUrl, $kind];
    }

    /** 标记订单已支付并发放邀请码（幂等）。 */
    public function markOrderPaid(InviteOrder $order): bool
    {
        if ($order->status === 'paid') {
            return false;
        }

        DB::transaction(function () use ($order) {
            $order->forceFill([
                'status' => 'paid',
                'paid_at' => now(),
            ])->save();

            $this->generate(1, 'crypto', (int) $order->user_id, null, $order->amount);
        });

        return true;
    }

    // ------------------------------------------------------------------
    // 内部
    // ------------------------------------------------------------------

    private function uniqueCode(): string
    {
        do {
            $code = 'INV-'.strtoupper(Str::random(10));
        } while (InviteCode::query()->where('code', $code)->exists());

        return $code;
    }
}
