<?php

declare(strict_types=1);

namespace Modules\CryptoPay\Services;

use App\Support\SafeHttpClient;
use App\Support\SiteSettings;
use Illuminate\Support\Facades\Http;

/**
 * 码支付（epay 协议兼容）客户端：
 * - API 支付：POST /mapi.php（form-urlencoded）→ payurl/qrcode/urlscheme
 * - 异步通知：GET notify_url，trade_status=TRADE_SUCCESS 为成功
 * - MD5 签名：参数 ASCII 升序拼接（排除 sign/sign_type/空值）+ 商户密钥
 * 金额单位元（CNY）。
 */
final class CodePayClient
{
    public const S_GATEWAY = 'codepay_gateway';

    public const S_PID = 'codepay_pid';

    public const S_KEY = 'codepay_key';

    public const S_TYPE = 'codepay_type';

    public const S_NOTIFY_KEY = 'codepay_notify_key';

    public static function configured(): bool
    {
        $gateway = SiteSettings::get(self::S_GATEWAY);

        return filled($gateway) && filled(SiteSettings::get(self::S_PID)) && filled(SiteSettings::get(self::S_KEY));
    }

    private static function gateway(): string
    {
        return rtrim((string) SiteSettings::get(self::S_GATEWAY), '/');
    }

    private static function type(): string
    {
        $t = SiteSettings::get(self::S_TYPE, 'alipay');

        return in_array($t, ['alipay', 'wxpay', 'qqpay'], true) ? $t : 'alipay';
    }

    /** 用户选择优先，无效值回落后台默认。 */
    private static function resolveType(?string $userChoice): string
    {
        if ($userChoice && in_array($userChoice, ['alipay', 'wxpay', 'qqpay'], true)) {
            return $userChoice;
        }

        return self::type();
    }

    /** MD5 签名（协议规则：排除 sign/sign_type/空值，ASCII 升序）。 */
    public static function generateSign(array $data, string $key): string
    {
        ksort($data);
        reset($data);

        $parts = [];
        foreach ($data as $k => $v) {
            if ($k === 'sign' || $k === 'sign_type' || $v === null || $v === '') {
                continue;
            }
            $parts[] = $k.'='.$v;
        }

        return md5(implode('&', $parts).$key);
    }

    /**
     * API 支付：返回 [payUrl, kind]；kind=qrcode（二维码链接）/ redirect（跳转）。
     * $type 用户选择的支付方式（alipay/wxpay/qqpay）；null 则用后台默认。
     *
     * @return array{0: string, 1: string}
     *
     * @throws \RuntimeException
     */
    public static function createPayment(string $orderNo, string $title, float $amountCny, string $clientIp, string $notifyUrl, string $returnUrl, ?string $type = null): array
    {
        if (! self::configured()) {
            throw new \RuntimeException('码支付未配置，请填写网关 / PID / 密钥。');
        }

        $key = (string) SiteSettings::get(self::S_KEY);
        $data = [
            'pid' => (string) SiteSettings::get(self::S_PID),
            'type' => self::resolveType($type),
            'out_trade_no' => $orderNo,
            'notify_url' => $notifyUrl,
            'return_url' => $returnUrl,
            'name' => mb_substr($title, 0, 127),
            'money' => number_format($amountCny, 2, '.', ''),
            'clientip' => $clientIp,
            'device' => 'pc',
            'param' => '',
        ];
        $data['sign'] = self::generateSign($data, $key);
        $data['sign_type'] = 'MD5';

        $url = self::gateway().'/mapi.php';
        self::assertAllowed($url);

        $response = Http::asForm()->timeout(20)->post($url, $data);
        $result = $response->json() ?? [];

        if ((int) ($result['code'] ?? 0) !== 1) {
            throw new \RuntimeException('码支付下单失败：'.($result['msg'] ?? $response->status()));
        }

        // payurl / qrcode / urlscheme 只返回一个；pc 端优先 payurl
        if (! empty($result['payurl'])) {
            return [(string) $result['payurl'], 'redirect'];
        }
        if (! empty($result['qrcode'])) {
            return [(string) $result['qrcode'], 'qrcode'];
        }
        if (! empty($result['urlscheme'])) {
            return [(string) $result['urlscheme'], 'redirect'];
        }

        throw new \RuntimeException('码支付未返回支付地址');
    }

    /** 异步/跳转通知验签：trade_status=TRADE_SUCCESS 为成功。 */
    public static function verifyNotify(array $params, ?string $key = null): bool
    {
        $signKey = $key ?: (string) SiteSettings::get(self::S_NOTIFY_KEY, SiteSettings::get(self::S_KEY));

        if (! isset($params['sign'], $params['trade_status'])) {
            return false;
        }

        if (! hash_equals(self::generateSign($params, $signKey), (string) $params['sign'])) {
            return false;
        }

        return strtoupper((string) $params['trade_status']) === 'TRADE_SUCCESS';
    }

    private static function assertAllowed(string $url): void
    {
        // 码支付为自部署/第三方域名，只校验协议与内网（不再额外限定 host 白名单）
        SafeHttpClient::assertUrlAllowed($url, []);
    }
}
