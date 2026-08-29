<?php

declare(strict_types=1);

namespace Modules\Economy\Filament\Resources\WalletLedgerResource;

use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Table;
use Modules\Economy\Filament\Resources\WalletLedgerResource\Pages;
use Modules\Economy\Filament\Resources\WalletLedgerResource\Schemas\WalletLedgerForm;
use Modules\Economy\Filament\Resources\WalletLedgerResource\Tables\WalletLedgerTable;
use Modules\Economy\Models\WalletLedger;

class WalletLedgerResource extends Resource
{
    protected static ?string $model = WalletLedger::class;

    protected static ?string $slug = 'wallet-ledgers';

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-banknotes';

    protected static \UnitEnum|string|null $navigationGroup = '经济';

    public static function getNavigationLabel(): string
    {
        return __('钱包流水');
    }

    public static function getModelLabel(): string
    {
        return __('钱包流水');
    }

    public static function getPluralModelLabel(): string
    {
        return __('钱包流水');
    }

    public static function form(Schema $schema): Schema
    {
        return WalletLedgerForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return WalletLedgerTable::configure($table);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListWalletLedgers::route('/'),
            'create' => Pages\CreateWalletLedger::route('/create'),
            'edit' => Pages\EditWalletLedger::route('/{record}/edit'),
        ];
    }
}
