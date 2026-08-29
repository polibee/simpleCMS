<?php

namespace Modules\Invite\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class InviteCode extends Model
{
    protected $fillable = [
        'code',
        'source',
        'paid_amount',
        'gold_spent',
        'created_by',
        'used_by',
        'used_at',
        'expires_at',
        'status',
        'batch_id',
    ];

    protected $casts = [
        'paid_amount' => 'float',
        'gold_spent' => 'integer',
        'used_at' => 'datetime',
        'expires_at' => 'datetime',
    ];

    public function owner(): BelongsTo
    {
        return $this->belongsTo(config('mksine.user_model', \App\Models\User::class), 'created_by');
    }

    public function usedByUser(): BelongsTo
    {
        return $this->belongsTo(config('mksine.user_model', \App\Models\User::class), 'used_by');
    }

    /** 是否可被注册使用。 */
    public function isUsable(): bool
    {
        return $this->status === 'active'
            && ($this->expires_at === null || $this->expires_at->isFuture());
    }
}
