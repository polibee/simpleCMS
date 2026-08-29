<?php

declare(strict_types=1);

namespace Modules\CMS;

use Miran\Mksine\Core\Plugins\Contracts\PluginInterface;

class CmsPlugin implements PluginInterface
{
    /**
     * Get the unique plugin identifier.
     */
    public function id(): string
    {
        return 'cms';
    }

    /**
     * Called when plugin is first installed.
     */
    public function install(): void
    {
        // Run migrations, create initial data, etc.
    }

    /**
     * Called when plugin is activated.
     */
    public function activate(): void
    {
        // 依赖自校验：cms 依赖 user
        if (! module_enabled('user')) {
            throw new \RuntimeException('模块 cms 依赖 user，请先安装并启用 user 模块。');
        }

        // WordPress 式固定链接迁移（一次性）：把底座默认文章路由挪到 legacy 路径，
        // 让位给 CMS 可自定义的文章地址（后台 → 设置集群 → CMS 固定链接）。
        try {
            if (\Illuminate\Support\Facades\Schema::hasTable('settings')) {
                $migrated = false;

                $baseSingle = \Miran\Mksine\Models\Setting::query()->where('key', 'single_post_url')->value('value');
                if ($baseSingle === null || $baseSingle === '/post/{slug}') {
                    \Miran\Mksine\Models\Setting::updateOrCreate(
                        ['key' => 'single_post_url'],
                        ['value' => '/mksine-legacy/post/{slug}'],
                    );
                    $migrated = true;
                }

                $basePosts = \Miran\Mksine\Models\Setting::query()->where('key', 'posts_url')->value('value');
                if ($basePosts === null || $basePosts === '/posts') {
                    \Miran\Mksine\Models\Setting::updateOrCreate(
                        ['key' => 'posts_url'],
                        ['value' => '/mksine-legacy/posts'],
                    );
                    $migrated = true;
                }

                if ($migrated) {
                    \Illuminate\Support\Facades\Artisan::call('route:clear');
                }
            }
        } catch (\Throwable) {
            // 表未就绪时跳过；下次激活重试
        }

        // 区域/类型注册放 boot()（每请求生效）
        \Illuminate\Support\Facades\Cache::forget('sidebar.cards.cms.sidebar');
    }

    /**
     * Called when plugin is deactivated.
     */
    public function deactivate(): void
    {
        // Note: Data should NOT be deleted here
    }

    /**
     * Called when plugin is uninstalled.
     */
    public function uninstall(bool $deleteData = false): void
    {
        if ($deleteData) {
            // Delete tables, files, etc.
        }
    }

    /**
     * Called on every request when plugin is active.
     */
    public function boot(): void
    {
        // ★ 向系统级 Sidebar 引擎注册本模块的区域与特色卡片类型（ADR-010）
        \App\Core\Sidebar\Sidebar::registerArea('cms.sidebar', 'CMS 侧边栏', 'cms');
        \App\Core\Sidebar\Sidebar::registerType(new \Modules\CMS\Sidebar\CategoriesType());
        \App\Core\Sidebar\Sidebar::registerType(new \Modules\CMS\Sidebar\LatestPostsType());
        \App\Core\Sidebar\Sidebar::registerType(new \Modules\CMS\Sidebar\CheckinType());
        \App\Core\Sidebar\Sidebar::registerType(new \Modules\CMS\Sidebar\UserCenterType());
        \App\Core\Sidebar\Sidebar::registerType(new \Modules\CMS\Sidebar\AuthorCenterType());
        \App\Core\Sidebar\Sidebar::registerType(new \Modules\CMS\Sidebar\DonationType());

        // 发布事件桥接：底座 post.created(published) → cms.article.published
        // （Quest/AI/Search 监听统一域事件，不感知底座细节）
        \Illuminate\Support\Facades\Event::listen(
            \Miran\Mksine\Core\Events\Posts\PostCreated::class,
            \Modules\CMS\Listeners\BridgesArticlePublished::class,
        );

        // 模块视图命名空间（Blade 兜底视图）
        $viewsPath = $this->viewsPath();
        if ($viewsPath && is_dir($viewsPath)) {
            view()->addNamespace('cms', $viewsPath);
        }
    }

    /**
     * Get the plugin's migrations path.
     */
    public function migrationsPath(): ?string
    {
        return __DIR__ . '/../database/migrations';
    }

    /**
     * Get the plugin's config path.
     */
    public function configPath(): ?string
    {
        return __DIR__ . '/../config';
    }

    /**
     * Get the plugin's views path.
     */
    public function viewsPath(): ?string
    {
        return __DIR__ . '/../resources/views';
    }

    /**
     * Get the plugin's web routes path.
     */
    public function webRoutesPath(): ?string
    {
        return __DIR__ . '/../routes/web.php';
    }

    /**
     * Get the plugin's API routes path.
     */
    public function apiRoutesPath(): ?string
    {
        return __DIR__ . '/../routes/api.php';
    }

    /**
     * Get the plugin's translations path.
     */
    public function translationsPath(): ?string
    {
        return __DIR__ . '/../resources/lang';
    }

    /**
     * Get the plugin's Filament resources path.
     */
    public function filamentResourcesPath(): ?string
    {
        $path = __DIR__ . '/Filament/Resources';
        return is_dir($path) ? $path : null;
    }

    /**
     * Get the plugin's Filament pages path.
     */
    public function filamentPagesPath(): ?string
    {
        $path = __DIR__ . '/Filament/Pages';
        return is_dir($path) ? $path : null;
    }

    /**
     * Get the plugin's Filament widgets path.
     */
    public function filamentWidgetsPath(): ?string
    {
        $path = __DIR__ . '/Filament/Widgets';
        return is_dir($path) ? $path : null;
    }

    /**
     * Get the plugin's namespace.
     */
    public function namespace(): ?string
    {
        return 'Modules\CMS';
    }
}