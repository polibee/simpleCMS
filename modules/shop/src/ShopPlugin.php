<?php

declare(strict_types=1);

namespace Modules\Shop;

use Illuminate\Support\Facades\Route;
use Miran\Mksine\Core\Plugins\Contracts\PluginInterface;

class ShopPlugin implements PluginInterface
{
    public function id(): string
    {
        return 'shop';
    }

    public function install(): void {}

    public function activate(): void {}

    public function deactivate(): void {}

    public function uninstall(bool $deleteData = false): void {}

    public function boot(): void
    {
        // 注册模块翻译命名空间（多语言）
        \Illuminate\Support\Facades\Lang::addNamespace('shop', __DIR__.'/../resources/lang');

        // Xcash 支付回调 + 码支付/虎皮椒通知：boot 期裸注册（无 CSRF），各自验签保护
        Route::post('/shop/webhook', [\Modules\Shop\Http\Controllers\WebhookController::class, 'handle']);
        Route::match(['get', 'post'], '/shop/notify', [\Modules\Shop\Http\Controllers\ShopController::class, 'notify']);
        Route::match(['get', 'post'], '/shop/notify/return', [\Modules\Shop\Http\Controllers\ShopController::class, 'notifyReturn']);
    }

    public function migrationsPath(): ?string
    {
        return __DIR__.'/../database/migrations';
    }

    public function configPath(): ?string
    {
        return null;
    }

    public function viewsPath(): ?string
    {
        return null;
    }

    public function webRoutesPath(): ?string
    {
        return __DIR__.'/../routes/web.php';
    }

    public function apiRoutesPath(): ?string
    {
        return null;
    }

    public function translationsPath(): ?string
    {
        return null;
    }

    public function namespace(): ?string
    {
        return 'Modules\\Shop';
    }

    public function filamentResourcesPath(): ?string
    {
        $path = __DIR__.'/Filament/Resources';

        return is_dir($path) ? $path : null;
    }

    public function filamentPagesPath(): ?string
    {
        return null;
    }

    public function filamentWidgetsPath(): ?string
    {
        return null;
    }
}
