<?php

declare(strict_types=1);

namespace Modules\Quest\Filament\Resources;

use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Table;
use Modules\Quest\Filament\Resources\QuestResource\Pages;
use Modules\Quest\Models\Quest;
use Modules\Quest\Filament\Resources\QuestResource\Schemas\QuestForm;
use Modules\Quest\Filament\Resources\QuestResource\Tables\QuestTable;

class QuestResource extends Resource
{
    protected static ?string $model = Quest::class;

    protected static ?string $slug = 'quests';

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-gift';

    protected static \UnitEnum|string|null $navigationGroup = '经济';

    public static function getNavigationLabel(): string
    {
        return __('任务奖励');
    }

    public static function getModelLabel(): string
    {
        return __('任务');
    }

    public static function form(Schema $schema): Schema
    {
        return QuestForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return QuestTable::configure($table);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListQuests::route('/'),
            'edit' => Pages\EditQuest::route('/{record}/edit'),
        ];
    }
}
