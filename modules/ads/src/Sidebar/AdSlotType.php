<?php

namespace Modules\Ads\Sidebar;

use App\Core\Sidebar\CardTypeContract;
use Filament\Forms\Components\TextInput;
use Illuminate\Contracts\Auth\Authenticatable;

/**
 * 侧边栏广告卡位：后台"侧边栏管理"添加 ad_slot 卡片，
 * 渲染"广告管理"中投放到侧边栏位置的广告。
 */
class AdSlotType implements CardTypeContract
{
    public function typeKey(): string
    {
        return 'ad_slot';
    }

    public function label(): string
    {
        return '广告卡位（Ads 插件）';
    }

    public function schema(): array
    {
        return [
            TextInput::make('data.cardTitle')
                ->label('卡片标题')
                ->maxLength(50)
                ->default('广告'),
        ];
    }

    public function componentKey(): string
    {
        return 'ad_slot';
    }

    public function authorize(Authenticatable $user): bool
    {
        return true;
    }
}
