<?php

namespace Modules\Invite\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class InviteOrder extends Model
{
    protected $fillable = [
        'order_no',
        'user_id',
        'amount',
        'fiat_currency',
        'channel',
        'invoice_id',
        'checkout_url',
        'status',
        'paid_at',
    ];

    protected $casts = [
        'amount' => 'float',
        'paid_at' => 'datetime',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(config('mksine.user_model', \App\Models\User::class));
    }
}
