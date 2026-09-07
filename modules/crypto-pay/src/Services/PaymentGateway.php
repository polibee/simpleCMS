<?php

declare(strict_types=1);

namespace Modules\CryptoPay\Services;

use App\Support\SiteSettings;
use Illuminate\Support\Facades\Http;

/**
 * 统一支付网关：多通道同时启用，用户前台自选支付方式。
 *
 * 后台设置 payment_channels = 逗号分隔的通道列表（如 'codepay,paypal,xcash'），
 * 每个通道的凭据区块在"加密支付"设置页独立配置。
 * 用户结账时选择支付方式（method），网关自动路由到支持该方式的已启用通道。
 *
 * 使用方式：
 *   $urls = ['notify' => …, 'return' => …, 'cancel' => …, 'mock' => …];
 *   $pay  = PaymentGateway::checkout($orderNo, $title, $amount, $currency, $urls, $method);
 *   // $pay['kind']: redirect=跳转 / qrcode=二维码链接
 *   // $pay['channel']: 实际使用的通道
 *
 * CNY 通道（码支付/虎皮椒）金额按 payment_fx_rate（USD→CNY）换算。
 */
final class PaymentGateway
{
    /** 所有支持的通道及其可用支付方式。 */
    private const CHANNEL_METHODS = [
        'codepay' => ['alipay', 'wxpay', 'qqpay'],
        'xunhupay' => ['alipay', 'wechat'],
        'paypal' => ['paypal'],
        'xcash' => ['crypto'],
    ];

    /** @return array<string> 当前启用的通道列表。 */
    public static function enabledChannels(): array
    {
        if (SiteSettings::bool('crypto_pay_mock_mode')) {
            return ['mock'];
        }

        $raw = (string) SiteSettings::get('payment_channels', '');
        if ($raw === '') {
            return [];
        }

        // 兼容 JSON 数组 / 逗号分隔两种格式
        if (str_starts_with($raw, '[')) {
            $channels = json_decode($raw, true) ?: [];
        } else {
            $channels = array_map('trim', explode(',', $raw));
        }

        return array_values(array_filter($channels));
    }

    /** @return array<array{key: string, label: string, icon: string, channel: string}> 用户可选支付方式（去重、按启用通道聚合）。 */
    public static function availableMethods(): array
    {
        $meta = self::methodMeta();

        $methods = [];
        foreach (self::enabledChannels() as $channel) {
            foreach (self::CHANNEL_METHODS[$channel] ?? [] as $method) {
                if (! isset($methods[$method])) {
                    $methods[$method] = [
                        'key' => $method,
                        'label' => $meta[$method]['label'] ?? $method,
                        'icon' => $meta[$method]['icon'] ?? '💳',
                        'channel' => $channel,
                    ];
                }
            }
        }

        return array_values($methods);
    }

    private static function methodMeta(): array
    {
        return [
            'alipay' => ['label' => '支付宝', 'icon' => '🅰'],
            'wxpay' => ['label' => '微信支付', 'icon' => '💬'],
            'qqpay' => ['label' => 'QQ 钱包', 'icon' => '🐧'],
            'paypal' => ['label' => 'PayPal', 'icon' => '🌐'],
            'crypto' => ['label' => '加密货币', 'icon' => '₿'],
            'mock' => ['label' => 'Mock 支付', 'icon' => '🧪'],
        ];
    }

    /**
     * 发起支付：根据用户选择的 method 自动路由到对应已启用通道。
     *
     * @param  string|null  $method  用户选择的支付方式（alipay/wxpay/qqpay/paypal/crypto）；null = 用第一个可用通道
     * @return array{channel: string, invoice_id: string, pay_url: string, kind: string}
     *
     * @throws \RuntimeException
     */
    public static function checkout(string $orderNo, string $title, float $amount, string $currency, array $urls, ?string $method = null): array
    {
        // Mock 优先（本地联调）
        if (SiteSettings::bool('crypto_pay_mock_mode')) {
            return self::result('mock', 'MOCK-'.$orderNo, (string) ($urls['mock'] ?? '/'), 'redirect');
        }

        $enabled = self::enabledChannels();
        if ($enabled === []) {
            throw new \RuntimeException('没有启用的支付通道，请联系管理员。');
        }

        // 根据 method 找到支持该方式的通道
        if ($method !== null) {
            foreach ($enabled as $ch) {
                if (in_array($method, self::CHANNEL_METHODS[$ch] ?? [], true)) {
                    return self::dispatch($ch, $orderNo, $title, $amount, $currency, $urls, $method);
                }
            }
            throw new \RuntimeException("支付方式 {$method} 当前不可用。");
        }

        // 未指定方式 → 用第一个可用通道
        return self::dispatch($enabled[0], $orderNo, $title, $amount, $currency, $urls);
    }

    private static function dispatch(string $channel, string $orderNo, string $title, float $amount, string $currency, array $urls, ?string $method = null): array
    {
        return match ($channel) {
            'paypal' => self::paypal($orderNo, $title, $amount, $currency, $urls),
            'codepay' => self::codepay($orderNo, $title, $amount, $currency, $urls, $method),
            'xunhupay' => self::xunhupay($orderNo, $title, $amount, $currency, $urls, $method),
            'xcash' => self::xcash($orderNo, $title, $amount, $currency, $urls),
            default => throw new \RuntimeException("未知支付通道: {$channel}"),
        };
    }

    private static function result(string $channel, string $invoiceId, string $payUrl, string $kind): array
    {
        return ['channel' => $channel, 'invoice_id' => $invoiceId, 'pay_url' => $payUrl, 'kind' => $kind];
    }

    private static function toCny(float $amount, string $currency): string
    {
        $rate = max(0.01, (float) SiteSettings::get('payment_fx_rate', '7.2'));

        return number_format($currency === 'USD' ? $amount * $rate : $amount, 2, '.', '');
    }

    private static function xcash(string $orderNo, string $title, float $amount, string $currency, array $urls): array
    {
        // XcashClient::createInvoice 是实例方法（用 app() 解析，支持容器替换）
        [$invoiceId, $payUrl] = app(XcashClient::class)->createInvoice(
            $orderNo,
            mb_substr($title, 0, 100),
            (string) $amount,
            $currency,
            $urls['notify'],
            $urls['return'],
        );

        return self::result('xcash', $invoiceId, $payUrl, 'redirect');
    }

    private static function paypal(string $orderNo, string $title, float $amount, string $currency, array $urls): array
    {
        if (! PayPalClient::configured()) {
            throw new \RuntimeException('PayPal 未配置，请在后台填写 Client ID 与 Secret。');
        }

        [$invoiceId, $payUrl] = PayPalClient::createOrder(
            $orderNo,
            $title,
            (string) $amount,
            $currency,
            $urls['return'],
            $urls['cancel'],
        );

        return self::result('paypal', $invoiceId, $payUrl, 'redirect');
    }

    private static function codepay(string $orderNo, string $title, float $amount, string $currency, array $urls, ?string $method = null): array
    {
        if (! CodePayClient::configured()) {
            throw new \RuntimeException('码支付未配置，请在后台填写网关 / PID / 密钥。');
        }

        [$payUrl, $kind] = CodePayClient::createPayment(
            $orderNo,
            $title,
            (float) self::toCny($amount, $currency),
            (string) (request()?->ip() ?? '127.0.0.1'),
            $urls['notify'],
            $urls['return'],
            $method,
        );

        return self::result('codepay', $orderNo, $payUrl, $kind);
    }

    private static function xunhupay(string $orderNo, string $title, float $amount, string $currency, array $urls, ?string $method = null): array
    {
        if (! XunHuPayClient::configured()) {
            throw new \RuntimeException('虎皮椒未配置，请在后台填写 APPID 与 APPSECRET。');
        }

        [$payUrl, $kind] = XunHuPayClient::createPayment(
            $orderNo,
            $title,
            (float) self::toCny($amount, $currency),
            $urls['notify'],
            $urls['return'],
            $method,
        );

        return self::result('xunhupay', $orderNo, $payUrl, $kind);
    }

    /** 通用二维码展示页地址（kind=qrcode 时跳转用）。 */
    public static function qrPageUrl(string $payUrl, string $backUrl): string
    {
        return '/pay/qr?'.http_build_query(['u' => $payUrl, 'back' => $backUrl]);
    }
}
