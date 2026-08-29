<?php

declare(strict_types=1);

namespace App\Filament\Resources\SidebarCardResource\Schemas;

use App\Core\Sidebar\SidebarManager;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Illuminate\Support\Facades\Auth;

class SidebarCardForm
{
    public static function configure(Schema $schema): Schema
    {
        $manager = app(SidebarManager::class);

        return $schema
            ->components([
                Section::make('卡片设置')
                    ->schema([
                        Select::make('area_key')
                            ->label('注入区域')
                            ->options($manager->areaOptions())
                            ->default('cms.sidebar')
                            ->required()
                            ->helperText('选择该卡片渲染到哪个模块/产品的侧边栏'),
                        Select::make('type')
                            ->label('卡片类型')
                            ->options($manager->typeOptionsFor(Auth::user()))
                            ->required()
                            ->live()
                            ->helperText('系统内置 + 各模块/插件注册的类型'),
                        TextInput::make('title')
                            ->label('标题')
                            ->maxLength(100)
                            ->helperText('留空使用类型默认标题'),
                        Toggle::make('enabled')
                            ->label('启用')
                            ->default(true),
                    ])
                    ->columns(2),

                // 动态字段：来自各类型注册的 schema，按所选 type visible 过滤
                Section::make('类型配置')
                    ->schema($manager->mergedTypeSchema())
                    ->columns(2),
            ]);
    }
}
