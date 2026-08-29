<?php

namespace Modules\CryptoPay\Models;

use Illuminate\Database\Eloquent\Model;

class CryptoPostPrice extends Model
{
    protected $table = 'crypto_post_prices';

    protected $fillable = [
        'post_id',
        'price',
        'currency',
    ];

    protected $casts = [
        'price' => 'decimal:2',
    ];
}
