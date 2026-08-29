<?php

declare(strict_types=1);

namespace Modules\Performance\Http\Middleware;

use App\Support\SiteSettings;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Modules\Performance\Services\StatsService;
use Symfony\Component\HttpFoundation\Response;

/**
 * WP Super Cache 式整页缓存（自研引擎）：
 * - 仅缓存 游客 + GET/HEAD + 200 的 HTML 响应；登录用户永远实时渲染
 * - 排除后台/登录/创作中心/邀请码/接口等动态路径；?nocache=1 手动绕过
 * - TTL 后台可配；命中/未命中/耗时写入 performance_stats（图表数据源）
 * - 存储数组快照（body+headers），驱动 auto（Redis 可达自动用）/file/redis
 */
class PerformancePageCache
{
    private const INDEX_KEY = 'pcache:index';

    private const INDEX_LIMIT = 2000;

    /** 不参与页面缓存的前缀（动态/个性化/管理路径）。 */
    public const EXCLUDED_PREFIXES = [
        'admin', 'login', 'logout', 'register', 'studio', 'invite', 'horizon',
        'email', 'comment/captcha', 'crypto-pay', 'livewire', 'octane',
        'sitemap.xml', 'up', 'user', 'quest', 'api', 'privacy',
    ];

    public function handle(Request $request, Closure $next): Response
    {
        $start = microtime(true);
        $cacheable = $this->isCacheable($request);
        $key = $cacheable ? $this->key($request) : null;
        $store = $this->store();

        if ($key !== null && ! $request->query('nocache')) {
            $cached = Cache::store($store)->get($key);
            if (is_array($cached)) {
                StatsService::record(true, 0);

                return new Response($cached['body'], $cached['status'], $cached['headers']);
            }
        }

        $response = $next($request);

        if ($key !== null && $response->getStatusCode() === 200 && ! $request->query('nocache')) {
            $ttl = max(60, (int) SiteSettings::get('performance_page_cache_ttl', '3600'));

            Cache::store($store)->put($key, [
                'body' => $response->getContent(),
                'status' => $response->getStatusCode(),
                'headers' => $response->headers->all(),
            ], $ttl);

            $this->trackIndex($key, $store);

            StatsService::record(false, (int) round((microtime(true) - $start) * 1000));
        }

        return $response;
    }

    /** 是否参与缓存（WP Super Cache 的 should-cache 判定）。 */
    public function isCacheable(Request $request): bool
    {
        if (! SiteSettings::bool('performance_page_cache_enabled')) {
            return false;
        }

        if (! $request->isMethodCacheable() || $request->ajax() || $request->pjax()) {
            return false;
        }

        if ($request->user()) {
            return false;
        }

        // Inertia 部分刷新/JSON 请求不能与 HTML 页共用缓存
        if ($request->headers->has('X-Inertia')) {
            return false;
        }

        if ($request->query('nocache') !== null) {
            return false;
        }

        $path = trim($request->path(), '/');
        if ($path === '') {
            return true; // 首页
        }

        foreach (self::EXCLUDED_PREFIXES as $prefix) {
            if ($path === $prefix || str_starts_with($path, $prefix.'/')) {
                return false;
            }
        }

        return true;
    }

    public function key(Request $request): string
    {
        return 'pcache:'.hash('sha256', 'GET|'.$request->getSchemeAndHttpHost().$request->getRequestUri());
    }

    /** 当前缓存存储（auto = Redis 可达时优先）。 */
    public function store(): string
    {
        $setting = SiteSettings::get('performance_cache_store', 'auto');

        if ($setting === 'redis') {
            return 'redis';
        }

        if ($setting === 'auto' && \Modules\Performance\Services\SystemProbe::redisAvailable()) {
            return 'redis';
        }

        return 'file';
    }

    /** 清空全部页面缓存（后台按钮）。 */
    public static function clearAll(): void
    {
        $self = new self();
        $store = $self->store();

        foreach ((array) Cache::store($store)->get(self::INDEX_KEY, []) as $key) {
            Cache::store($store)->forget($key);
        }

        Cache::store($store)->forget(self::INDEX_KEY);
    }

    /** 记录活跃缓存键，供 clearAll 精确清除。 */
    private function trackIndex(string $key, string $store): void
    {
        $index = (array) Cache::store($store)->get(self::INDEX_KEY, []);
        $index[] = $key;

        Cache::store($store)->put(self::INDEX_KEY, array_values(array_unique(array_slice($index, -self::INDEX_LIMIT))), now()->addDays(7));
    }
}
