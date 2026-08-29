<?php

namespace App\Core\Sidebar\Types;

use App\Core\Sidebar\CardTypeContract;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Illuminate\Contracts\Auth\Authenticatable;

/**
 * 内置类型：文本超链接卡片（按钮可选）。
 */
class TextLinkType implements CardTypeContract
{
    public function typeKey(): string
    {
        return 'text_link';
    }

    public function label(): string
    {
        return '文本超链接';
    }

    public function schema(): array
    {
        return [
            Textarea::make('data.body')
                ->label('正文')
                ->rows(3)
                ->maxLength(500),
            TextInput::make('data.url')
                ->label('跳转链接（可选）')
                ->url()
                ->maxLength(500),
            TextInput::make('data.button_text')
                ->label('按钮文字')
                ->maxLength(30),
            Toggle::make('data.new_tab')
                ->label('新窗口打开')
                ->default(true),
        ];
    }

    public function componentKey(): string
    {
        return 'text_link';
    }

    public function authorize(Authenticatable $user): bool
    {
        return true;
    }
}
