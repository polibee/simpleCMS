<?php

declare(strict_types=1);

namespace Modules\Ads\Filament\Resources\AdResource\Tables;

use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;
use Modules\Ads\Models\Ad;

class AdTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('sort')
                    ->label('排序')
                    ->sortable()
                    ->width('60px'),
                TextColumn::make('name')
                    ->label('名称')
                    ->searchable(),
                TextColumn::make('position')
                    ->label('位置')
                    ->badge()
                    ->color('info')
                    ->formatStateUsing(fn ($state) => Ad::POSITIONS[$state] ?? $state),
                TextColumn::make('type')
                    ->label('类型')
                    ->badge()
                    ->formatStateUsing(fn ($state) => Ad::TYPES[$state] ?? $state),
                TextColumn::make('paragraph')
                    ->label('段落后')
                    ->placeholder('—'),
                IconColumn::make('enabled')
                    ->label('启用')
                    ->boolean(),
            ])
            ->filters([
                SelectFilter::make('position')
                    ->label('位置')
                    ->options(Ad::POSITIONS),
                TernaryFilter::make('enabled')
                    ->label('启用'),
            ])
            ->actions([
                EditAction::make(),
            ])
            ->bulkActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ])
            ->defaultSort('sort');
    }
}
