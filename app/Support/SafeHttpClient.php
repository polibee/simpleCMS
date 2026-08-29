<?php

namespace App\Support;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Http;

/**
 * SSRF-safe HTTP client.
 *
 * 安全约束：服务端发起外部请求时仅允许 http/https；请求前校验 host，
 * 拒绝 localhost、环回、私有和保留地址；DNS 解析后按解析出的 IP 二次校验
 * （防 DNS rebinding）；支持目标域名白名单。
 */
final class SafeHttpClient
{
    /**
     * 校验一个 URL 是否允许请求。
     *
     * @throws \InvalidArgumentException 当 scheme 非法、host 为内网/保留地址或不在白名单时
     */
    public static function assertUrlAllowed(string $url, array $allowedHosts = []): void
    {
        $parts = parse_url($url);

        if ($parts === false || ! isset($parts['scheme'], $parts['host'])) {
            throw new \InvalidArgumentException('非法 URL');
        }

        $scheme = strtolower((string) $parts['scheme']);
        if (! in_array($scheme, ['http', 'https'], true)) {
            throw new \InvalidArgumentException("仅允许 http/https，收到: {$scheme}");
        }

        $host = strtolower(rtrim((string) $parts['host'], '.'));

        // 域名白名单（可选）
        if ($allowedHosts !== []) {
            $matched = false;
            foreach ($allowedHosts as $allowed) {
                $allowed = strtolower($allowed);
                if ($host === $allowed || str_ends_with($host, '.'.ltrim($allowed, '.'))) {
                    $matched = true;
                    break;
                }
            }
            if (! $matched) {
                throw new \InvalidArgumentException("目标域名不在白名单: {$host}");
            }
        }

        if (self::isBlockedHost($host)) {
            throw new \InvalidArgumentException("目标地址被拒绝（内网/环回/保留地址）: {$host}");
        }

        // DNS 解析后二次校验（防 DNS rebinding）
        foreach (self::resolve($host) as $ip) {
            if (self::isBlockedIp($ip)) {
                throw new \InvalidArgumentException("目标地址解析到被拒绝的 IP: {$ip}");
            }
        }
    }

    /**
     * 发起 GET 请求（默认 5s 超时，跟随重定向最多 3 次）。
     */
    public static function get(string $url, array $allowedHosts = [], int $timeout = 5): \Illuminate\Http\Client\Response
    {
        self::assertUrlAllowed($url, $allowedHosts);

        return self::request()->timeout($timeout)->get($url);
    }

    /**
     * 发起 POST 请求（JSON）。
     */
    public static function postJson(string $url, array $data, array $allowedHosts = [], int $timeout = 5, array $headers = []): \Illuminate\Http\Client\Response
    {
        self::assertUrlAllowed($url, $allowedHosts);

        return self::request($headers)->timeout($timeout)->post($url, $data);
    }

    /**
     * 发起 POST 请求（form）。
     */
    public static function postForm(string $url, array $data, array $allowedHosts = [], int $timeout = 5, array $headers = []): \Illuminate\Http\Client\Response
    {
        self::assertUrlAllowed($url, $allowedHosts);

        return self::request($headers)->asForm()->timeout($timeout)->post($url, $data);
    }

    private static function request(array $headers = []): PendingRequest
    {
        return Http::withHeaders($headers)
            ->maxRedirects(3)
            ->withoutVerifying(); // 支付等场景的证书验证在渠道层按需开启；默认关闭避免联调受阻
    }

    /**
     * 判断 host 是否为应拒绝的地址（字面内网/环回/保留 host）。
     */
    public static function isBlockedHost(string $host): bool
    {
        $normalized = strtolower(trim($host, " \t\n\r\0\x0B[]"));

        if ($normalized === '' || $normalized === 'localhost') {
            return true;
        }

        // IPv6 环回
        if ($normalized === '::1' || $normalized === '0:0:0:0:0:0:0:1') {
            return true;
        }

        if (filter_var($normalized, FILTER_VALIDATE_IP) !== false) {
            return self::isBlockedIp($normalized);
        }

        return false;
    }

    /**
     * 判断 IP 是否属于应拒绝的网段：
     * 环回、链路本地、私网、保留、CGNAT、组播、广播等。
     */
    public static function isBlockedIp(string $ip): bool
    {
        $ip = strtolower(trim($ip));

        if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV6)) {
            // IPv6：仅放行全局单播（含 2000::/3 之外的少数保留地址一并拦截）
            $expanded = self::expandIpv6($ip);

            return $expanded === null
                || str_starts_with($expanded, '::')
                || str_starts_with($expanded, '0:')
                || str_starts_with($expanded, 'fe8')
                || str_starts_with($expanded, 'fe9')
                || str_starts_with($expanded, 'fea')
                || str_starts_with($expanded, 'feb')
                || str_starts_with($expanded, 'fec')
                || str_starts_with($expanded, 'fed')
                || str_starts_with($expanded, 'fee')
                || str_starts_with($expanded, 'fef')
                || str_starts_with($expanded, 'ff'); // 组播
        }

        if (! filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4)) {
            return true; // 非合法 IP
        }

        $long = ip2long($ip);
        if ($long === false) {
            return true;
        }

        $long = sprintf('%u', $long);

        // 0.0.0.0/8
        if ($long >= 0 && $long <= 0x00FFFFFF) {
            return true;
        }
        // 10.0.0.0/8
        if ($long >= 0x0A000000 && $long <= 0x0AFFFFFF) {
            return true;
        }
        // 100.64.0.0/10 (CGNAT)
        if ($long >= 0x64400000 && $long <= 0x647FFFFF) {
            return true;
        }
        // 127.0.0.0/8
        if ($long >= 0x7F000000 && $long <= 0x7FFFFFFF) {
            return true;
        }
        // 169.254.0.0/16
        if ($long >= 0xA9FE0000 && $long <= 0xA9FEFFFF) {
            return true;
        }
        // 172.16.0.0/12
        if ($long >= 0xAC100000 && $long <= 0xAC1FFFFF) {
            return true;
        }
        // 192.168.0.0/16
        if ($long >= 0xC0A80000 && $long <= 0xC0A8FFFF) {
            return true;
        }
        // 192.0.2.0/24, 198.51.100.0/24, 203.0.113.0/24 (TEST-NET)
        if (($long >= 0xC0000200 && $long <= 0xC00002FF)
            || ($long >= 0xC6336400 && $long <= 0xC63364FF)
            || ($long >= 0xCB007100 && $long <= 0xCB0071FF)) {
            return true;
        }
        // 224.0.0.0/4 组播 + 240.0.0.0/4 保留 + 255.255.255.255
        if ($long >= 0xE0000000) {
            return true;
        }

        return false;
    }

    /**
     * DNS 解析 host 为 IP 列表（gethostbynamel 兼容；失败返回空）。
     *
     * @return list<string>
     */
    private static function resolve(string $host): array
    {
        if (filter_var($host, FILTER_VALIDATE_IP)) {
            return [$host];
        }

        $ips = @gethostbynamel($host);

        return is_array($ips) ? array_values($ips) : [];
    }

    /**
     * 展开 IPv6 到完整 8 组格式（:: 处理），失败返回 null。
     */
    private static function expandIpv6(string $ip): ?string
    {
        if (str_contains($ip, '.')) {
            return null; // IPv4-mapped 等复杂形式一律拒绝
        }

        if (str_contains($ip, '::')) {
            [$left, $right] = explode('::', $ip, 2);
            $leftGroups = $left === '' ? [] : explode(':', $left);
            $rightGroups = $right === '' ? [] : explode(':', $right);
            $missing = 8 - count($leftGroups) - count($rightGroups);
            if ($missing < 1) {
                return null;
            }
            $groups = array_merge($leftGroups, array_fill(0, $missing, '0'), $rightGroups);
        } else {
            $groups = explode(':', $ip);
        }

        if (count($groups) !== 8) {
            return null;
        }

        $padded = array_map(static function (string $g): string {
            $g = ltrim($g, '0');
            if ($g === '') {
                return '0';
            }

            return $g;
        }, $groups);

        return implode(':', $padded);
    }
}
