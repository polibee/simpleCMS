<?php

declare(strict_types=1);

namespace Modules\CryptoPay;

use Illuminate\Support\Facades\Route;
use Miran\Mksine\Core\Hooks\Hooks;
use Miran\Mksine\Core\Plugins\Contracts\PluginInterface;
use Miran\Mksine\Core\Shortcodes\ShortcodeCatalogEntry;
use Modules\CryptoPay\Http\Controllers\WebhookController;
use Modules\CryptoPay\Services\CoinPayService;

class CryptoPayPlugin implements PluginInterface
{
    /**
     * Get the unique plugin identifier.
     */
    public function id(): string
    {
        return 'crypto-pay';
    }

    /**
     * Called when plugin is first installed.
     */
    public function install(): void
    {
        //
    }

    /**
     * Called when plugin is activated.
     */
    public function activate(): void
    {
        // 依赖自校验
        foreach (['user', 'cms'] as $required) {
            if (! module_enabled($required)) {
                throw new \RuntimeException("模块 crypto-pay 依赖 {$required}，请先安装并启用。");
            }
        }
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
        // Webhook 路由：裸注册（无 web 组 → 无 session/CSRF；SDK HMAC 验签即认证）
        Route::post('/crypto-pay/webhook', [WebhookController::class, 'handle'])
            ->name('crypto-pay.webhook');

        // 短码 [coinpay_buy]（带目录）
        Hooks::addShortcode('coinpay_buy', \Modules\CryptoPay\Shortcodes\CoinpayBuyShortcode::class, priority: 10, catalog: new ShortcodeCatalogEntry(
            tag: 'coinpay_buy',
            label: '加密支付购买按钮',
            description: '输出当前文章的加密货币购买按钮（付费文章自动生效，无需手动插入）',
            example: '[coinpay_buy]',
        ));

        // 模块视图命名空间（mock 收银台）
        $viewsPath = $this->viewsPath();
        if ($viewsPath && is_dir($viewsPath)) {
            view()->addNamespace('crypto-pay', $viewsPath);
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
        return 'Modules\CryptoPay';
    }
}