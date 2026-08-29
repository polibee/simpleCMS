<?php

namespace Modules\Economy\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Wallet extends Model
{
    protected $table = 'wallets';

    protected $fillable = [
        'user_id',
        'currency',
        'balance',
    ];

    protected $casts = [
        'balance' => 'decimal:6',
    ];

    public function ledger(): HasMany
    {
        return $this->hasMany(WalletLedger::class, 'wallet_id');
    }
}
