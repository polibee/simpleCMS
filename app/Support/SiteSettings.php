<?php

namespace App\Support;

use Illuminate\Support\Facades\Schema;

/**
 * 站点级设置读取器（后台"站点设置"页写 settings 表，前台/中间件读取）。
 * 每请求静态缓存；表未就绪时安全降级。
 */
final class SiteSettings
{
    /** @var array<string, string|null>|null */
    private static ?array $cache = null;

    public static function get(string $key, ?string $default = null): ?string
    {
        $all = self::all();

        $value = $all[$key] ?? null;

        return ($value === null || $value === '') ? $default : $value;
    }

    public static function bool(string $key): bool
    {
        return self::get($key) === '1';
    }

    /**
     * SEO 共享 props：站点标题/描述/关键词（后台"站点设置→SEO"）。
     *
     * @return array{title: ?string, description: ?string, keywords: ?string}
     */
    public static function seo(): array
    {
        return [
            'title' => self::get('site_seo_title'),
            'description' => self::get('site_seo_description'),
            'keywords' => self::get('site_seo_keywords'),
        ];
    }

    /** @return array<string, string|null> */
    private static function all(): array
    {
        if (self::$cache !== null) {
            return self::$cache;
        }

        try {
            if (! Schema::hasTable('settings')) {
                return self::$cache = [];
            }

            return self::$cache = \Miran\Mksine\Models\Setting::query()
                ->pluck('value', 'key')
                ->all();
        } catch (\Throwable) {
            return self::$cache = [];
        }
    }

    /**
     * 清空快照缓存（同一进程内写库后需要刷新，如测试场景）。
     */
    public static function flush(): void
    {
        self::$cache = null;
    }
}
