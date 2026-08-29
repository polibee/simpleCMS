<?php

declare(strict_types=1);

namespace App\Filament\Pages;

use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Illuminate\Contracts\Support\Htmlable;
use Modules\Performance\Services\StatsService;
use Modules\Performance\Services\SystemProbe;

/**
 * 性能优化中心（后台 → 性能优化）：
 * WP Super Cache 式页面缓存管理 + Redis / OPcache / Octane / Horizon 状态与图表。
 */
class PerformanceDashboardPage extends Page
{
    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-bolt';

    protected string $view = 'filament.pages.performance-dashboard';

    public static function getSlug(?\Filament\Panel $panel = null): string
    {
        return 'performance';
    }

    protected static ?int $navigationSort = 1;

    public static function canAccess(): bool
    {
        $user = auth()->user();

        if ($user && method_exists($user, 'hasRole') && $user->hasRole('super_admin')) {
            return true;
        }

        return parent::canAccess();
    }

    public static function getNavigationLabel(): string
    {
        return __('性能优化');
    }

    public static function getNavigationGroup(): ?string
    {
        return null;
    }

    public function getTitle(): string|Htmlable
    {
        return __('性能优化中心');
    }

    // ------------------------------------------------------------------
    // 供 Blade 渲染的数据
    // ------------------------------------------------------------------

    public function stats(): array
    {
        return StatsService::last(14);
    }

    public function today(): array
    {
        return StatsService::today();
    }

    public function redis(): array
    {
        return SystemProbe::redis();
    }

    public function opcache(): array
    {
        return SystemProbe::opcache();
    }

    public function octane(): array
    {
        return SystemProbe::octane();
    }

    public function horizon(): array
    {
        return SystemProbe::horizon();
    }

    public function pageCacheEnabled(): bool
    {
        return \App\Support\SiteSettings::bool('performance_page_cache_enabled');
    }

    public function cacheStore(): string
    {
        return (new \Modules\Performance\Http\Middleware\PerformancePageCache)->store();
    }

    public function queueConnection(): string
    {
        return (string) config('queue.default', 'database');
    }

    // ------------------------------------------------------------------
    // 操作
    // ------------------------------------------------------------------

    public function clearPageCache(): void
    {
        \Modules\Performance\Http\Middleware\PerformancePageCache::clearAll();
        Notification::make()->title('页面缓存已清除')->success()->send();
    }

    public function resetOpcache(): void
    {
        if (SystemProbe::opcacheReset()) {
            Notification::make()->title('OPcache 已重置')->success()->send();

            return;
        }

        Notification::make()->title('OPcache 不可用或重置失败')->warning()->send();
    }

    public function flushApplicationCache(): void
    {
        \Illuminate\Support\Facades\Artisan::call('cache:clear');
        Notification::make()->title('应用缓存已清空')->success()->send();
    }
}
