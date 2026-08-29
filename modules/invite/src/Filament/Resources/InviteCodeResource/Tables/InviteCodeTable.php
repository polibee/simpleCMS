<?php

declare(strict_types=1);

namespace Modules\Invite\Filament\Resources\InviteCodeResource\Tables;

use Filament\Actions\Action;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Modules\Invite\Models\InviteCode;
use Modules\Invite\Services\InviteService;

class InviteCodeTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('code')
                    ->label('邀请码')
                    ->copyable()
                    ->searchable(),
                TextColumn::make('source')
                    ->label('来源')
                    ->badge()
                    ->formatStateUsing(fn ($state) => ['admin' => '管理员', 'gold' => '金币购买', 'crypto' => '加密购买'][$state] ?? $state),
                TextColumn::make('status')
                    ->label('状态')
                    ->badge()
                    ->color(fn ($state) => match ($state) {
                        'active' => 'success',
                        'used' => 'gray',
                        default => 'danger',
                    })
                    ->formatStateUsing(fn ($state) => ['active' => '可用', 'used' => '已使用', 'disabled' => '已禁用'][$state] ?? $state),
                TextColumn::make('gold_spent')->label('花费金币')->placeholder('—'),
                TextColumn::make('paid_amount')->label('实付 USD')->placeholder('—'),
                TextColumn::make('created_by')->label('归属用户 ID')->placeholder('—'),
                TextColumn::make('used_by')->label('使用者 ID')->placeholder('—'),
                TextColumn::make('expires_at')->label('过期时间')->date()->placeholder('永久'),
                TextColumn::make('created_at')->label('创建时间')->date(),
            ])
            ->filters([
                SelectFilter::make('source')
                    ->label('来源')
                    ->options(['admin' => '管理员', 'gold' => '金币购买', 'crypto' => '加密购买']),
                SelectFilter::make('status')
                    ->label('状态')
                    ->options(['active' => '可用', 'used' => '已使用', 'disabled' => '已禁用']),
            ])
            ->headerActions([
                Action::make('generate')
                    ->label('批量生成')
                    ->icon('heroicon-o-plus')
                    ->color('primary')
                    ->form([
                        TextInput::make('count')
                            ->label('生成数量（1-100）')
                            ->numeric()
                            ->minValue(1)
                            ->maxValue(100)
                            ->default(10)
                            ->required(),
                        DatePicker::make('expires_at')
                            ->label('过期日期（可选）')
                            ->native(false),
                    ])
                    ->action(function (array $data) {
                        $days = isset($data['expires_at']) ? max(1, (int) ceil((strtotime($data['expires_at']) - time()) / 86400)) : null;
                        $codes = collect(app(InviteService::class)->generate((int) $data['count'], 'admin', null, $days));

                        Notification::make()
                            ->title('已生成 '.$codes->count().' 枚邀请码')
                            ->body($codes->pluck('code')->implode('，'))
                            ->success()
                            ->persistent()
                            ->send();
                    }),
            ])
            ->actions([
                Action::make('toggle')
                    ->label(fn (InviteCode $record) => $record->status === 'disabled' ? '启用' : '禁用')
                    ->action(function (InviteCode $record): void {
                        $record->update(['status' => $record->status === 'disabled' ? 'active' : 'disabled']);
                    })
                    ->visible(fn (InviteCode $record) => $record->status !== 'used'),
            ])
            ->bulkActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ])
            ->defaultSort('id', 'desc');
    }
}
