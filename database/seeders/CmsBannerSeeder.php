<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Modules\CMS\Models\CmsBanner;

/**
 * 首页演示轮播图（3 张，可后台"内容→首页轮播图"替换为自己的图片）。
 * php artisan db:seed --class=CmsBannerSeeder
 */
class CmsBannerSeeder extends Seeder
{
    public function run(): void
    {
        $banners = [
            ['title' => 'Laravel 13 新特性速览', 'image_url' => 'https://picsum.photos/id/0/1600/560', 'link_url' => '/articles/laravel-13-features', 'sort' => 1],
            ['title' => '用 shadcn/ui 打造现代界面设计系统', 'image_url' => 'https://picsum.photos/id/48/1600/560', 'link_url' => '/articles/shadcn-design-system', 'sort' => 2],
            ['title' => '模块化 CMS 架构实践', 'image_url' => 'https://picsum.photos/id/180/1600/560', 'link_url' => '/articles/modular-cms-architecture', 'sort' => 3],
        ];

        foreach ($banners as $b) {
            CmsBanner::query()->firstOrCreate(
                ['image_url' => $b['image_url']],
                [...$b, 'enabled' => true],
            );
        }

        $this->command?->info('演示轮播图完成：'.CmsBanner::count().' 张');
    }
}
