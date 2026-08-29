<?php

declare(strict_types=1);

namespace Modules\Economy\Filament\Resources\WalletLedgerResource\Pages;

use Filament\Resources\Pages\CreateRecord;
use Modules\Economy\Filament\Resources\WalletLedgerResource\WalletLedgerResource;

class CreateWalletLedger extends CreateRecord
{
    protected static string $resource = WalletLedgerResource::class;
}