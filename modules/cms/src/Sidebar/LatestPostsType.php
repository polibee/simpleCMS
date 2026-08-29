<?php

namespace Modules\CMS\Sidebar;

use App\Core\Sidebar\CardTypeContract;
use Filament\Forms\Components\TextInput;
use Illuminate\Contracts\Auth\Authenticatable;

/**
 * CMS 特色类型：最新文章卡。
 */
class LatestPostsType implements CardTypeContract
{
    public function typeKey(): string
    {
        return 'latest_posts';
    }

    public function label(): string
    {
        return '最新文章';
    }

    public function schema(): array
    {
        return [
            TextInput::make('data.limit')
                ->label('显示条数')
                ->numeric()->minValue(1)->maxValue(20)->default(5),
        ];
    }

    public function componentKey(): string
    {
        return 'latest_posts';
    }

    public function authorize(Authenticatable $user): bool
    {
        return true;
    }
}
