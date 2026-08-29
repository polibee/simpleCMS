<?php

declare(strict_types=1);

namespace Modules\Economy\Filament\Resources\CurrencyConfigResource;

use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Table;
use Modules\Economy\Filament\Resources\CurrencyConfigResource\Pages;
use Modules\Economy\Filament\Resources\CurrencyConfigResource\Schemas\CurrencyConfigForm;
use Modules\Economy\Filament\Resources\CurrencyConfigResource\Tables\CurrencyConfigTable;
use Modules\Economy\Models\EconomyCurrencyConfig;

class CurrencyConfigResource extends Resource
{
    protected static ?string $model = EconomyCurrencyConfig::class;

    protected static ?string $slug = 'currency-configs';

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-currency-dollar';

    protected static \UnitEnum|string|null $navigationGroup = '经济';

    public static function getNavigationLabel(): string
    {
        return __('货币配置');
    }

    public static function getModelLabel(): string
    {
        return __('货币');
    }

    public static function getPluralModelLabel(): string
    {
        return __('货币配置');
    }

    public static function form(Schema $schema): Schema
    {
        return CurrencyConfigForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return CurrencyConfigTable::configure($table);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListCurrencyConfigs::route('/'),
            'create' => Pages\CreateCurrencyConfig::route('/create'),
            'edit' => Pages\EditCurrencyConfig::route('/{record}/edit'),
        ];
    }
}
