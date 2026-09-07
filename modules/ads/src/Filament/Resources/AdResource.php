<?php

declare(strict_types=1);

namespace Modules\Ads\Filament\Resources;

use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Table;

/**
 * 广告位管理（后台 → 内容 → 广告管理）：
 * 文本 / 图片超链 / HTML(JS) 代码三类广告，投放到前端各注入位置。
 */
class AdResource extends Resource
{
    protected static ?string $model = \Modules\Ads\Models\Ad::class;

    protected static ?string $slug = 'ads';

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-megaphone';

    protected static \UnitEnum|string|null $navigationGroup = \Miran\Mksine\Filament\Support\AdminNavigationGroup::Content;

    protected static ?int $navigationSort = 5;

    public static function getNavigationLabel(): string
    {
        return __('ads::ads.navigation_label');
    }

    public static function getModelLabel(): string
    {
        return __('ads::ads.ad');
    }

    public static function form(Schema $schema): Schema
    {
        return \Modules\Ads\Filament\Resources\AdResource\Schemas\AdForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return \Modules\Ads\Filament\Resources\AdResource\Tables\AdTable::configure($table);
    }

    public static function getPages(): array
    {
        return [
            'index' => \Modules\Ads\Filament\Resources\AdResource\Pages\ListAds::route('/'),
            'create' => \Modules\Ads\Filament\Resources\AdResource\Pages\CreateAd::route('/create'),
            'edit' => \Modules\Ads\Filament\Resources\AdResource\Pages\EditAd::route('/{record}/edit'),
        ];
    }
}
