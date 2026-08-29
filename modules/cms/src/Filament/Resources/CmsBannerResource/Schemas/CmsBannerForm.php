<?php

declare(strict_types=1);

namespace Modules\CMS\Filament\Resources\CmsBannerResource\Schemas;

use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class CmsBannerForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('轮播图')
                    ->schema([
                        TextInput::make('title')
                            ->label('标题')
                            ->maxLength(100)
                            ->helperText('可选；用于无障碍提示与悬浮说明'),
                        TextInput::make('image_url')
                            ->label('图片地址')
                            ->url()
                            ->required()
                            ->maxLength(500)
                            ->helperText('支持外链或媒体库路径（上传到 后台→媒体库 后复制链接）'),
                        TextInput::make('link_url')
                            ->label('跳转链接')
                            ->url()
                            ->maxLength(500)
                            ->helperText('点击轮播图跳转；留空则纯展示'),
                        Toggle::make('new_tab')
                            ->label('新窗口打开')
                            ->default(false),
                        Toggle::make('enabled')
                            ->label('启用')
                            ->default(true),
                    ])
                    ->columns(2),
            ]);
    }
}
