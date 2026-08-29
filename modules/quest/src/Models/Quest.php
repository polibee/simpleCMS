<?php

namespace Modules\Quest\Models;

use Illuminate\Database\Eloquent\Model;

class Quest extends Model
{
    protected $table = 'quests';

    protected $fillable = [
        'key',
        'name',
        'reward_currency',
        'reward_amount',
        'enabled',
        'daily_limit',
    ];

    protected $casts = [
        'enabled' => 'boolean',
    ];
}
