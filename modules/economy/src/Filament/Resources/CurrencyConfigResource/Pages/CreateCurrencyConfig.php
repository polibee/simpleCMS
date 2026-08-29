<?php

declare(strict_types=1);

namespace Modules\Economy\Filament\Resources\CurrencyConfigResource\Pages;

use Filament\Resources\Pages\CreateRecord;
use Modules\Economy\Filament\Resources\CurrencyConfigResource\CurrencyConfigResource;

class CreateCurrencyConfig extends CreateRecord
{
    protected static string $resource = CurrencyConfigResource::class;
}