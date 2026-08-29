<?php

declare(strict_types=1);

namespace Modules\Economy\Filament\Resources\WalletLedgerResource\Pages;

use Miran\Mksine\Filament\Resources\Pages\MksineListRecords;
use Modules\Economy\Filament\Resources\WalletLedgerResource\WalletLedgerResource;

class ListWalletLedgers extends MksineListRecords
{
    protected static string $resource = WalletLedgerResource::class;
}