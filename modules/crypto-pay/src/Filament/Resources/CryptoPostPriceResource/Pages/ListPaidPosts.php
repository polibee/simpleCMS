<?php

declare(strict_types=1);

namespace Modules\CryptoPay\Filament\Resources\CryptoPostPriceResource\Pages;

use Filament\Resources\Pages\ListRecords;
use Modules\CryptoPay\Filament\Resources\CryptoPostPriceResource;

class ListPaidPosts extends ListRecords
{
    protected static string $resource = CryptoPostPriceResource::class;
}
