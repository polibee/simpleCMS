<?php

declare(strict_types=1);

namespace Modules\Economy\Filament\Resources\CurrencyConfigResource\Tables;

use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Miran\Mksine\Core\Hooks\TableHookManager;

class CurrencyConfigTable
{
    public static function configure(Table $table): Table
    {
        $table = $table
            ->columns([
                TextColumn::make('key')
                    ->label('标识')
                    ->badge()
                    ->sortable()
                    ->searchable(),

                TextColumn::make('name')
                    ->label('名称')
                    ->sortable()
                    ->searchable(),

                TextColumn::make('unit')
                    ->label('汇率')
                    ->numeric()
                    ->sortable(),

                IconColumn::make('enabled')
                    ->label('启用')
                    ->boolean(),
            ])
            ->filters([
                //
            ])
            ->actions([
                EditAction::make(),
                DeleteAction::make(),
            ])
            ->bulkActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);

        // Apply table hooks
        $hookManager = app(TableHookManager::class);

        return $hookManager->apply('CurrencyConfig.table', $table);
    }
}
