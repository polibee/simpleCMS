<?php

declare(strict_types=1);

namespace Modules\Economy\Filament\Resources\CurrencyConfigResource\Pages;

use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;
use Modules\Economy\Filament\Resources\CurrencyConfigResource\CurrencyConfigResource;

class EditCurrencyConfig extends EditRecord
{
    protected static string $resource = CurrencyConfigResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }
}