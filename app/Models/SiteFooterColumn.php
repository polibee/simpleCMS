<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * 页脚列（通用模板）：一列 = 标题 + 绑定的菜单。
 * 管理员在后台"外观 → 页脚导航列"添加列并选择菜单；前端按列渲染。
 */
class SiteFooterColumn extends Model
{
    protected $fillable = [
        'title',
        'menu_id',
        'sort',
        'enabled',
    ];

    protected $casts = [
        'enabled' => 'boolean',
    ];

    public function menu(): BelongsTo
    {
        return $this->belongsTo(\Miran\Mksine\Models\Menu::class);
    }
}
