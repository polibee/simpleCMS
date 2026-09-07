<?php

declare(strict_types=1);

namespace Modules\Shop\Filament\Resources;

use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Table;

/**
 * 商品管理（后台 → 商城 → 商品）：上架/下架/库存/价格。
 */
class ShopProductResource extends Resource
{
    protected static ?string $model = \Modules\Shop\Models\ShopProduct::class;

    protected static ?string $slug = 'products';

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-shopping-bag';

    protected static \UnitEnum|string|null $navigationGroup = \Miran\Mksine\Filament\Support\AdminNavigationGroup::Products;

    protected static ?int $navigationSort = 1;

    public static function getNavigationLabel(): string
    {
        return __('shop::shop.navigation_products');
    }

    public static function getModelLabel(): string
    {
        return __('shop::shop.product');
    }

    public static function form(Schema $schema): Schema
    {
        return \Modules\Shop\Filament\Resources\ShopProductResource\Schemas\ShopProductForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return \Modules\Shop\Filament\Resources\ShopProductResource\Tables\ShopProductTable::configure($table);
    }

    public static function getPages(): array
    {
        return [
            'index' => \Modules\Shop\Filament\Resources\ShopProductResource\Pages\ListShopProducts::route('/'),
            'create' => \Modules\Shop\Filament\Resources\ShopProductResource\Pages\CreateShopProduct::route('/create'),
            'edit' => \Modules\Shop\Filament\Resources\ShopProductResource\Pages\EditShopProduct::route('/{record}/edit'),
        ];
    }
}
