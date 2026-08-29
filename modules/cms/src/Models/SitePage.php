<?php

namespace Modules\CMS\Models;

use Illuminate\Database\Eloquent\Model;

class SitePage extends Model
{
    protected $fillable = [
        'slug',
        'title',
        'content',
        'enabled',
        'sort',
    ];

    protected $casts = [
        'enabled' => 'boolean',
        'sort' => 'integer',
    ];

    /** 默认单页清单（slug => 标题）。 */
    public const DEFAULTS = [
        'privacy' => '隐私政策',
        'terms' => '服务条款',
        'about' => '关于我们',
    ];
}
