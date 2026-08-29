<?php

declare(strict_types=1);

namespace Modules\User;

use Miran\Mksine\Core\Hooks\Hooks;
use Miran\Mksine\Core\Plugins\Contracts\PluginInterface;
use Miran\Mksine\Core\Shortcodes\ShortcodeCatalogEntry;
use Modules\User\Shortcodes\UserShortcode;

class UserPlugin implements PluginInterface
{
    /**
     * Get the unique plugin identifier.
     */
    public function id(): string
    {
        return 'user';
    }

    /**
     * Called when plugin is first installed.
     */
    public function install(): void
    {
        // 迁移由 mks-plugin:migrate 执行；此处可写初始数据
    }

    /**
     * Called when plugin is activated.
     */
    public function activate(): void
    {
        // 依赖自校验：user 是基础模块，无硬依赖
        // 短码等运行时注册放 boot()
    }

    /**
     * Called when plugin is deactivated.
     */
    public function deactivate(): void
    {
        // Unregister hooks, disable features
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
        // 注册模块视图命名空间 user::（底座不自动注册插件视图）
        $viewsPath = $this->viewsPath();
        if ($viewsPath && is_dir($viewsPath)) {
            view()->addNamespace('user', $viewsPath);
        }

        // 注册短码（运行时注册，每请求生效；带目录）
        Hooks::addShortcode('user', UserShortcode::class, priority: 10, catalog: new ShortcodeCatalogEntry(
            tag: 'user',
            label: '用户数据',
            description: '输出当前用户或指定用户的资料字段',
            example: '[user field="name"]',
        ));
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
        return 'Modules\User';
    }
}