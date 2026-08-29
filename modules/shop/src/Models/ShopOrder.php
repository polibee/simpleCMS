<?php

namespace Modules\Shop\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ShopOrder extends Model
{
    protected $fillable = [
        'order_no',
        'user_id',
        'product_id',
        'amount',
        'currency',
        'quantity',
        'channel',
        'invoice_id',
        'checkout_url',
        'status',
        'paid_at',
        'delivered_data',
    ];

    protected $casts = [
        'amount' => 'float',
        'quantity' => 'integer',
        'paid_at' => 'datetime',
        'delivered_data' => 'array',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(config('mksine.user_model', \App\Models\User::class));
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(ShopProduct::class);
    }
}
