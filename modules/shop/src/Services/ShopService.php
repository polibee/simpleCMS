<?php

declare(strict_types=1);

namespace Modules\Shop\Services;

use App\Support\SiteSettings;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Modules\CryptoPay\Services\PaymentGateway;
use Modules\Shop\Models\ShopOrder;
use Modules\Shop\Models\ShopProduct;

/**
 * 商城结账服务：统一经 PaymentGateway（Mock/Xcash/PayPal/码支付/虎皮椒），
 * 支付成功 → 核销库存 + 累计销量（幂等）。
 */
final class ShopService
{
    public static function mockMode(): bool
    {
        return SiteSettings::bool('crypto_pay_mock_mode');
    }

    /**
     * 创建支付订单，返回 [ShopOrder, payUrl, kind]。
     *
     * 库存采用「下单即预占」：在同一事务内行锁商品、校验并扣减库存，
     * 支付回调只推进订单状态、不再二次扣减。旧实现下单时不预占、支付时才扣，
     * 库存为 1 时两个用户可同时下单并各自付款，造成超卖（P1-3）。
     *
     * @param  string|null  $method  用户选择的支付方式（alipay/wxpay），仅 CNY 通道有效
     * @return array{0: ShopOrder, 1: string, 2: string}
     *
     * @throws \RuntimeException
     */
    public function checkout(?Authenticatable $user, ShopProduct $product, ?string $method = null, ?string $guestEmail = null): array
    {
        // 先把超时的待付订单库存放回去，避免长期占用（无需额外的调度器也自愈）
        $this->releaseExpired($product);

        $orderNo = 'SHOP-'.strtoupper(Str::random(12));

        // 预占库存（行锁 + 条件扣减，原子）
        $reserved = DB::transaction(function () use ($product) {
            $fresh = ShopProduct::query()
                ->whereKey($product->id)
                ->lockForUpdate()
                ->first();

            if (! $fresh || $fresh->status !== 'on_sale') {
                throw new \RuntimeException('商品当前未上架。');
            }

            if ((int) $fresh->stock < 1) {
                throw new \RuntimeException('商品已售罄。');
            }

            return ShopProduct::query()
                ->whereKey($product->id)
                ->where('stock', '>', 0)
                ->decrementEach(['stock' => 1]) > 0;
        });

        if (! $reserved) {
            throw new \RuntimeException('商品已售罄。');
        }

        try {
            [$channel, $invoiceId, $payUrl, $kind] = array_values(PaymentGateway::checkout(
                $orderNo,
                'Shop: '.mb_substr($product->name, 0, 100),
                (float) $product->price,
                $product->currency,
                [
                    'notify' => url('/shop/notify'),
                    'return' => url('/shop/paypal/return'),
                    'cancel' => url('/shop'),
                    'mock' => route('shop.mock.checkout', ['orderNo' => $orderNo]),
                ],
                $method,
            ));

            $order = ShopOrder::create([
                'order_no' => $orderNo,
                // 游客下单 user_id 为空，邮箱存 guest_email（邮件交付用）
                'user_id' => $user?->getAuthIdentifier(),
                'guest_email' => $user ? null : $guestEmail,
                'product_id' => $product->id,
                'amount' => $product->price,
                'currency' => $product->currency,
                'quantity' => 1,
                'channel' => $channel,
                'invoice_id' => $invoiceId,
                'checkout_url' => $payUrl,
                'status' => 'pending',
            ]);
        } catch (\Throwable $e) {
            // 下单失败（网关异常/未配置）→ 归还预占的库存
            ShopProduct::query()->whereKey($product->id)->incrementEach(['stock' => 1]);

            throw $e;
        }

        return [$order, $payUrl, $kind];
    }

    /**
     * 释放超时的待付订单库存（默认 30 分钟未支付即回收）。
     *
     * @return int 释放的订单数
     */
    public function releaseExpired(?ShopProduct $product = null, int $minutes = 30): int
    {
        $query = ShopOrder::query()
            ->where('status', 'pending')
            ->where('created_at', '<', now()->subMinutes($minutes));

        if ($product !== null) {
            $query->where('product_id', $product->id);
        }

        $orders = $query->get();

        foreach ($orders as $order) {
            DB::transaction(function () use ($order) {
                // 条件更新占位：只有仍是 pending 的订单才会释放，避免与支付回调用时冲撞
                $affected = ShopOrder::query()
                    ->whereKey($order->id)
                    ->where('status', 'pending')
                    ->update(['status' => 'failed', 'updated_at' => now()]);

                if ($affected > 0) {
                    ShopProduct::query()->whereKey($order->product_id)->incrementEach(['stock' => 1]);
                }
            });
        }

        return $orders->count();
    }

    /**
     * 支付成功：标记订单 + 累计销量 + 执行交付（幂等）。
     *
     * 并发安全：用条件更新 `WHERE status = 'pending'` 抢占状态推进，
     * 只有抢到的那个请求才会继续发货；并发回调不会重复发货（P1-2）。
     * 库存在下单时已预占，此处不再扣减。
     */
    public function markPaid(ShopOrder $order): bool
    {
        $affected = DB::transaction(function () use ($order) {
            $affected = ShopOrder::query()
                ->whereKey($order->id)
                ->where('status', 'pending')
                ->update(['status' => 'paid', 'paid_at' => now(), 'updated_at' => now()]);

            if ($affected > 0) {
                ShopProduct::query()
                    ->whereKey($order->product_id)
                    ->incrementEach(['sold' => 1]);
            }

            return $affected;
        });

        if ($affected === 0) {
            return false; // 已被其它请求推进（重复回调 / 并发回跳）
        }

        $this->deliver($order->fresh());

        return true;
    }

    /**
     * 执行交付：根据商品 delivery_type 生成 delivered_data。
     * - download → payload 即下载链接
     * - content  → payload 即文本内容
     * - invite_code → 自动生成一枚邀请码并归属购买者
     * - email → 通过后台邮箱服务把 payload（账号/激活码/邀请码等）发给买家
     */
    public function deliver(ShopOrder $order): void
    {
        if ($order->delivered_data !== null) {
            return; // 已交付
        }

        $product = $order->product;
        if (! $product || $product->delivery_type === 'none') {
            return;
        }

        if ($product->delivery_type === 'email') {
            $this->deliverByEmail($order, $product);

            return;
        }

        $data = match ($product->delivery_type) {
            'download' => ['type' => 'download', 'url' => $product->delivery_payload, 'name' => $product->name],
            'content' => ['type' => 'content', 'content' => $product->delivery_payload],
            'invite_code' => $this->deliverInviteCode($order),
            default => null,
        };

        if ($data !== null) {
            $order->forceFill(['delivered_data' => $data])->save();
        }
    }

    /**
     * 邮件交付：把商品 payload（账号 / 激活码 / 邀请码 / 下载说明等）发给买家邮箱。
     * 买家为登录用户（购买路由要求 auth），邮箱取 user.email。
     *
     * 幂等：email_sent_at 非空即已发送，重复回调不再重发；发送失败在订单上
     * 记录错误，后续回调 / 手动重发可补。
     */
    private function deliverByEmail(ShopOrder $order, ShopProduct $product): void
    {
        if ($order->email_sent_at !== null) {
            return;
        }

        // 邮件交付目标：游客订单用 guest_email，登录用户用账号邮箱
        $email = $order->guest_email ?? $order->user?->email;

        if (! $email) {
            $order->forceFill(['delivered_data' => ['type' => 'email', 'error' => '买家无邮箱，无法邮件交付']])->save();

            return;
        }

        $payload = trim((string) $product->delivery_payload);
        if ($payload === '') {
            $order->forceFill(['delivered_data' => ['type' => 'email', 'error' => '商品未配置邮件交付内容']])->save();

            return;
        }

        try {
            \Illuminate\Support\Facades\Mail::raw(
                $this->emailDeliveryBody($order, $product, $payload),
                function ($message) use ($email, $product): void {
                    $message->to($email)->subject('【'.config('app.name', 'simpleCMS').'】您购买的商品：'.$product->name);
                },
            );

            $order->forceFill([
                'delivered_data' => ['type' => 'email', 'to' => $email, 'sent_at' => now()->toDateTimeString()],
                'email_sent_at' => now(),
            ])->save();
        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\Log::error('商城邮件交付失败', [
                'order_no' => $order->order_no,
                'email' => $email,
                'error' => $e->getMessage(),
            ]);

            $order->forceFill(['delivered_data' => ['type' => 'email', 'error' => '邮件发送失败，请联系客服']])->save();
        }
    }

    private function emailDeliveryBody(ShopOrder $order, ShopProduct $product, string $payload): string
    {
        $lines = [
            '您好，',
            '',
            '感谢购买「'.$product->name.'」。以下是您的商品内容：',
            '',
            '————————————————————————',
            $payload,
            '————————————————————————',
            '',
            '订单号：'.$order->order_no,
            '购买时间：'.$order->paid_at?->toDateTimeString(),
            '',
            '请妥善保管以上内容，勿向他人泄露。',
        ];

        return implode("\n", $lines);
    }

    /** 邀请码交付：调用 InviteService 生成一枚邀请码归属购买者。 */
    private function deliverInviteCode(ShopOrder $order): ?array
    {
        if (! class_exists(\Modules\Invite\Services\InviteService::class) || ! module_enabled('invite')) {
            return ['type' => 'invite_code', 'code' => null, 'error' => '邀请码模块未启用'];
        }

        try {
            // source 必须是 invite_codes.source 枚举内的值（admin|gold|crypto）；
            // 旧实现传 'shop_purchase' 会在严格模式下触发数据截断，被 catch 吞掉，
            // 结果是买家已付款却只拿到错误信息（P3-3）。商城购买归入 crypto 来源。
            $codes = app(\Modules\Invite\Services\InviteService::class)
                ->generate(1, 'crypto', $order->user_id, null, (float) $order->amount);

            return ['type' => 'invite_code', 'code' => $codes[0]->code];
        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\Log::error('商城邀请码发货失败', [
                'order_no' => $order->order_no,
                'error' => $e->getMessage(),
            ]);

            return ['type' => 'invite_code', 'code' => null, 'error' => $e->getMessage()];
        }
    }
}
