<?php

declare(strict_types=1);

namespace Modules\Shop\Filament\Resources\ShopProductResource\Tables;

use Filament\Actions\Action;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Modules\Shop\Models\ShopProduct;

class ShopProductTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')
                    ->label('商品')
                    ->searchable(),
                TextColumn::make('price')
                    ->label('价格')
                    ->money('USD')
                    ->sortable(),
                TextColumn::make('stock')
                    ->label('库存')
                    ->sortable()
                    ->color(fn ($state) => $state > 0 ? null : 'danger'),
                TextColumn::make('sold')
                    ->label('累计销量')
                    ->sortable(),
                TextColumn::make('status')
                    ->label('状态')
                    ->badge()
                    ->color(fn ($state) => match ($state) {
                        'on_sale' => 'success',
                        'draft' => 'gray',
                        default => 'warning',
                    })
                    ->formatStateUsing(fn ($state) => $state === 'on_sale' ? '已上架' : ($state === 'draft' ? '草稿' : '已下架')),
                TextColumn::make('category')
                    ->label('分类')
                    ->badge()
                    ->placeholder('—'),
            ])
            ->filters([
                SelectFilter::make('status')
                    ->label('状态')
                    ->options(['draft' => '草稿', 'on_sale' => '已上架', 'off_sale' => '已下架']),
            ])
            ->recordActions([
                EditAction::make(),
                Action::make('toggleSale')
                    ->label(fn (ShopProduct $record) => $record->status === 'on_sale' ? '下架' : '上架')
                    ->icon(fn (ShopProduct $record) => $record->status === 'on_sale' ? 'heroicon-o-pause' : 'heroicon-o-play')
                    ->color(fn (ShopProduct $record) => $record->status === 'on_sale' ? 'warning' : 'success')
                    ->action(function (ShopProduct $record): void {
                        $record->update([
                            'status' => $record->status === 'on_sale' ? 'off_sale' : 'on_sale',
                        ]);
                    })
                    ->visible(fn (ShopProduct $record) => $record->status !== 'draft'),
            ])
            ->bulkActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ])
            ->defaultSort('id', 'desc');
    }
}
