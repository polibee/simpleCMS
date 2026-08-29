<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Casts\Attribute;

/**
 * 侧边栏卡片实例（系统级，ADR-010）。
 * area_key 由各模块注册；type 对应 CardTypeContract 实现。
 */
class SidebarCard extends Model
{
    protected $fillable = [
        'area_key',
        'type',
        'title',
        'sort',
        'enabled',
        'data',
    ];

    protected $casts = [
        'enabled' => 'boolean',
        'data' => 'array',
    ];
}
