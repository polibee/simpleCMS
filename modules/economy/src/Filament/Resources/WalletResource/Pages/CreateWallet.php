<?php

declare(strict_types=1);

namespace Modules\Economy\Filament\Resources\WalletResource\Pages;

use Filament\Resources\Pages\CreateRecord;
use Modules\Economy\Filament\Resources\WalletResource\WalletResource;

class CreateWallet extends CreateRecord
{
    protected static string $resource = WalletResource::class;
}