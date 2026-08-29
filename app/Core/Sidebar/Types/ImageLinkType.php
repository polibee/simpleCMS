<?php

namespace App\Core\Sidebar\Types;

use App\Core\Sidebar\CardTypeContract;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Illuminate\Contracts\Auth\Authenticatable;

/**
 * 内置类型：图片超链接卡片。
 */
class ImageLinkType implements CardTypeContract
{
    public function typeKey(): string
    {
        return 'image_link';
    }

    public function label(): string
    {
        return '图片超链接';
    }

    public function schema(): array
    {
        return [
            TextInput::make('data.image_url')
                ->label('图片地址')
                ->url()
                ->maxLength(500),
            TextInput::make('data.url')
                ->label('跳转链接')
                ->url()
                ->maxLength(500),
            TextInput::make('data.alt')
                ->label('图片描述 alt')
                ->maxLength(200),
            Toggle::make('data.new_tab')
                ->label('新窗口打开')
                ->default(true),
        ];
    }

    public function componentKey(): string
    {
        return 'image_link';
    }

    public function authorize(Authenticatable $user): bool
    {
        return true;
    }
}
