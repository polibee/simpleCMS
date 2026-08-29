<?php

declare(strict_types=1);

namespace Modules\Economy\Filament\Resources\WalletLedgerResource\Tables;

use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Miran\Mksine\Core\Hooks\TableHookManager;

class WalletLedgerTable
{
    public static function configure(Table $table): Table
    {
        $table = $table
            ->columns([
                TextColumn::make('id')
                    ->label('ID')
                    ->sortable(),

                TextColumn::make('wallet_id')
                    ->label('钱包 ID')
                    ->sortable(),

                TextColumn::make('currency')
                    ->label('货币')
                    ->badge()
                    ->sortable(),

                TextColumn::make('type')
                    ->label('类型')
                    ->badge()
                    ->color(fn (string $state): string => $state === 'credit' ? 'success' : 'danger')
                    ->formatStateUsing(fn (string $state): string => $state === 'credit' ? '入账' : '扣减'),

                TextColumn::make('amount')
                    ->label('金额')
                    ->numeric()
                    ->sortable(),

                TextColumn::make('balance_after')
                    ->label('变动后余额')
                    ->numeric()
                    ->toggleable(),

                TextColumn::make('ref_type')
                    ->label('来源类型')
                    ->searchable()
                    ->toggleable(),

                TextColumn::make('ref_id')
                    ->label('来源 ID')
                    ->toggleable(),

                TextColumn::make('created_at')
                    ->label('时间')
                    ->dateTime()
                    ->sortable(),
            ])
            ->filters([
                //
            ])
            ->defaultSort('id', 'desc')
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

        return $hookManager->apply('WalletLedger.table', $table);
    }
}
