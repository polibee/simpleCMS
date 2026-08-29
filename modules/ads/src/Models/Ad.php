<?php

namespace Modules\Ads\Models;

use Illuminate\Database\Eloquent\Model;

class Ad extends Model
{
    protected $fillable = [
        'name',
        'position',
        'type',
        'title',
        'text',
        'image_url',
        'link_url',
        'html',
        'paragraph',
        'new_tab',
        'enabled',
        'sort',
    ];
    protected $casts = [
        'new_tab' => 'boolean',
        'enabled' => 'boolean',
        'paragraph' => 'integer',
        'sort' => 'integer',
    ];

    /** 全部可用投放位置（前端注入点）。 */
    public const POSITIONS = [
        'banner_below' => '首页轮播图下方',
        'home_cards_top' => '首页文章卡片上方',
        'home_cards_middle' => '首页文章卡片中间（插入第 N 卡后）',
        'home_cards_bottom' => '首页文章卡片下方',
        'article_top' => '文章标题下方',
        'article_inline' => '文章段落间（按段落数注入）',
        'article_before_related' => '相关文章上方',
        'article_bottom' => '文章末尾（相关文章下方）',
        'comments_above' => '评论区上方',
        'comments_below' => '评论区下方',
        'sidebar' => '侧边栏卡位',
    ];

    /** 广告类型。 */
    public const TYPES = [
        'text' => '文本广告',
        'image' => '图片广告',
        'combo' => '图文结合广告',
        'card' => '文章卡片式广告',
        'html' => 'HTML / JS 代码',
    ];
}
