<?php

declare(strict_types=1);

namespace App\Filament\Pages\Settings;

use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Miran\Mksine\Filament\Pages\Settings\MksSettingsPage;

/**
 * 捐赠设置：后台 → 外观 → 捐赠按钮。
 * 配置后文章详情末尾自动渲染捐赠按钮（donatr.ee 等任意捐赠页链接）。
 */
class DonationSettingsPage extends MksSettingsPage
{
    protected string $view = 'mksine::filament.pages.mks-settings-page';

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
        return __('捐赠按钮');
    }

    public function getTitle(): string|\Illuminate\Contracts\Support\Htmlable
    {
        return __('捐赠按钮');
    }

    protected function settingsSchema(): array
    {
        return [
            Section::make('文章捐赠按钮')
                ->description('填写捐赠页链接后，文章详情末尾自动显示捐赠按钮；留空则不显示。')
                ->schema([
                    TextInput::make('site_donation_url')
                        ->label('捐赠页链接')
                        ->url()
                        ->maxLength(500)
                        ->placeholder('https://donatr.ee/yourname')
                        ->helperText('支持 donatr.ee / 爱发电 / Ko-fi 等任意捐赠页'),
                    TextInput::make('site_donation_text')
                        ->label('按钮文案')
                        ->maxLength(50)
                        ->placeholder('Donate to aniok')
                        ->default('支持作者'),
                ])
                ->columns(1),
        ];
    }
}
