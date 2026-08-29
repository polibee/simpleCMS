<?php

declare(strict_types=1);

namespace Modules\CMS\Filament\Resources\CmsBannerResource\Pages;

use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;
use Modules\CMS\Filament\Resources\CmsBannerResource;

class ListCmsBanners extends ListRecords
{
    protected static string $resource = CmsBannerResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
