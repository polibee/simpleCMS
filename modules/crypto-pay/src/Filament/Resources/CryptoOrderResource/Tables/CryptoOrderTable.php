<?php

declare(strict_types=1);

namespace Modules\CryptoPay\Filament\Resources\CryptoOrderResource\Tables;

use App\Models\User;
use Filament\Actions\Action;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Modules\CryptoPay\Models\CryptoOrder;
use Modules\CryptoPay\Services\CoinPayService;

class CryptoOrderTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('order_no')
                    ->label('单号')
                    ->fontFamily('mono')
                    ->searchable(),

                TextColumn::make('post_id')
                    ->label('文章 ID')
                    ->sortable(),

                TextColumn::make('user.name')
                    ->label('买家'),

                TextColumn::make('amount')
                    ->label('金额')
                    ->money('USD')
                    ->sortable(),

                TextColumn::make('status')
                    ->label('状态')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'paid' => 'success',
                        'pending' => 'warning',
                        default => 'danger',
                    })
                    ->formatStateUsing(fn (string $state): string => match ($state) {
                        'paid' => '已支付',
                        'pending' => '待支付',
                        'cancelled' => '已取消',
                        default => '失败',
                    }),

                TextColumn::make('paid_at')
                    ->label('支付时间')
                    ->dateTime()
                    ->placeholder('—'),
            ])
            ->defaultSort('id', 'desc')
            ->actions([
                Action::make('confirmPaid')
                    ->label('手动确认支付')
                    ->icon('heroicon-o-check-badge')
                    ->color('success')
                    ->requiresConfirmation()
                    ->visible(fn (CryptoOrder $record): bool => $record->status === 'pending')
                    ->action(function (CryptoOrder $record): void {
                        app(CoinPayService::class)->markPaid($record, ['source' => 'manual']);
                    }),
            ]);
    }
}
