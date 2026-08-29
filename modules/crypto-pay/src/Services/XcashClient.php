<?php

namespace Modules\CryptoPay\Services;

use App\Support\SafeHttpClient;
use Illuminate\Support\Str;

/**
 * Xcash 加密支付通道客户端（docs/xcash.md 协议）。
 *
 * - API 请求：HMAC-SHA256 签名头（XC-Appid / XC-Timestamp / XC-Nonce / XC-Signature）
 *   message = nonce + timestamp + raw_body；GET 无 body 用空字符串
 * - Webhook 验签：同一签名算法反向校验 + nonce 幂等由调用方保证
 *
 * 安全约束：网关地址发请求前经 SafeHttpClient 校验（仅 http/https、
 * 拒绝 localhost/环回/私有/保留地址）；官方网关 https://pay.xca.sh。
 */
final class XcashClient
{
    public const OFFICIAL_GATEWAY = 'https://pay.xca.sh';

    // settings keys
    public const S_GATEWAY = 'xcash_gateway';

    public const S_APP_ID = 'xcash_app_id';

    public const S_HMAC = 'xcash_hmac_key';

    private const ALLOWED_HOSTS = ['pay.xca.sh', 'dash.xca.sh'];

    public static function setting(string $key): ?string
    {
        $value = \Miran\Mksine\Models\Setting::query()->where('key', $key)->value('value');

        return ($value !== null && $value !== '') ? (string) $value : null;
    }

    public static function gateway(): string
    {
        return rtrim(self::setting(self::S_GATEWAY) ?: self::OFFICIAL_GATEWAY, '/');
    }

    public static function configured(): bool
    {
        return self::setting(self::S_APP_ID) !== null && self::setting(self::S_HMAC) !== null;
    }

    // ------------------------------------------------------------------
    // 签名
    // ------------------------------------------------------------------

    /**
     * 组装签名头。message = nonce + timestamp + rawBody。
     *
     * @return array{appid: string, timestamp: string, nonce: string, signature: string}
     */
    public static function sign(string $rawBody): array
    {
        $appid = (string) self::setting(self::S_APP_ID);
        $key = (string) self::setting(self::S_HMAC);
        $timestamp = (string) time();
        $nonce = (string) Str::uuid();

        $signature = hash_hmac('sha256', $nonce.$timestamp.$rawBody, $key);

        return [
            'appid' => $appid,
            'timestamp' => $timestamp,
            'nonce' => $nonce,
            'signature' => $signature,
        ];
    }

    /**
     * 验证 Xcash Webhook 签名（与 API 同算法反向校验）。
     *
     * @param  array<string, mixed>  $headers
     */
    public static function verifyWebhook(string $rawBody, array $headers): bool
    {
        $get = fn (string $name): ?string => $headers[$name] ?? $headers[strtolower($name)] ?? null;

        $appid = $get('XC-Appid');
        $nonce = $get('XC-Nonce');
        $timestamp = $get('XC-Timestamp');
        $signature = $get('XC-Signature');

        if (! $appid || ! $nonce || ! $timestamp || ! $signature) {
            return false;
        }

        // 时间戳漂移 ±300s（与官方 API 一致）
        if (abs(time() - (int) $timestamp) > 300) {
            return false;
        }

        $key = (string) self::setting(self::S_HMAC);
        $expected = hash_hmac('sha256', $nonce.$timestamp.$rawBody, $key);

        return hash_equals($expected, (string) $signature);
    }

    // ------------------------------------------------------------------
    // API
    // ------------------------------------------------------------------

    /**
     * 创建账单收款，返回 [sys_no, pay_url]。
     *
     * @return array{0: string, 1: string}
     *
     * @throws \RuntimeException 网关/凭证未配置或响应异常
     */
    public function createInvoice(
        string $outNo,
        string $title,
        string $amount,
        string $currency = 'USD',
        ?string $notifyUrl = null,
        ?string $returnUrl = null,
        int $durationMinutes = 30,
    ): array {
        if (! self::configured()) {
            throw new \RuntimeException('Xcash 未配置：请填写 AppID 与 HMAC 密钥');
        }

        $gateway = self::gateway();

        // SSRF 约束：发请求前校验网关（https、拒绝内网/保留地址；官方域白名单放行）
        SafeHttpClient::assertUrlAllowed($gateway, self::ALLOWED_HOSTS);

        $body = json_encode(array_filter([
            'out_no' => $outNo,
            'title' => mb_substr($title, 0, 32),
            'currency' => $currency,
            'amount' => $amount,
            'duration' => $durationMinutes,
            'notify_url' => $notifyUrl,
            'return_url' => $returnUrl,
        ], static fn ($v) => $v !== null), JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);

        $sig = self::sign((string) $body);
        $responseBody = self::curlRaw('POST', $gateway.'/v1/invoice', (string) $body, $sig);
        $data = json_decode($responseBody, true) ?? [];

        if (isset($data['code'])) {
            throw new \RuntimeException('Xcash 错误 '.$data['code'].': '.($data['message'] ?? '未知错误'));
        }

        $sysNo = (string) ($data['sys_no'] ?? '');
        $payUrl = (string) ($data['pay_url'] ?? '');

        if ($sysNo === '' || $payUrl === '') {
            throw new \RuntimeException('Xcash 响应缺少 sys_no/pay_url');
        }

        return [$sysNo, $payUrl];
    }

    /**
     * 查询账单公开状态（无需签名；webhook 不可达时的兜底）。
     * status: waiting / completed / expired
     */
    public function queryInvoice(string $sysNo): array
    {
        $gateway = self::gateway();
        SafeHttpClient::assertUrlAllowed($gateway, self::ALLOWED_HOSTS);

        $body = self::curlRaw('GET', $gateway.'/v1/invoice/'.rawurlencode($sysNo), '', null);

        return json_decode($body, true) ?? [];
    }

    /**
     * 发送预签名的原始请求体（curl；签名必须覆盖实际发送字节）。
     */
    private static function curlRaw(string $method, string $url, string $body, ?array $sig): string
    {
        $headers = ['Accept: application/json', 'Content-Type: application/json'];

        if ($sig !== null) {
            $headers[] = 'XC-Appid: '.$sig['appid'];
            $headers[] = 'XC-Timestamp: '.$sig['timestamp'];
            $headers[] = 'XC-Nonce: '.$sig['nonce'];
            $headers[] = 'XC-Signature: '.$sig['signature'];
        }

        $ch = curl_init();
        curl_setopt_array($ch, [
            CURLOPT_URL => $url,
            CURLOPT_CUSTOMREQUEST => $method,
            CURLOPT_HTTPHEADER => $headers,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 10,
            CURLOPT_POSTFIELDS => $body !== '' ? $body : null,
        ]);

        $response = curl_exec($ch);

        if ($response === false) {
            $err = curl_error($ch);
            curl_close($ch);

            throw new \RuntimeException('Xcash 网络错误: '.$err);
        }

        curl_close($ch);

        return (string) $response;
    }
}
