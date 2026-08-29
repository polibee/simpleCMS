<?php

declare(strict_types=1);

namespace Modules\CryptoPay\Filament\Resources;

use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Table;
use Modules\CryptoPay\Filament\Resources\CryptoOrderResource\Pages;
use Modules\CryptoPay\Models\CryptoOrder;
use Modules\CryptoPay\Filament\Resources\CryptoOrderResource\Tables\CryptoOrderTable;

/**
 * 加密支付订单管理（后台 → 经济 → 加密支付订单）。
 */
class CryptoOrderResource extends Resource
{
    protected static ?string $model = CryptoOrder::class;

    protected static ?string $slug = 'crypto-orders';

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-banknotes';

    protected static \UnitEnum|string|null $navigationGroup = '经济';

    public static function getNavigationLabel(): string
    {
        return __('加密支付订单');
    }

    public static function getModelLabel(): string
    {
        return __('支付订单');
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->components([]);
    }

    public static function table(Table $table): Table
    {
        return CryptoOrderTable::configure($table);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListCryptoOrders::route('/'),
        ];
    }
}
