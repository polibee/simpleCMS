<?php

declare(strict_types=1);

namespace App\Filament\Pages;

use App\Support\PluginPackager;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Illuminate\Contracts\Support\Htmlable;

/**
 * 插件打包下载（后台 → 插件打包）：把已发现的插件目录压缩为 zip 分发包。
 * 独立于底座「插件管理」页，避免改动 vendor；导航同为顶级便于并列。
 */
class PluginPackagerPage extends Page
{
    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-archive-box-arrow-down';

    protected string $view = 'filament.pages.plugin-packager';

    public static function getSlug(?\Filament\Panel $panel = null): string
    {
        return 'plugin-packager';
    }

    protected static ?int $navigationSort = 3;

    public ?string $pluginId = null;

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
        return __('插件打包');
    }

    public static function getNavigationGroup(): ?string
    {
        return null; // 与底座"插件管理"同级并列
    }

    public function getTitle(): string|Htmlable
    {
        return __('插件打包下载');
    }

    /**
     * 已发现插件选项：[id => "{id} v{version} — {name}（status）"]
     */
    public function pluginOptions(): array
    {
        try {
            return collect(PluginPackager::discoverable())
                ->mapWithKeys(fn ($p) => [$p['id'] => "{$p['id']} v{$p['version']} — {$p['name']}（{$p['status']}）"])
                ->all();
        } catch (\Throwable) {
            return [];
        }
    }

    public function download(): \Symfony\Component\HttpFoundation\RedirectResponse
    {
        if (! $this->pluginId) {
            Notification::make()->title('请先选择要打包的插件')->warning()->send();

            return redirect()->back();
        }

        try {
            // 预打包：错误在此处暴露为通知；正式下载走独立 GET 路由（浏览器原生附件下载）
            PluginPackager::package($this->pluginId);

            Notification::make()
                ->title('打包完成，开始下载…')
                ->body($this->pluginId.' 已生成 zip 分发包')
                ->success()
                ->send();

            return redirect()->to(route('plugin-packager.download', ['pluginId' => $this->pluginId]));
        } catch (\Throwable $e) {
            Notification::make()
                ->title('打包失败')
                ->body($e->getMessage())
                ->danger()
                ->send();

            return redirect()->back();
        }
    }
}
