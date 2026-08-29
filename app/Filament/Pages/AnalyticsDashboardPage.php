<?php

declare(strict_types=1);

namespace App\Filament\Pages;

use App\Support\SiteSettings;
use BezhanSalleh\GoogleAnalytics\Pages\GoogleAnalyticsDashboard;
use Filament\Forms\Components\Placeholder;
use Filament\Schemas\Components\Section;
use Illuminate\Contracts\Support\Htmlable;

/**
 * Google Analytics 仪表盘（继承 vendor 页面，重定义 slug/排序/标题）。
 * 配置不完整时显示可视化引导而非底层异常。
 */
class AnalyticsDashboardPage extends GoogleAnalyticsDashboard
{
    public static function getSlug(?\Filament\Panel $panel = null): string
    {
        return 'analytics';
    }

    public static function getNavigationLabel(): string
    {
        return __('Google Analytics');
    }

    public function getTitle(): string|Htmlable
    {
        return __('Google Analytics 统计');
    }

    /** 配置未就绪时使用引导视图（替代 vendor 的空视图 + 报错 Widget）。 */
    public function getView(): string
    {
        return static::gaReady()
            ? 'google-analytics::pages.google-analytics-dashboard'
            : 'filament.pages.analytics-setup-required';
    }

    public static function getNavigationSort(): ?int
    {
        return 90;
    }

    /** GA 报表是否可正常查询（配置完整 = 数字 ID + JSON 均已填写）。 */
    public static function gaReady(): bool
    {
        $propertyNumber = SiteSettings::get('ga_property_number');
        $json = trim((string) SiteSettings::get('ga_service_account_json', ''));

        return filled($propertyNumber) && str_starts_with($json, '{')
            && is_file(storage_path('app/analytics/service-account-credentials.json'));
    }

    /**
     * 配置不完整时返回引导占位（替代会抛异常的 GA 查询 Widget）。
     */
    protected function getHeaderWidgets(): array
    {
        if (! static::gaReady()) {
            return [];
        }

        return parent::getHeaderWidgets();
    }

    protected function getFooterWidgets(): array
    {
        return [];
    }

    public function getSubheading(): string|Htmlable|null
    {
        if (! static::gaReady()) {
            return null;
        }

        return parent::getSubheading();
    }
}
