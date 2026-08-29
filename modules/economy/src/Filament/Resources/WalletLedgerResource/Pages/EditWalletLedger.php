<?php

declare(strict_types=1);

namespace Modules\Economy\Filament\Resources\WalletLedgerResource\Pages;

use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;
use Modules\Economy\Filament\Resources\WalletLedgerResource\WalletLedgerResource;

class EditWalletLedger extends EditRecord
{
    protected static string $resource = WalletLedgerResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }
}