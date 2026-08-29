<?php

namespace App\Providers;

use App\Core\Sidebar\SidebarManager;
use App\Core\Sidebar\Types\HtmlType;
use App\Core\Sidebar\Types\ImageLinkType;
use App\Core\Sidebar\Types\TextLinkType;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        // 系统级侧边栏引擎（ADR-010）
        $this->app->singleton(SidebarManager::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // super_admin 绕过全部权限策略（Shield 惯例）：后台用户列表等资源直接可见
        \Illuminate\Support\Facades\Gate::before(function ($user, $ability) {
            if (method_exists($user, 'hasRole') && $user->hasRole('super_admin')) {
                return true;
            }

            return null;
        });

        // Horizon 仪表盘访问授权
        \Illuminate\Support\Facades\Gate::define('viewHorizon', function ($user = null) {
            return $user && method_exists($user, 'hasRole') && $user->hasRole('super_admin');
        });

        // 注册系统内置卡片类型（任何区域可用；模块特色类型由各模块 boot() 自行注册）
        $sidebar = app(SidebarManager::class);
        $sidebar->registerType(new ImageLinkType);
        $sidebar->registerType(new TextLinkType);
        $sidebar->registerType(new HtmlType);

        // 注册前台导航菜单位置（后台"菜单"可配置并分配菜单）
        app(\Miran\Mksine\Core\Hooks\MenuLocationManager::class)->registerLocations([
            'header' => '页眉导航',
            'footer' => '页脚导航',
        ]);

        // 模块翻译命名空间：必须在 Filament 面板构建前注册（此处早于 plugin boot）
        foreach (['shop', 'ads', 'invite', 'performance', 'cms', 'user'] as $module) {
            $langPath = base_path("modules/{$module}/resources/lang");
            if (is_dir($langPath)) {
                \Illuminate\Support\Facades\Lang::addNamespace($module, $langPath);
            }
        }

        // GA 后台报表：设置页配置的数字 Property ID 运行时注入 spatie/laravel-analytics
        // （直查不进静态缓存：测试进程内 boot 早于用例写库，避免读到过期快照）
        try {
            if (\Illuminate\Support\Facades\Schema::hasTable('settings')) {
                $settingRows = \Miran\Mksine\Models\Setting::query()
                    ->whereIn('key', ['ga_property_number', 'ga_service_account_json', 'mail_driver', 'smtp_host', 'smtp_port', 'smtp_username',
                        'smtp_password', 'smtp_encryption', 'resend_api_key', 'mail_from_address', 'mail_from_name',
                        'performance_cache_store', 'queue_connection'])
                    ->pluck('value', 'key');

                if (($settingRows['ga_property_number'] ?? null) !== null && $settingRows['ga_property_number'] !== '') {
                    config(['analytics.property_id' => $settingRows['ga_property_number']]);
                }

                // GA 服务账号 JSON（站点设置粘贴）→ 写入凭据文件供 spatie/laravel-analytics 使用
                $gaJson = trim((string) (\Miran\Mksine\Models\Setting::query()->where('key', 'ga_service_account_json')->value('value') ?? ''));
                if ($gaJson !== '' && str_starts_with($gaJson, '{')) {
                    $credDir = storage_path('app/analytics');
                    if (! is_dir($credDir)) {
                        @mkdir($credDir, 0700, true);
                    }
                    $credFile = $credDir.'/service-account-credentials.json';
                    // 内容变化时才重写（减少 IO）
                    if (! is_file($credFile) || md5_file($credFile) !== md5($gaJson)) {
                        @file_put_contents($credFile, $gaJson);
                    }
                }

                // 性能优化：页面缓存存储（auto = Redis 可达自动用）与队列驱动
                $storeSetting = $settingRows['performance_cache_store'] ?? 'auto';
                if ($storeSetting === 'redis' || ($storeSetting === 'auto' && \Modules\Performance\Services\SystemProbe::redisAvailable())) {
                    config(['cache.default' => 'redis']);
                }

                $queue = $settingRows['queue_connection'] ?? null;
                if (in_array($queue, ['sync', 'database', 'redis'], true)) {
                    config(['queue.default' => $queue]);
                }

                // 邮件驱动运行时切换（log / smtp / resend），后台"站点设置→邮件服务"控制
                $driver = $settingRows['mail_driver'] ?? null;
                if (in_array($driver, ['log', 'smtp', 'resend'], true)) {
                    config(['mail.default' => $driver]);

                    if ($driver === 'smtp') {
                        config([
                            'mail.mailers.smtp.host' => $settingRows['smtp_host'] ?? 'localhost',
                            'mail.mailers.smtp.port' => (int) ($settingRows['smtp_port'] ?? 587),
                            'mail.mailers.smtp.username' => $settingRows['smtp_username'],
                            'mail.mailers.smtp.password' => $settingRows['smtp_password'],
                            'mail.mailers.smtp.encryption' => $settingRows['smtp_encryption'] ?: 'tls',
                        ]);
                    }

                    if ($driver === 'resend') {
                        config(['services.resend.key' => $settingRows['resend_api_key']]);
                    }

                    if ($settingRows['mail_from_address'] ?? false) {
                        config([
                            'mail.from.address' => $settingRows['mail_from_address'],
                            'mail.from.name' => $settingRows['mail_from_name'] ?? config('app.name'),
                        ]);
                    }
                }
            }
        } catch (\Throwable) {
            // 表未就绪（迁移期）跳过
        }
    }
}
