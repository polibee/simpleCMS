<?php

declare(strict_types=1);

namespace Modules\Shop\Filament\Resources\ShopOrderResource\Pages;

use Filament\Resources\Pages\ListRecords;
use Modules\Shop\Filament\Resources\ShopOrderResource;

class ListShopOrders extends ListRecords
{
    protected static string $resource = ShopOrderResource::class;
}
