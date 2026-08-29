<?php

declare(strict_types=1);

namespace Modules\Quest\Filament\Resources\QuestResource\Tables;

use Filament\Actions\EditAction;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class QuestTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('key')
                    ->label('标识')
                    ->badge()
                    ->sortable(),

                TextColumn::make('name')
                    ->label('名称')
                    ->sortable(),

                TextColumn::make('reward_currency')
                    ->label('奖励货币')
                    ->badge(),

                TextColumn::make('reward_amount')
                    ->label('数额')
                    ->numeric(),

                TextColumn::make('daily_limit')
                    ->label('每日上限')
                    ->numeric(),

                IconColumn::make('enabled')
                    ->label('启用')
                    ->boolean(),
            ])
            ->actions([
                EditAction::make(),
            ]);
    }
}
