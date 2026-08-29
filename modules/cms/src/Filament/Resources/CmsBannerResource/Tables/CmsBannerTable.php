<?php

declare(strict_types=1);

namespace Modules\CMS\Filament\Resources\CmsBannerResource\Tables;

use Filament\Actions\Action;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\ImageColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Modules\CMS\Models\CmsBanner;

class CmsBannerTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                ImageColumn::make('image_url')
                    ->label('预览')
                    ->width(120)
                    ->height(48),

                TextColumn::make('title')
                    ->label('标题')
                    ->placeholder('—')
                    ->searchable(),

                TextColumn::make('link_url')
                    ->label('链接')
                    ->limit(40)
                    ->placeholder('纯展示')
                    ->toggleable(),

                TextColumn::make('sort')
                    ->label('排序')
                    ->sortable()
                    ->width('60px'),

                IconColumn::make('enabled')
                    ->label('启用')
                    ->boolean(),
            ])
            ->defaultSort('sort')
            ->reorderable('sort')
            ->filters([
                //
            ])
            ->actions([
                EditAction::make(),
                Action::make('toggle')
                    ->label(fn (CmsBanner $record) => $record->enabled ? '停用' : '启用')
                    ->icon(fn (CmsBanner $record) => $record->enabled ? 'heroicon-o-pause' : 'heroicon-o-play')
                    ->action(fn (CmsBanner $record) => $record->update(['enabled' => ! $record->enabled])),
            ])
            ->bulkActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }
}
