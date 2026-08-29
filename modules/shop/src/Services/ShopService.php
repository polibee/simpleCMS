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
     * @param  string|null  $method  用户选择的支付方式（alipay/wxpay），仅 CNY 通道有效
     * @return array{0: ShopOrder, 1: string, 2: string}
     *
     * @throws \RuntimeException
     */
    public function checkout(Authenticatable $user, ShopProduct $product, ?string $method = null): array
    {
        if ($product->status !== 'on_sale') {
            throw new \RuntimeException('商品当前未上架。');
        }

        if ($product->stock < 1) {
            throw new \RuntimeException('商品已售罄。');
        }

        $orderNo = 'SHOP-'.strtoupper(Str::random(12));

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
            'user_id' => $user->getAuthIdentifier(),
            'product_id' => $product->id,
            'amount' => $product->price,
            'currency' => $product->currency,
            'quantity' => 1,
            'channel' => $channel,
            'invoice_id' => $invoiceId,
            'checkout_url' => $payUrl,
            'status' => 'pending',
        ]);

        return [$order, $payUrl, $kind];
    }

    /** 支付成功：标记订单 + 扣库存 + 累计销量 + 执行交付（幂等）。 */
    public function markPaid(ShopOrder $order): bool
    {
        if ($order->status === 'paid') {
            return false;
        }

        DB::transaction(function () use ($order) {
            $order->forceFill([
                'status' => 'paid',
                'paid_at' => now(),
            ])->save();

            ShopProduct::query()
                ->whereKey($order->product_id)
                ->where('stock', '>', 0)
                ->decrementEach(['stock' => 1]);
            ShopProduct::query()
                ->whereKey($order->product_id)
                ->incrementEach(['sold' => 1]);
        });

        $this->deliver($order->fresh());

        return true;
    }

    /**
     * 执行交付：根据商品 delivery_type 生成 delivered_data。
     * - download → payload 即下载链接
     * - content  → payload 即文本内容
     * - invite_code → 自动生成一枚邀请码并归属购买者
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

    /** 邀请码交付：调用 InviteService 生成一枚邀请码归属购买者。 */
    private function deliverInviteCode(ShopOrder $order): ?array
    {
        if (! class_exists(\Modules\Invite\Services\InviteService::class) || ! module_enabled('invite')) {
            return ['type' => 'invite_code', 'code' => null, 'error' => '邀请码模块未启用'];
        }

        try {
            $codes = app(\Modules\Invite\Services\InviteService::class)
                ->generate(1, 'shop_purchase', $order->user_id);

            return ['type' => 'invite_code', 'code' => $codes[0]->code];
        } catch (\Throwable $e) {
            return ['type' => 'invite_code', 'code' => null, 'error' => $e->getMessage()];
        }
    }
}
