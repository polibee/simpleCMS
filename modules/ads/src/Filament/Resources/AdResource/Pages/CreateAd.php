<?php

declare(strict_types=1);

namespace Modules\Ads\Filament\Resources\AdResource\Pages;

use Filament\Actions\CreateAction;
use Filament\Resources\Pages\CreateRecord;
use Modules\Ads\Filament\Resources\AdResource;

class CreateAd extends CreateRecord
{
    protected static string $resource = AdResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
