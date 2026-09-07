<?php

declare(strict_types=1);

namespace Modules\CryptoPay\Filament\Resources\CryptoPostPriceResource\Tables;

use Filament\Actions\Action;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Miran\Mksine\Models\Post;
use Modules\CryptoPay\Services\CoinPayService;

class PaidPostTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('id')
                    ->label('ID')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('title')
                    ->label('标题')
                    ->searchable()
                    ->limit(40)
                    ->wrap(),
                TextColumn::make('status')
                    ->label('状态')
                    ->badge()
                    ->color(fn ($state) => $state === 'published' ? 'success' : 'gray')
                    ->formatStateUsing(fn ($state) => $state === 'published' ? '已发布' : $state),
                TextColumn::make('author.name')
                    ->label('作者')
                    ->toggleable(),
                TextColumn::make('paid_price')
                    ->label('解锁价格 (USD)')
                    ->money('USD')
                    ->placeholder('免费')
                    ->sortable(),
            ])
            ->filters([
                SelectFilter::make('status')
                    ->label('状态')
                    ->options(['published' => '已发布', 'draft' => '草稿', 'archived' => '归档']),
            ])
            ->modifyQueryUsing(fn ($query) => $query
                ->with('author:id,name')
                ->leftJoin('crypto_post_prices', 'crypto_post_prices.post_id', '=', 'posts.id')
                ->select('posts.*', 'crypto_post_prices.price as paid_price'))
            ->headerActions([
                Action::make('help')
                    ->label('使用说明')
                    ->icon('heroicon-o-question-mark-circle')
                    ->color('gray')
                    ->modalSubmitAction(false)
                    ->modalCancelActionLabel('知道了')
                    ->modalContent(view('crypto-pay::paid-posts-help')),
            ])
            ->actions([
                Action::make('setPrice')
                    ->label(fn (Post $record) => $record->paid_price !== null ? '修改价格' : '设置价格')
                    ->icon('heroicon-o-currency-dollar')
                    ->form([
                        TextInput::make('price')
                            ->label('解锁价格（USD）')
                            ->numeric()
                            ->minValue(0.01)
                            ->step(0.01)
                            ->default(fn (Post $record) => $record->paid_price)
                            ->required(),
                    ])
                    ->action(function (Post $record, array $data): void {
                        app(CoinPayService::class)->setPrice((int) $record->id, (float) $data['price']);

                        Notification::make()->title('已设置付费价格 $'.number_format((float) $data['price'], 2))->success()->send();
                    }),
                Action::make('clearPrice')
                    ->label('设为免费')
                    ->icon('heroicon-o-x-mark')
                    ->color('danger')
                    ->requiresConfirmation()
                    ->modalHeading('确认设为免费？')
                    ->modalDescription('移除该文章的付费阅读价格，文章恢复为免费阅读。')
                    ->action(function (Post $record): void {
                        \Illuminate\Support\Facades\DB::table('crypto_post_prices')
                            ->where('post_id', $record->id)
                            ->delete();

                        Notification::make()->title('已恢复为免费文章')->success()->send();
                    }),
            ]);
    }
}
