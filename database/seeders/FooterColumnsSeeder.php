<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Miran\Mksine\Models\Menu;
use Miran\Mksine\Models\MenuLocation;

/**
 * 页脚多列演示：3 列（产品/支持/关于），每列绑定独立菜单。
 * php artisan db:seed --class=FooterColumnsSeeder
 */
class FooterColumnsSeeder extends Seeder
{
    public function run(): void
    {
        $columns = [
            ['title' => '产品', 'slug' => 'footer-products', 'items' => [
                ['label' => '文章', 'url' => '/posts-list', 'order' => 1],
                ['label' => '教程', 'url' => '/posts-list', 'order' => 2],
            ]],
            ['title' => '支持', 'slug' => 'footer-support', 'items' => [
                ['label' => '社区动态', 'url' => '/posts-list', 'order' => 1],
                ['label' => '管理后台', 'url' => '/admin', 'order' => 2],
            ]],
            ['title' => '关于', 'slug' => 'footer-about', 'items' => [
                ['label' => '注册账号', 'url' => '/register', 'order' => 1],
                ['label' => '登录', 'url' => '/login', 'order' => 2],
            ]],
        ];

        foreach ($columns as $col) {
            $menu = Menu::query()->firstOrCreate(
                ['slug' => $col['slug']],
                ['name' => $col['title'].'菜单'],
            );

            if ($menu->items()->count() === 0) {
                $menu->items()->createMany(array_map(
                    fn ($item) => [...$item, 'type' => 'custom_link'],
                    $col['items'],
                ));
            }

            \App\Models\SiteFooterColumn::query()->firstOrCreate(
                ['title' => $col['title']],
                ['menu_id' => $menu->id, 'sort' => count($columns), 'enabled' => true],
            );
        }

        $this->command?->info('页脚多列演示完成：'.\App\Models\SiteFooterColumn::count().' 列');
    }
}
