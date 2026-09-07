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
        // 带任意 query 即可生成新缓存键，参与缓存会被刷爆存储（P2-3）
        'search', 'feed',
    ];

    /**
     * 允许随响应体一起缓存的响应头（白名单）。
     *
     * 绝不能缓存的头：set-cookie（会把首个游客的 session / XSRF-TOKEN 重放给
     * 所有后续访客，造成会话串号与 419）、cache-control、date、age、etag 之外的
     * 逐响应头，以及任何限速头。
     */
    private const CACHEABLE_HEADERS = [
        'content-type', 'content-language', 'content-encoding',
        'etag', 'last-modified', 'vary',
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

                $response = new Response($cached['body'], $cached['status'], $cached['headers']);
                $response->headers->set('X-Page-Cache', 'HIT');

                return $response;
            }
        }

        $response = $next($request);

        if ($key !== null && $response->getStatusCode() === 200 && ! $request->query('nocache')) {
            $ttl = max(60, (int) SiteSettings::get('performance_page_cache_ttl', '3600'));

            Cache::store($store)->put($key, [
                'body' => $response->getContent(),
                'status' => $response->getStatusCode(),
                'headers' => $this->cacheableHeaders($response),
            ], $ttl);

            $this->trackIndex($key, $store);

            StatsService::record(false, (int) round((microtime(true) - $start) * 1000));
        }

        $response->headers->set('X-Page-Cache', $key !== null ? 'MISS' : 'BYPASS');

        return $response;
    }

    /**
     * 从响应中挑出可以安全缓存的头（按白名单，避免重放 Set-Cookie）。
     *
     * @return array<string, list<string|null>>
     */
    private function cacheableHeaders(Response $response): array
    {
        $headers = [];

        foreach (self::CACHEABLE_HEADERS as $name) {
            $values = $response->headers->all($name);
            if ($values !== []) {
                $headers[$name] = $values;
            }
        }

        return $headers;
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

        // 携带 flash / 校验错误的响应是一次性的（如评论后 redirect()->back()->with(...)），
        // 一旦进缓存会把这条提示固化给后续所有访客（P2-1 缓存投毒）
        if ($request->hasSession()) {
            $session = $request->session();
            if ($session->has('success') || $session->has('error') || $session->has('errors')) {
                return false;
            }
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
        // Cookie 授权状态决定统计脚本是否注入（app.blade.php），必须参与缓存键，
        // 否则首个访客的选择会被固化给所有人，未同意者也被注入统计代码（P2-2）
        $consent = (string) $request->cookie('cookie_consent', '');

        return 'pcache:'.hash('sha256', 'GET|'.$request->getSchemeAndHttpHost().$request->getRequestUri().'|consent:'.$consent);
    }

    /**
     * 静态便捷判定：当前请求是否会被整页缓存接管。
     *
     * 供 HandleInertiaRequests 等在渲染期决定是否下发个性化数据。
     */
    public static function cacheable(Request $request): bool
    {
        try {
            return (new self)->isCacheable($request);
        } catch (\Throwable) {
            return false;
        }
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
