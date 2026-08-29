<?php

declare(strict_types=1);

namespace App\Filament\Resources\SidebarCardResource\Tables;

use App\Core\Sidebar\SidebarManager;
use App\Models\SidebarCard;
use Filament\Actions\Action;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class SidebarCardTable
{
    public static function configure(Table $table): Table
    {
        $manager = app(SidebarManager::class);

        return $table
            ->columns([
                TextColumn::make('sort')
                    ->label('排序')
                    ->sortable()
                    ->width('60px'),

                TextColumn::make('title')
                    ->label('标题')
                    ->formatStateUsing(fn ($state, SidebarCard $record) => $state ?: ($manager->type($record->type)?->label() ?? $record->type))
                    ->searchable(),

                TextColumn::make('type')
                    ->label('类型')
                    ->badge()
                    ->formatStateUsing(fn ($state) => $manager->type($state)?->label() ?? $state),

                TextColumn::make('area_key')
                    ->label('区域')
                    ->badge()
                    ->color('gray')
                    ->formatStateUsing(fn ($state) => $manager->areaLabel($state)),

                IconColumn::make('enabled')
                    ->label('启用')
                    ->boolean(),
            ])
            ->defaultSort('area_key')
            ->reorderable('sort')
            ->filters([
                \Filament\Tables\Filters\SelectFilter::make('area_key')
                    ->label('区域')
                    ->options($manager->areaOptions()),
            ])
            ->actions([
                EditAction::make(),
                Action::make('toggle')
                    ->label(fn (SidebarCard $record) => $record->enabled ? '停用' : '启用')
                    ->icon(fn (SidebarCard $record) => $record->enabled ? 'heroicon-o-pause' : 'heroicon-o-play')
                    ->action(function (SidebarCard $record): void {
                        $record->update(['enabled' => ! $record->enabled]);
                        SidebarManager::flushCache();
                    }),
            ])
            ->bulkActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }
}
