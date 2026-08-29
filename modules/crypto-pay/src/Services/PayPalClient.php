<?php

declare(strict_types=1);

namespace Modules\CryptoPay\Services;

use App\Support\SafeHttpClient;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

/**
 * PayPal 支付网关（REST v2，Orders API）：
 * - createOrder：创建订单 → 返回买家批准链接（approve）
 * - capture：买家批准回跳后收款（COMPLETED 即支付成功）
 * 凭据来自站点设置（sandbox/live 双环境）；外呼经 SafeHttpClient 白名单校验。
 */
final class PayPalClient
{
    public const S_CLIENT_ID = 'paypal_client_id';

    public const S_SECRET = 'paypal_secret';

    public const S_MODE = 'paypal_mode';

    public static function configured(): bool
    {
        return filled(\App\Support\SiteSettings::get(self::S_CLIENT_ID))
            && filled(\App\Support\SiteSettings::get(self::S_SECRET));
    }

    public static function mode(): string
    {
        return \App\Support\SiteSettings::get(self::S_MODE, 'sandbox') === 'live' ? 'live' : 'sandbox';
    }

    private static function apiBase(): string
    {
        return self::mode() === 'live'
            ? 'https://api-m.paypal.com'
            : 'https://api-m.sandbox.paypal.com';
    }

    private static function assertAllowed(string $url): void
    {
        SafeHttpClient::assertUrlAllowed($url, ['api-m.paypal.com', 'api-m.sandbox.paypal.com']);
    }

    /** OAuth2 token（缓存至过期前 60s）。 */
    private static function token(): string
    {
        $cacheKey = 'paypal_token:'.self::mode();

        return Cache::remember($cacheKey, 3000, function () {
            $url = self::apiBase().'/v1/oauth2/token';
            self::assertAllowed($url);

            $response = Http::asForm()->timeout(15)
                ->withBasicAuth(
                    (string) \App\Support\SiteSettings::get(self::S_CLIENT_ID),
                    (string) \App\Support\SiteSettings::get(self::S_SECRET),
                )
                ->post($url, ['grant_type' => 'client_credentials']);

            if (! $response->successful()) {
                throw new \RuntimeException('PayPal 认证失败：'.$response->status());
            }

            return (string) $response->json('access_token');
        });
    }

    /**
     * 创建 PayPal 订单，返回 [orderId, approveUrl]。
     *
     * @return array{0: string, 1: string}
     *
     * @throws \RuntimeException
     */
    public static function createOrder(string $orderNo, string $description, string $amount, string $currency, string $returnUrl, string $cancelUrl): array
    {
        $url = self::apiBase().'/v2/checkout/orders';
        self::assertAllowed($url);

        $response = Http::withToken(self::token())->timeout(20)->post($url, [
            'intent' => 'CAPTURE',
            'purchase_units' => [[
                'custom_id' => $orderNo,
                'description' => mb_substr($description, 0, 120),
                'amount' => ['currency_code' => $currency, 'value' => $amount],
            ]],
            'application_context' => [
                'brand_name' => config('app.name', 'CMSForum'),
                'user_action' => 'PAY_NOW',
                'return_url' => $returnUrl,
                'cancel_url' => $cancelUrl,
            ],
        ]);

        if (! $response->successful()) {
            throw new \RuntimeException('PayPal 创建订单失败：'.$response->status().' '.mb_substr($response->body(), 0, 200));
        }

        $orderId = (string) $response->json('id');
        $approve = collect($response->json('links') ?? [])
            ->firstWhere('rel', 'approve')['href'] ?? '';

        if ($orderId === '' || $approve === '') {
            throw new \RuntimeException('PayPal 响应缺少订单号或批准链接');
        }

        return [$orderId, $approve];
    }

    /**
     * 收款（capture）。返回 [captured, orderNo, raw]。
     *
     * @return array{0: bool, 1: string, 2: array}
     */
    public static function capture(string $paypalOrderId): array
    {
        $url = self::apiBase().'/v2/checkout/orders/'.urlencode($paypalOrderId).'/capture';
        self::assertAllowed($url);

        $response = Http::withToken(self::token())->timeout(20)
            ->withHeaders(['Prefer' => 'return=representation'])
            ->post($url);

        $body = $response->json() ?? [];
        $status = strtolower((string) ($body['status'] ?? ''));
        $orderNo = (string) ($body['purchase_units'][0]['custom_id']
            ?? $body['purchase_units'][0]['payments']['captures'][0]['custom_id'] ?? '');

        return [$response->successful() && $status === 'completed', $orderNo, $body];
    }
}
