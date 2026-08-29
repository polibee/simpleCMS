<?php

declare(strict_types=1);

namespace Modules\Economy\Filament\Resources\CurrencyConfigResource\Pages;

use Miran\Mksine\Filament\Resources\Pages\MksineListRecords;
use Modules\Economy\Filament\Resources\CurrencyConfigResource\CurrencyConfigResource;

class ListCurrencyConfigs extends MksineListRecords
{
    protected static string $resource = CurrencyConfigResource::class;
}