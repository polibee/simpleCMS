<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Spatie\Permission\Models\Role;

/**
 * 管理员账号保障（迁移/重装后运行，保证可登录后台）。
 * 账号与密码从 .env 读取：ADMIN_DEFAULT_EMAIL / ADMIN_DEFAULT_PASSWORD。
 *
 * 每次运行都会把该账号密码重置为 .env 中的值并授予 super_admin 角色。
 *
 * php artisan db:seed --class=AdminAccountSeeder
 */
class AdminAccountSeeder extends Seeder
{
    public function run(): void
    {
        $email = (string) env('ADMIN_DEFAULT_EMAIL', 'admin@cmsforum.test');
        $password = (string) env('ADMIN_DEFAULT_PASSWORD');

        if ($password === '') {
            $this->command?->warn('未配置 ADMIN_DEFAULT_PASSWORD，跳过管理员密码重置。');

            return;
        }

        $admin = \App\Models\User::query()->firstOrCreate(
            ['email' => $email],
            ['name' => 'Admin', 'password' => Hash::make($password)],
        );

        // 迁移保障：密码始终重置为 .env 配置值
        $admin->forceFill(['password' => Hash::make($password)])->save();

        $role = Role::firstOrCreate(['name' => 'super_admin', 'guard_name' => 'web']);
        if (! $admin->hasRole('super_admin')) {
            $admin->assignRole($role);
        }

        $this->command?->warn("管理员账号已就绪：{$email} / （见 .env ADMIN_DEFAULT_PASSWORD）");
    }
}
