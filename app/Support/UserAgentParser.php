<?php

namespace App\Support;

/**
 * 轻量 User-Agent 解析器：从 UA 字符串识别操作系统、浏览器、设备类型。
 * 仅用于评论展示场景的追踪识别；不追求全量覆盖。
 */
final class UserAgentParser
{
    /**
     * @return array{os: string, browser: string, device: string}
     */
    public static function parse(?string $userAgent): array
    {
        $ua = $userAgent ?? '';

        return [
            'os' => self::parseOs($ua),
            'browser' => self::parseBrowser($ua),
            'device' => self::parseDevice($ua),
        ];
    }

    private static function parseOs(string $ua): string
    {
        return match (true) {
            stripos($ua, 'Windows NT 10') !== false || stripos($ua, 'Windows NT 11') !== false => 'Windows 10/11',
            stripos($ua, 'Windows NT 6.3') !== false => 'Windows 8.1',
            stripos($ua, 'Windows NT 6.1') !== false => 'Windows 7',
            stripos($ua, 'Windows') !== false => 'Windows',
            stripos($ua, 'iPhone') !== false || stripos($ua, 'iPad') !== false => 'iOS',
            stripos($ua, 'Mac OS X') !== false || stripos($ua, 'Macintosh') !== false => 'macOS',
            stripos($ua, 'Android') !== false => 'Android',
            stripos($ua, 'Linux') !== false => 'Linux',
            default => '未知系统',
        };
    }

    private static function parseBrowser(string $ua): string
    {
        // 顺序敏感：先匹配套壳/派生，再匹配内核
        return match (true) {
            stripos($ua, 'MicroMessenger') !== false => '微信内置浏览器',
            stripos($ua, 'Edg/') !== false || stripos($ua, 'Edge') !== false => 'Edge',
            stripos($ua, 'QQBrowser') !== false => 'QQ 浏览器',
            stripos($ua, 'MetaSr') !== false || stripos($ua, 'SE ') !== false => '搜狗浏览器',
            stripos($ua, 'BaiduBrowser') !== false || stripos($ua, 'BIDUBrowser') !== false => '百度浏览器',
            stripos($ua, 'UCBrowser') !== false => 'UC 浏览器',
            stripos($ua, 'Quark') !== false => '夸克浏览器',
            stripos($ua, 'Opera') !== false || stripos($ua, 'OPR/') !== false => 'Opera',
            stripos($ua, 'Firefox') !== false || stripos($ua, 'FxiOS') !== false => 'Firefox',
            stripos($ua, 'Chrome') !== false || stripos($ua, 'CriOS') !== false => 'Chrome',
            stripos($ua, 'Safari') !== false => 'Safari',
            default => '其他浏览器',
        };
    }

    private static function parseDevice(string $ua): string
    {
        if (stripos($ua, 'iPad') !== false) {
            return '平板';
        }
        if (stripos($ua, 'Mobile') !== false || stripos($ua, 'iPhone') !== false || stripos($ua, 'Android') !== false) {
            return '手机';
        }

        return '电脑';
    }
}
