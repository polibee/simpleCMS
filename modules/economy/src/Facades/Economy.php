<?php

namespace Modules\Economy;

use Illuminate\Support\Facades\Facade;

/**
 * @method static \Modules\Economy\Models\WalletLedger|null credit(\Illuminate\Contracts\Auth\Authenticatable $user, string $currency, float|string $amount, string $refType, int|string $refId, ?string $remark = null)
 * @method static \Modules\Economy\Models\WalletLedger|null debit(\Illuminate\Contracts\Auth\Authenticatable $user, string $currency, float|string $amount, string $refType, int|string $refId, ?string $remark = null)
 * @method static void transfer(\Illuminate\Contracts\Auth\Authenticatable $from, \Illuminate\Contracts\Auth\Authenticatable $to, string $currency, float|string $amount, string $remark = '转账')
 * @method static float balance(\Illuminate\Contracts\Auth\Authenticatable $user, string $currency)
 *
 * @see \Modules\Economy\Services\WalletService
 */
class Economy extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return \Modules\Economy\Services\WalletService::class;
    }
}
