<?php

namespace Modules\Economy\Models;

use Illuminate\Database\Eloquent\Model;

class EconomyCurrencyConfig extends Model
{
    protected $table = 'economy_currency_configs';

    protected $fillable = [
        'key',
        'name',
        'unit',
        'enabled',
    ];

    protected $casts = [
        'unit' => 'decimal:6',
        'enabled' => 'boolean',
    ];
}
