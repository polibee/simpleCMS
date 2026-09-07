<?php

declare(strict_types=1);

namespace Modules\CryptoPay\Filament\Resources;

use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Table;
use Miran\Mksine\Models\Post;

/**
 * 付费文章定价（后台 → 内容 → 付费文章）：
 * 为任意已发布文章设置加密支付解锁价格（>0 即启用付费阅读）。
 *
 * 说明：付费文章的正文内插入 [coinpay_buy]（可带 price 属性）即可分段付费；
 * 此处设置的价格作为该文章的默认解锁价。
 */
class CryptoPostPriceResource extends Resource
{
    protected static ?string $model = Post::class;

    protected static ?string $slug = 'paid-posts';

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-currency-dollar';

    protected static \UnitEnum|string|null $navigationGroup = \Miran\Mksine\Filament\Support\AdminNavigationGroup::Content;

    protected static ?int $navigationSort = 99;

    public static function getNavigationLabel(): string
    {
        return __('付费文章');
    }

    public static function getModelLabel(): string
    {
        return __('付费文章');
    }

    public static function getPluralModelLabel(): string
    {
        return __('付费文章');
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->components([]);
    }

    public static function table(Table $table): Table
    {
        return \Modules\CryptoPay\Filament\Resources\CryptoPostPriceResource\Tables\PaidPostTable::configure($table);
    }

    public static function getPages(): array
    {
        return [
            'index' => \Modules\CryptoPay\Filament\Resources\CryptoPostPriceResource\Pages\ListPaidPosts::route('/'),
        ];
    }
}
