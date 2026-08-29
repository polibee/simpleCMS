<?php

declare(strict_types=1);

namespace Modules\Ads;

use Miran\Mksine\Core\Plugins\Contracts\PluginInterface;

class AdsPlugin implements PluginInterface
{
    public function id(): string
    {
        return 'ads';
    }

    public function install(): void {}

    public function activate(): void
    {
        // 依赖自校验：ads 依赖 cms（广告位注入 CMS 页面）
        if (! module_enabled('cms')) {
            throw new \RuntimeException('模块 ads 依赖 cms，请先安装并启用 cms 模块。');
        }
    }

    public function deactivate(): void {}

    public function uninstall(bool $deleteData = false): void {}

    public function boot(): void
    {
        // 注册模块翻译命名空间（多语言）
        \Illuminate\Support\Facades\Lang::addNamespace('ads', __DIR__.'/../resources/lang');

        // 侧边栏广告卡位（ADR-010 系统级 Sidebar 引擎）
        \App\Core\Sidebar\Sidebar::registerType(new \Modules\Ads\Sidebar\AdSlotType());
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
        return null;
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
        return 'Modules\\Ads';
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
