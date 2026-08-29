<?php

declare(strict_types=1);

namespace Modules\Invite;

use Illuminate\Support\Facades\Route;
use Miran\Mksine\Core\Plugins\Contracts\PluginInterface;

class InvitePlugin implements PluginInterface
{
    public function id(): string
    {
        return 'invite';
    }

    public function install(): void {}

    public function activate(): void {}

    public function deactivate(): void {}

    public function uninstall(bool $deleteData = false): void {}

    public function boot(): void
    {
        // 注册模块翻译命名空间（多语言）
        \Illuminate\Support\Facades\Lang::addNamespace('invite', __DIR__.'/../resources/lang');

        // 支付回调：boot 期裸注册（不在 web 组内 → 无 CSRF），各自验签保护
        Route::post('/invite/webhook', [\Modules\Invite\Http\Controllers\WebhookController::class, 'handle']);
        Route::match(['get', 'post'], '/invite/notify', [\Modules\Invite\Http\Controllers\InviteCenterController::class, 'notify']);
        Route::get('/invite/paypal/return', [\Modules\Invite\Http\Controllers\InviteCenterController::class, 'paypalReturn']);
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
        return __DIR__.'/../resources/views';
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
        return 'Modules\\Invite';
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
