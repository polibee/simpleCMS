<?php

declare(strict_types=1);

namespace Modules\Shop\Filament\Resources\ShopOrderResource\Tables;

use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Modules\Shop\Models\ShopOrder;
use Modules\Shop\Services\ShopService;

class ShopOrderTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('order_no')
                    ->label('订单号')
                    ->copyable()
                    ->searchable(),
                TextColumn::make('product.name')
                    ->label('商品'),
                TextColumn::make('user_id')
                    ->label('买家 ID'),
                TextColumn::make('amount')
                    ->label('金额')
                    ->money('USD')
                    ->sortable(),
                TextColumn::make('channel')
                    ->label('通道')
                    ->badge()
                    ->formatStateUsing(fn ($state) => ['mock' => 'Mock', 'xcash' => 'Xcash', 'paypal' => 'PayPal'][$state] ?? $state),
                TextColumn::make('status')
                    ->label('状态')
                    ->badge()
                    ->color(fn ($state) => $state === 'paid' ? 'success' : ($state === 'pending' ? 'warning' : 'danger'))
                    ->formatStateUsing(fn ($state) => ['pending' => '待支付', 'paid' => '已支付', 'failed' => '失败'][$state] ?? $state),
                TextColumn::make('paid_at')
                    ->label('支付时间')
                    ->dateTime('m-d H:i')
                    ->placeholder('—'),
                TextColumn::make('created_at')
                    ->label('创建时间')
                    ->dateTime('m-d H:i'),
            ])
            ->filters([
                SelectFilter::make('status')
                    ->label('状态')
                    ->options(['pending' => '待支付', 'paid' => '已支付', 'failed' => '失败']),
                SelectFilter::make('channel')
                    ->label('通道')
                    ->options(['mock' => 'Mock', 'xcash' => 'Xcash', 'paypal' => 'PayPal']),
            ])
            ->headerActions([
                \Filament\Actions\Action::make('exportCsv')
                    ->label('导出 CSV')
                    ->icon('heroicon-o-arrow-down-tray')
                    ->color('gray')
                    ->url(route('orders.export', ['type' => 'shop'])),
            ])
            ->recordActions([
                Action::make('confirmPaid')
                    ->label('确认支付')
                    ->icon('heroicon-o-check-circle')
                    ->color('success')
                    ->requiresConfirmation()
                    ->visible(fn (ShopOrder $record) => $record->status === 'pending')
                    ->action(function (ShopOrder $record) {
                        app(ShopService::class)->markPaid($record);
                        Notification::make()->title('已确认支付并发货')->success()->send();
                    }),
            ])
            ->defaultSort('id', 'desc');
    }
}
