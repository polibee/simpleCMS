<?php

declare(strict_types=1);

namespace Modules\Economy\Filament\Resources\WalletResource;

use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Table;
use Modules\Economy\Filament\Resources\WalletResource\Pages;
use Modules\Economy\Filament\Resources\WalletResource\Schemas\WalletForm;
use Modules\Economy\Filament\Resources\WalletResource\Tables\WalletTable;
use Modules\Economy\Models\Wallet;

class WalletResource extends Resource
{
    protected static ?string $model = Wallet::class;

    protected static ?string $slug = 'wallets';

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-wallet';

    protected static \UnitEnum|string|null $navigationGroup = '经济';

    public static function getNavigationLabel(): string
    {
        return __('钱包');
    }

    public static function getModelLabel(): string
    {
        return __('钱包');
    }

    public static function getPluralModelLabel(): string
    {
        return __('钱包');
    }

    public static function form(Schema $schema): Schema
    {
        return WalletForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return WalletTable::configure($table);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListWallets::route('/'),
            'create' => Pages\CreateWallet::route('/create'),
            'edit' => Pages\EditWallet::route('/{record}/edit'),
        ];
    }
}
