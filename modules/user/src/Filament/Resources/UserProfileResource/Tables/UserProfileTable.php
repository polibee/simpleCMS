<?php

declare(strict_types=1);

namespace Modules\User\Filament\Resources\UserProfileResource\Tables;

use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Miran\Mksine\Core\Hooks\TableHookManager;

class UserProfileTable
{
    public static function configure(Table $table): Table
    {
        $table = $table
            ->columns([
                TextColumn::make('user_id')
                    ->label('用户 ID')
                    ->sortable()
                    ->searchable(),

                TextColumn::make('user.name')
                    ->label('用户')
                    ->searchable(),

                TextColumn::make('bio')
                    ->label('简介')
                    ->limit(40)
                    ->toggleable(),

                TextColumn::make('website')
                    ->label('网站')
                    ->toggleable(),

                TextColumn::make('created_at')
                    ->label('创建时间')
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

        return $hookManager->apply('UserProfile.table', $table);
    }
}
