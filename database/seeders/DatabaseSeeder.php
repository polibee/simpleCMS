<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * 项目标准种子：管理员账号保障（含迁移后可登录）→ 作者角色 → CMS 演示数据。
     */
    public function run(): void
    {
        $this->call([
            AdminAccountSeeder::class,
            AuthorRoleSeeder::class,
            CmsDemoSeeder::class,
        ]);
    }
}
