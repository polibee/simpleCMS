<?php

namespace Modules\Quest\Models;

use Illuminate\Database\Eloquent\Model;

class QuestCheckin extends Model
{
    protected $table = 'quest_checkins';

    protected $fillable = [
        'user_id',
        'checkin_date',
        'quest_id',
    ];
}
