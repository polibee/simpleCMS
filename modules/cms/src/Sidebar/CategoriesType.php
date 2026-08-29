<?php

namespace Modules\CMS\Sidebar;

use App\Core\Sidebar\CardTypeContract;
use Filament\Forms\Components\TextInput;
use Illuminate\Contracts\Auth\Authenticatable;

/**
 * CMS 特色类型：分类目录卡。
 */
class CategoriesType implements CardTypeContract
{
    public function typeKey(): string
    {
        return 'categories';
    }

    public function label(): string
    {
        return '分类目录';
    }

    public function schema(): array
    {
        return [
            TextInput::make('data.limit')
                ->label('显示条数')
                ->numeric()->minValue(1)->maxValue(20)->default(6),
        ];
    }

    public function componentKey(): string
    {
        return 'categories';
    }

    public function authorize(Authenticatable $user): bool
    {
        return true;
    }
}
