<?php

namespace Modules\CryptoPay\Models;

use Illuminate\Database\Eloquent\Model;

class CryptoOrder extends Model
{
    protected $table = 'crypto_orders';

    protected $fillable = [
        'order_no',
        'user_id',
        'session_id',
        'ip',
        'guest_email',
        'channel',
        'post_id',
        'amount',
        'fiat_currency',
        'invoice_id',
        'checkout_url',
        'status',
        'paid_at',
        'payload',
    ];

    protected $casts = [
        'paid_at' => 'datetime',
        'payload' => 'array',
    ];
}
