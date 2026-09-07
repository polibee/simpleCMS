<?php

declare(strict_types=1);

namespace Modules\Shop\Filament\Resources;

use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Table;

/**
 * 订单流水（后台 → 商城 → 订单流水）。
 */
class ShopOrderResource extends Resource
{
    protected static ?string $model = \Modules\Shop\Models\ShopOrder::class;

    protected static ?string $slug = 'orders';

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-receipt-percent';

    protected static \UnitEnum|string|null $navigationGroup = \Miran\Mksine\Filament\Support\AdminNavigationGroup::Orders;

    protected static ?int $navigationSort = 2;

    public static function getNavigationLabel(): string
    {
        return __('shop::shop.navigation_orders');
    }

    public static function getModelLabel(): string
    {
        return __('shop::shop.order');
    }

    public static function canCreate(): bool
    {
        return false; // 订单由前台购买产生
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->components([]);
    }

    public static function table(Table $table): Table
    {
        return \Modules\Shop\Filament\Resources\ShopOrderResource\Tables\ShopOrderTable::configure($table);
    }

    public static function getPages(): array
    {
        return [
            'index' => \Modules\Shop\Filament\Resources\ShopOrderResource\Pages\ListShopOrders::route('/'),
        ];
    }
}
