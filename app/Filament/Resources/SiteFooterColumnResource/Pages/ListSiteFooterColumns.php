<?php

declare(strict_types=1);

namespace App\Filament\Resources\SiteFooterColumnResource\Pages;

use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;
use App\Filament\Resources\SiteFooterColumnResource;

class ListSiteFooterColumns extends ListRecords
{
    protected static string $resource = SiteFooterColumnResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
