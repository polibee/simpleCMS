<?php

declare(strict_types=1);

namespace App\Filament\Resources;

use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;

/**
 * 系统级侧边栏卡片管理（ADR-010）。
 * 管理所有模块/产品注册区域的卡片实例；类型与表单字段来自 SidebarManager 注册表。
 */
class SidebarCardResource extends Resource
{
    protected static ?string $model = \App\Models\SidebarCard::class;

    protected static ?string $slug = 'sidebar-cards';

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-adjustments-horizontal';

    protected static \UnitEnum|string|null $navigationGroup = '外观';

    protected static ?int $navigationSort = 2;

    public static function getNavigationLabel(): string
    {
        return __('侧边栏管理');
    }

    public static function getModelLabel(): string
    {
        return __('侧边栏卡片');
    }

    public static function canViewAny(): bool
    {
        return Auth::user()?->hasRole('super_admin') ?? false;
    }

    public static function form(Schema $schema): Schema
    {
        return \App\Filament\Resources\SidebarCardResource\Schemas\SidebarCardForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return \App\Filament\Resources\SidebarCardResource\Tables\SidebarCardTable::configure($table);
    }

    public static function getPages(): array
    {
        return [
            'index' => \App\Filament\Resources\SidebarCardResource\Pages\ListSidebarCards::route('/'),
            'create' => \App\Filament\Resources\SidebarCardResource\Pages\CreateSidebarCard::route('/create'),
            'edit' => \App\Filament\Resources\SidebarCardResource\Pages\EditSidebarCard::route('/{record}/edit'),
        ];
    }
}
