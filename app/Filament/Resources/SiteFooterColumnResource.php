<?php

declare(strict_types=1);

namespace App\Filament\Resources;

use BackedEnum;
use Filament\Actions\Action;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\CreateAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Support\Facades\Auth;
use Miran\Mksine\Models\Menu;

/**
 * 页脚导航列管理（通用模板）：后台 → 外观 → 页脚导航列。
 * 管理员添加列（标题 + 绑定菜单）；菜单内容在"菜单"构建器里维护。
 */
class SiteFooterColumnResource extends Resource
{
    protected static ?string $model = \App\Models\SiteFooterColumn::class;

    protected static ?string $slug = 'footer-columns';

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-queue-list';

    protected static \UnitEnum|string|null $navigationGroup = '外观';

    protected static ?int $navigationSort = 3;

    public static function canAccess(): bool
    {
        $user = Auth::user();

        if ($user && method_exists($user, 'hasRole') && $user->hasRole('super_admin')) {
            return true;
        }

        return parent::canAccess();
    }

    public static function getNavigationLabel(): string
    {
        return __('页脚导航列');
    }

    public static function getModelLabel(): string
    {
        return __('页脚列');
    }

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('title')
                    ->label('列标题')
                    ->required()
                    ->maxLength(100)
                    ->helperText('如：产品 / 支持 / 关于'),
                Select::make('menu_id')
                    ->label('绑定菜单')
                    ->options(fn (): array => Menu::query()->orderBy('name')->pluck('name', 'id')->all())
                    ->helperText('菜单内容在 后台 → 菜单 里创建和维护'),
                Toggle::make('enabled')
                    ->label('启用')
                    ->default(true),
            ])
            ->columns(1);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('sort')
                    ->label('排序')
                    ->sortable()
                    ->width('60px'),

                TextColumn::make('title')
                    ->label('列标题')
                    ->searchable(),

                TextColumn::make('menu.name')
                    ->label('绑定菜单')
                    ->placeholder('—'),

                IconColumn::make('enabled')
                    ->label('启用')
                    ->boolean(),
            ])
            ->defaultSort('sort')
            ->reorderable('sort')
            ->filters([])
            ->actions([
                EditAction::make(),
                Action::make('toggle')
                    ->label(fn ($record) => $record->enabled ? '停用' : '启用')
                    ->icon(fn ($record) => $record->enabled ? 'heroicon-o-pause' : 'heroicon-o-play')
                    ->action(fn ($record) => $record->update(['enabled' => ! $record->enabled])),
            ])
            ->bulkActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => \App\Filament\Resources\SiteFooterColumnResource\Pages\ListSiteFooterColumns::route('/'),
        ];
    }
}
