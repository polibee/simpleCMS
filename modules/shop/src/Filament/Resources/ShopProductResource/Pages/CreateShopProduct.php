<?php

declare(strict_types=1);

namespace Modules\Shop\Filament\Resources\ShopProductResource\Pages;

use Filament\Actions\CreateAction;
use Filament\Resources\Pages\CreateRecord;
use Modules\Shop\Filament\Resources\ShopProductResource;

class CreateShopProduct extends CreateRecord
{
    protected static string $resource = ShopProductResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
