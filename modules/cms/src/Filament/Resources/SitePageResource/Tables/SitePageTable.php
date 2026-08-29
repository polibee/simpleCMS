<?php

declare(strict_types=1);

namespace Modules\CMS\Filament\Resources\SitePageResource\Tables;

use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Modules\CMS\Models\SitePage;

class SitePageTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('slug')
                    ->label('路径')
                    ->badge()
                    ->copyable()
                    ->formatStateUsing(fn ($state) => '/p/'.$state)
                    ->searchable(),
                TextColumn::make('title')
                    ->label('标题')
                    ->searchable(),
                IconColumn::make('enabled')
                    ->label('启用')
                    ->boolean(),
                TextColumn::make('updated_at')
                    ->label('更新时间')
                    ->dateTime('Y-m-d H:i'),
            ])
            ->filters([])
            ->recordActions([
                EditAction::make(),
            ])
            ->bulkActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ])
            ->defaultSort('sort');
    }
}
