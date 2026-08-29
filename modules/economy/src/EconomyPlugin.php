<?php

declare(strict_types=1);

namespace Modules\Economy;

use Miran\Mksine\Core\Hooks\Hooks;
use Miran\Mksine\Core\Plugins\Contracts\PluginInterface;
use Miran\Mksine\Core\Shortcodes\ShortcodeCatalogEntry;
use Modules\Economy\Services\WalletService;
use Modules\Economy\Shortcodes\WalletBalanceShortcode;

class EconomyPlugin implements PluginInterface
{
    /**
     * Get the unique plugin identifier.
     */
    public function id(): string
    {
        return 'economy';
    }

    /**
     * Called when plugin is first installed.
     */
    public function install(): void
    {
        // 迁移由 mks-plugin:migrate 执行；此处写初始货币配置。
        // 注意：install 可能在迁移前被调用，用 Schema::hasTable 守卫。
        if (! \Illuminate\Support\Facades\Schema::hasTable('economy_currency_configs')) {
            return;
        }

        $defaults = [
            ['key' => 'gold', 'name' => '金币', 'unit' => 1],
            ['key' => 'silver', 'name' => '银币', 'unit' => 0.1],
            ['key' => 'copper', 'name' => '铜币', 'unit' => 0.01],
        ];

        foreach ($defaults as $cfg) {
            \Modules\Economy\Models\EconomyCurrencyConfig::query()->firstOrCreate(
                ['key' => $cfg['key']],
                $cfg,
            );
        }
    }

    /**
     * Called when plugin is activated.
     */
    public function activate(): void
    {
        // 依赖自校验：economy 依赖 user 模块
        if (! module_enabled('user')) {
            throw new \RuntimeException('模块 economy 依赖 user，请先安装并启用 user 模块。');
        }

        // 一次性副作用（初始数据等）；短码等运行时注册放 boot()
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
        // 注册钱包服务到容器（供 Economy 门面与跨模块调用）
        $this->app()->singleton(WalletService::class, fn () => new WalletService());

        // 注册短码（运行时注册，每请求生效；带目录）
        Hooks::addShortcode('wallet_balance', WalletBalanceShortcode::class, priority: 10, catalog: new ShortcodeCatalogEntry(
            tag: 'wallet_balance',
            label: '钱包余额',
            description: '输出当前用户指定货币的余额',
            example: '[wallet_balance currency="gold"]',
        ));

        // 注册模块视图命名空间
        $viewsPath = $this->viewsPath();
        if ($viewsPath && is_dir($viewsPath)) {
            view()->addNamespace('economy', $viewsPath);
        }
    }

    /**
     * 获取应用容器（插件 boot 由插件系统在应用内调用）。
     */
    private function app(): \Illuminate\Contracts\Foundation\Application
    {
        return app();
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
        return 'Modules\Economy';
    }
}