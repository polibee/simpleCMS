<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Spatie\Permission\Models\Role;

/**
 * 作者角色：管理员在后台「用户」编辑页勾选该角色后，用户即获得前台
 * 创作中心（撰写 / 管理文章）权限。演示作者 author@cmsforum.test 自动授予。
 *
 * php artisan db:seed --class=AuthorRoleSeeder
 */
class AuthorRoleSeeder extends Seeder
{
    public function run(): void
    {
        $role = Role::firstOrCreate(['name' => 'author', 'guard_name' => 'web']);

        $author = \App\Models\User::query()->firstOrCreate(
            ['email' => 'author@cmsforum.test'],
            ['name' => '演示作者', 'password' => Hash::make('password123')],
        );

        if (! $author->hasRole('author')) {
            $author->assignRole($role);
        }

        $this->command?->info('作者角色已就绪：'.Role::where('name', 'author')->count().' 个角色，作者 '.$author->email);
    }
}