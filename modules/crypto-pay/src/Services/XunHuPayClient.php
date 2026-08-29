<?php

declare(strict_types=1);

namespace Modules\CryptoPay\Services;

use App\Support\SafeHttpClient;
use App\Support\SiteSettings;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

/**
 * 虎皮椒（XunHuPay）支付网关客户端。
 * 协议：POST JSON → /payment/do.html；MD5 签名（参数 ksort 拼接 + appsecret）；
 * 回调 POST status=OD 为支付成功；金额单位元（CNY），精确到分。
 */
final class XunHuPayClient
{
    public const S_APP_ID = 'xunhupay_appid';

    public const S_APP_SECRET = 'xunhupay_appsecret';

    public const S_GATEWAY = 'xunhupay_gateway';

    public const S_PLUGIN = 'xunhupay_plugin';

    public const OFFICIAL_GATEWAY = 'https://api.xunhupay.com/payment/do.html';

    public static function configured(): bool
    {
        return filled(SiteSettings::get(self::S_APP_ID)) && filled(SiteSettings::get(self::S_APP_SECRET));
    }

    private static function gateway(): string
    {
        return (string) (SiteSettings::get(self::S_GATEWAY) ?: self::OFFICIAL_GATEWAY);
    }

    private static function plugin(): string
    {
        $p = SiteSettings::get(self::S_PLUGIN, 'alipay');

        return in_array($p, ['alipay', 'wechat'], true) ? $p : 'alipay';
    }

    /** 用户选择优先，无效值回落后台默认。 */
    private static function resolvePlugin(?string $userChoice): string
    {
        if ($userChoice && in_array($userChoice, ['alipay', 'wechat'], true)) {
            return $userChoice;
        }

        return self::plugin();
    }

    /** MD5 签名：参数 ksort 拼接（排除 hash / 空值）+ appsecret。 */
    public static function generateHash(array $data, string $appSecret): string
    {
        ksort($data);
        reset($data);

        $parts = [];
        foreach ($data as $key => $val) {
            if ($key === 'hash' || $val === null || $val === '') {
                continue;
            }
            $parts[] = $key.'='.$val;
        }

        return md5(implode('&', $parts).$appSecret);
    }

    /**
     * 发起支付，返回 [payUrl, kind]；kind=qrcode（扫码图）/ redirect（跳转）。
     * $plugin 用户选择的支付方式（alipay/wechat）；null 则用后台默认。
     *
     * @return array{0: string, 1: string}
     *
     * @throws \RuntimeException
     */
    public static function createPayment(string $orderNo, string $title, float $amountCny, string $notifyUrl, string $returnUrl, ?string $plugin = null): array
    {
        if (! self::configured()) {
            throw new \RuntimeException('虎皮椒未配置，请填写 APPID 与 APPSECRET。');
        }

        $appSecret = (string) SiteSettings::get(self::S_APP_SECRET);
        $data = [
            'version' => '1.1',
            'appid' => (string) SiteSettings::get(self::S_APP_ID),
            'plugins' => self::resolvePlugin($plugin),
            'trade_order_id' => $orderNo,
            'total_fee' => number_format($amountCny, 2, '.', ''),
            'title' => mb_substr($title, 0, 32),
            'time' => (string) time(),
            'notify_url' => $notifyUrl,
            'return_url' => $returnUrl,
            'nonce_str' => Str::random(16),
        ];
        $data['hash'] = self::generateHash($data, $appSecret);

        $url = self::gateway();
        self::assertAllowed($url);

        $response = Http::timeout(30)->asJson()->post($url, $data);
        $result = $response->json() ?? [];

        if ((int) ($result['errcode'] ?? -1) !== 0) {
            throw new \RuntimeException('虎皮椒下单失败：'.($result['errmsg'] ?? $response->status()));
        }

        // 移动端返回跳转 url；桌面返回二维码图
        if (! empty($result['url'])) {
            return [(string) $result['url'], 'redirect'];
        }

        return [(string) ($result['url_qrcode'] ?? ''), 'qrcode'];
    }

    /** 回调验签：hash 校验（虎皮椒 POST 字段含 status=OD 为支付成功）。 */
    public static function verifyNotify(array $data, string $appSecret): bool
    {
        if (! isset($data['hash'], $data['trade_order_id'])) {
            return false;
        }

        // 与官方 demo 一致：去除转义斜杠后验签
        foreach ($data as $k => $v) {
            $data[$k] = stripslashes((string) $v);
        }

        return hash_equals(self::generateHash($data, $appSecret), (string) $data['hash']);
    }

    public static function notifyAppSecret(): string
    {
        return (string) SiteSettings::get(self::S_APP_SECRET);
    }

    private static function assertAllowed(string $url): void
    {
        SafeHttpClient::assertUrlAllowed($url, ['api.xunhupay.com', 'pay.wordpressopen.com']);
    }
}
