<?php

declare(strict_types=1);

namespace Modules\Economy\Filament\Resources\WalletResource\Pages;

use Miran\Mksine\Filament\Resources\Pages\MksineListRecords;
use Modules\Economy\Filament\Resources\WalletResource\WalletResource;

class ListWallets extends MksineListRecords
{
    protected static string $resource = WalletResource::class;
}