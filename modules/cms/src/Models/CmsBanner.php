<?php

namespace Modules\CMS\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * 首页轮播图（CMS 模块）。
 */
class CmsBanner extends Model
{
    protected $table = 'cms_banners';

    protected $fillable = [
        'title',
        'image_url',
        'link_url',
        'sort',
        'enabled',
        'new_tab',
    ];

    protected $casts = [
        'enabled' => 'boolean',
        'new_tab' => 'boolean',
    ];
}
