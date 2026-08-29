<?php

declare(strict_types=1);

namespace Modules\CMS\Filament\Resources;

use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Table;

/**
 * 单页管理（后台 → 内容 → 单页）：隐私政策 / 服务条款 / 关于 等前台静态页。
 */
class SitePageResource extends Resource
{
    protected static ?string $model = \Modules\CMS\Models\SitePage::class;

    protected static ?string $slug = 'pages';

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-document-text';

    protected static \UnitEnum|string|null $navigationGroup = '内容';

    protected static ?int $navigationSort = 4;

    public static function getNavigationLabel(): string
    {
        return __('单页管理');
    }

    public static function getModelLabel(): string
    {
        return __('单页');
    }

    public static function form(Schema $schema): Schema
    {
        return \Modules\CMS\Filament\Resources\SitePageResource\Schemas\SitePageForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return \Modules\CMS\Filament\Resources\SitePageResource\Tables\SitePageTable::configure($table);
    }

    public static function getPages(): array
    {
        return [
            'index' => \Modules\CMS\Filament\Resources\SitePageResource\Pages\ListSitePages::route('/'),
            'create' => \Modules\CMS\Filament\Resources\SitePageResource\Pages\CreateSitePage::route('/create'),
            'edit' => \Modules\CMS\Filament\Resources\SitePageResource\Pages\EditSitePage::route('/{record}/edit'),
        ];
    }
}
