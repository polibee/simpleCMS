<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Miran\Mksine\Models\Menu;
use Miran\Mksine\Models\MenuLocation;

/**
 * 前台默认导航菜单：页眉（首页/文章）+ 页脚（文章/后台）。
 * php artisan db:seed --class=NavigationSeeder
 */
class NavigationSeeder extends Seeder
{
    public function run(): void
    {
        // 位置（MenuLocationManager 注册的 key 同步入库）
        foreach (['header' => '页眉导航', 'footer' => '页脚导航'] as $key => $label) {
            MenuLocation::query()->firstOrCreate(['key' => $key], ['label' => $label]);
        }

        // 页眉菜单
        $header = Menu::query()->firstOrCreate(
            ['slug' => 'main-nav'],
            ['name' => '主导航'],
        );

        if ($header->items()->count() === 0) {
            $header->items()->createMany([
                ['label' => '首页', 'url' => '/', 'type' => 'custom_link', 'order' => 1],
                ['label' => '文章', 'url' => '/articles', 'type' => 'custom_link', 'order' => 2],
                ['label' => '教程', 'url' => '/articles', 'type' => 'custom_link', 'order' => 3],
            ]);
        }

        MenuLocation::query()->where('key', 'header')->first()?->assignMenu($header);

        // 页脚菜单
        $footer = Menu::query()->firstOrCreate(
            ['slug' => 'footer-nav'],
            ['name' => '页脚导航'],
        );

        if ($footer->items()->count() === 0) {
            $footer->items()->createMany([
                ['label' => '文章', 'url' => '/articles', 'type' => 'custom_link', 'order' => 1],
                ['label' => '注册', 'url' => '/register', 'type' => 'custom_link', 'order' => 2],
                ['label' => '管理后台', 'url' => '/admin', 'type' => 'custom_link', 'order' => 3],
            ]);
        }

        MenuLocation::query()->where('key', 'footer')->first()?->assignMenu($footer);

        $this->command?->info('导航菜单完成：header/footer 已分配默认菜单');
    }
}
