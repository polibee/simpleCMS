<?php

namespace Modules\User\Tests;

use App\Models\User;
use Miran\Mksine\Models\Setting;
use Modules\Tests\ModuleTestCase;

/**
 * §九十九 注册邮箱白名单：开关开 + 白名单生效时拒绝非白名单域名；
 * 关闭或留空 = 不限制。
 */
class RegistrationWhitelistTest extends ModuleTestCase
{
    public function test_non_whitelisted_domain_rejected_when_enabled(): void
    {
        Setting::updateOrCreate(['key' => 'user_reg_whitelist_enabled'], ['value' => '1']);
        Setting::updateOrCreate(['key' => 'user_reg_whitelist_domains'], ['value' => 'gmail.com,qq.com']);
        \App\Support\SiteSettings::flush();

        $this->withSession([\App\Support\Captcha::SESSION_KEY => 42])
            ->post('/register', [
                'name' => '临时邮箱用户',
                'email' => 'spammer@tempmail.xyz',
                'password' => 'password123',
                'password_confirmation' => 'password123',
                'captcha_answer' => 42,
            ])
            ->assertSessionHasErrors(['email']);

        $this->assertDatabaseMissing('users', ['email' => 'spammer@tempmail.xyz']);
    }

    public function test_whitelisted_domain_can_register(): void
    {
        Setting::updateOrCreate(['key' => 'user_reg_whitelist_enabled'], ['value' => '1']);
        Setting::updateOrCreate(['key' => 'user_reg_whitelist_domains'], ['value' => 'gmail.com,qq.com']);
        \App\Support\SiteSettings::flush();

        $this->withSession([\App\Support\Captcha::SESSION_KEY => 42])
            ->post('/register', [
                'name' => '白名单用户',
                'email' => 'real@gmail.com',
                'password' => 'password123',
                'password_confirmation' => 'password123',
                'captcha_answer' => 42,
            ])
            ->assertRedirect('/');

        $this->assertDatabaseHas('users', ['email' => 'real@gmail.com']);
    }

    public function test_no_limit_when_disabled_or_empty(): void
    {
        // 关闭 = 不限制（任意域名可注册）
        Setting::updateOrCreate(['key' => 'user_reg_whitelist_enabled'], ['value' => '0']);
        Setting::updateOrCreate(['key' => 'user_reg_whitelist_domains'], ['value' => 'gmail.com']);
        \App\Support\SiteSettings::flush();

        $this->withSession([\App\Support\Captcha::SESSION_KEY => 42])
            ->post('/register', [
                'name' => '白名单关闭',
                'email' => 'anyone@custom.dev',
                'password' => 'password123',
                'password_confirmation' => 'password123',
                'captcha_answer' => 42,
            ])
            ->assertRedirect('/');

        $this->assertDatabaseHas('users', ['email' => 'anyone@custom.dev']);
        $this->assertSame(1, User::count());
    }
}
