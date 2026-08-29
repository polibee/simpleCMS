<?php

declare(strict_types=1);

namespace Modules\Economy\Filament\Resources\WalletResource\Tables;

use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Miran\Mksine\Core\Hooks\TableHookManager;

class WalletTable
{
    public static function configure(Table $table): Table
    {
        $table = $table
            ->columns([
                TextColumn::make('user_id')
                    ->label('用户 ID')
                    ->sortable()
                    ->searchable(),

                TextColumn::make('currency')
                    ->label('货币')
                    ->badge()
                    ->sortable(),

                TextColumn::make('balance')
                    ->label('余额')
                    ->numeric()
                    ->sortable(),

                TextColumn::make('updated_at')
                    ->label('更新时间')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
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

        return $hookManager->apply('Wallet.table', $table);
    }
}
