<?php

declare(strict_types=1);

namespace Modules\CryptoPay\Filament\Resources\CryptoOrderResource\Pages;

use Filament\Resources\Pages\ListRecords;
use Modules\CryptoPay\Filament\Resources\CryptoOrderResource;

class ListCryptoOrders extends ListRecords
{
    protected static string $resource = CryptoOrderResource::class;

    protected function getHeaderActions(): array
    {
        return [];
    }
}
