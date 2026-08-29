<?php

namespace Modules\CMS\Sidebar;

use App\Core\Sidebar\CardTypeContract;
use Filament\Forms\Components\TextInput;
use Illuminate\Contracts\Auth\Authenticatable;

/**
 * CMS 特色类型：捐赠卡片（侧边栏）。
 * 链接在卡片实例的 data 里配置（与文章末尾自动按钮的站点级配置独立）。
 */
class DonationType implements CardTypeContract
{
    public function typeKey(): string
    {
        return 'donation';
    }

    public function label(): string
    {
        return '捐赠支持';
    }

    public function schema(): array
    {
        return [
            TextInput::make('data.url')
                ->label('捐赠页链接')
                ->url()
                ->required()
                ->maxLength(500)
                ->helperText('donatr.ee / 爱发电 / Ko-fi 等任意捐赠页'),
            TextInput::make('data.button_text')
                ->label('按钮文案')
                ->maxLength(50)
                ->default('支持作者'),
            TextInput::make('data.description')
                ->label('说明文案')
                ->maxLength(100)
                ->default('你的支持是创作的动力'),
        ];
    }

    public function componentKey(): string
    {
        return 'donation';
    }

    public function authorize(Authenticatable $user): bool
    {
        return true;
    }
}
