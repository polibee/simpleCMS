<?php

namespace Modules\CMS\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PostMeta extends Model
{
    protected $table = 'cms_post_meta';

    protected $fillable = ['post_id', 'view_count', 'extra'];

    protected $casts = [
        'extra' => 'array',
    ];

    public function post(): BelongsTo
    {
        return $this->belongsTo(\Miran\Mksine\Models\Post::class, 'post_id');
    }
}
