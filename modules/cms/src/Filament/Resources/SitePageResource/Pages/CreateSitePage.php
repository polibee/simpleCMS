<?php

declare(strict_types=1);

namespace Modules\CMS\Filament\Resources\SitePageResource\Pages;

use Filament\Actions\CreateAction;
use Filament\Resources\Pages\CreateRecord;
use Modules\CMS\Filament\Resources\SitePageResource;

class CreateSitePage extends CreateRecord
{
    protected static string $resource = SitePageResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
