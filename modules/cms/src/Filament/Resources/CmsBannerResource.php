<?php

declare(strict_types=1);

namespace Modules\CMS\Filament\Resources;

use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Table;
use Modules\CMS\Filament\Resources\CmsBannerResource\Pages;
use Modules\CMS\Models\CmsBanner;
use Modules\CMS\Filament\Resources\CmsBannerResource\Schemas\CmsBannerForm;
use Modules\CMS\Filament\Resources\CmsBannerResource\Tables\CmsBannerTable;

/**
 * 首页轮播图管理（CMS 模块）。
 */
class CmsBannerResource extends Resource
{
    protected static ?string $model = CmsBanner::class;

    protected static ?string $slug = 'cms-banners';

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-photo';

    protected static \UnitEnum|string|null $navigationGroup = '内容';

    protected static ?int $navigationSort = 2;

    public static function getNavigationLabel(): string
    {
        return __('首页轮播图');
    }

    public static function getModelLabel(): string
    {
        return __('轮播图');
    }

    public static function form(Schema $schema): Schema
    {
        return CmsBannerForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return CmsBannerTable::configure($table);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListCmsBanners::route('/'),
            'create' => Pages\CreateCmsBanner::route('/create'),
            'edit' => Pages\EditCmsBanner::route('/{record}/edit'),
        ];
    }
}
