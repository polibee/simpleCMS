<?php

namespace Modules\Shop\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ShopProduct extends Model
{
    protected $fillable = [
        'name',
        'slug',
        'description',
        'price',
        'currency',
        'stock',
        'sold',
        'image_url',
        'status',
        'category',
        'delivery_type',
        'delivery_payload',
    ];

    protected $casts = [
        'price' => 'float',
        'stock' => 'integer',
        'sold' => 'integer',
    ];

    public function orders(): HasMany
    {
        return $this->hasMany(ShopOrder::class);
    }

    public function isPurchasable(): bool
    {
        return $this->status === 'on_sale' && $this->stock > 0;
    }

    public function statusLabel(): string
    {
        return ['draft' => '草稿', 'on_sale' => '已上架', 'off_sale' => '已下架'][$this->status] ?? $this->status;
    }

    /** 交付类型标签。 */
    public function deliveryLabel(): string
    {
        return [
            'none' => '无',
            'download' => '下载链接',
            'content' => '文本内容',
            'invite_code' => '邀请码',
            'email' => '邮件交付',
        ][$this->delivery_type] ?? $this->delivery_type;
    }
}
