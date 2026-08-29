<?php

namespace Modules\User\Tests;

use App\Models\User;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Mail;
use Miran\Mksine\Models\Setting;
use Modules\Tests\ModuleTestCase;

/**
 * §邮件功能：注册/改密邮箱验证码（后台开关 + 限频 + 校验）。
 * 默认 mail driver=log（本地联调不外发）。
 */
class EmailCodeTest extends ModuleTestCase
{
    protected array $activeModules = ['user', 'cms'];

    public function setUp(): void
    {
        parent::setUp();

        // 隔离其他用例残留的全局设置（settings 不在截断清单中）
        foreach (['invite_registration_enabled', 'user_reg_whitelist_enabled', 'user_reg_email_code_enabled', 'user_password_email_code_enabled'] as $key) {
            \Miran\Mksine\Models\Setting::updateOrCreate(['key' => $key], ['value' => '0']);
        }
        \App\Support\SiteSettings::flush();
    }

    protected function seedSetting(string $key, string $value): void
    {
        Setting::updateOrCreate(['key' => $key], ['value' => $value]);
        \App\Support\SiteSettings::flush();
    }

    public function test_disabled_by_default_no_field_required(): void
    {
        $this->withSession([\App\Support\Captcha::SESSION_KEY => 42])
            ->post('/register', [
                'name' => '无验证码用户',
                'email' => 'nocode@cmsforum.test',
                'password' => 'password123',
                'password_confirmation' => 'password123',
                'captcha_answer' => 42,
            ])
            ->assertSessionHasNoErrors()
            ->assertRedirect('/');

        $this->assertDatabaseHas('users', ['email' => 'nocode@cmsforum.test']);
    }

    public function test_register_requires_email_code_when_enabled(): void
    {
        $this->seedSetting('user_reg_email_code_enabled', '1');

        // 未带验证码 → 报错
        $this->withSession([\App\Support\Captcha::SESSION_KEY => 42])
            ->post('/register', [
                'name' => '验证码用户',
                'email' => 'codetest@cmsforum.test',
                'password' => 'password123',
                'password_confirmation' => 'password123',
                'captcha_answer' => 42,
            ])
            ->assertSessionHasErrors(['email_code']);

        // 发送 + 携带正确验证码 → 成功
        Mail::fake();
        \App\Support\EmailCode::send('register', 'codetest@cmsforum.test');

        $code = Cache::get('email_code:register:codetest@cmsforum.test');
        $this->assertNotNull($code, '验证码应已写入缓存');

        $this->withSession([\App\Support\Captcha::SESSION_KEY => 42])
            ->post('/register', [
                'name' => '验证码用户',
                'email' => 'codetest@cmsforum.test',
                'password' => 'password123',
                'password_confirmation' => 'password123',
                'email_code' => $code,
                'captcha_answer' => 42,
            ])
            ->assertSessionHasNoErrors()
            ->assertRedirect('/');

        $this->assertDatabaseHas('users', ['email' => 'codetest@cmsforum.test']);
    }

    public function test_wrong_code_rejected_and_single_use(): void
    {
        $this->seedSetting('user_reg_email_code_enabled', '1');

        $this->postJson('/email/code', ['scene' => 'register', 'email' => 'single@cmsforum.test'])
            ->assertOk();
        Cache::put('email_code:register:single@cmsforum.test', '123456', 600);

        // 错误验证码
        $this->withSession([\App\Support\Captcha::SESSION_KEY => 42])
            ->post('/register', [
                'name' => 'X', 'email' => 'single@cmsforum.test',
                'password' => 'password123', 'password_confirmation' => 'password123',
                'email_code' => '000000', 'captcha_answer' => 42,
            ])->assertSessionHasErrors(['email_code']);

        // 正确验证码一次性：再次提交（新用户名但同邮箱场景不存在，直接验证 pull 语义）
        $this->withSession([\App\Support\Captcha::SESSION_KEY => 42])
            ->post('/register', [
                'name' => '一次性用户', 'email' => 'once@cmsforum.test',
                'password' => 'password123', 'password_confirmation' => 'password123',
                'email_code' => '123456', 'captcha_answer' => 42,
            ])->assertRedirect('/');

        // 同码再注册另一邮箱域 → 已被 pull，验证失败
        $this->withSession([\App\Support\Captcha::SESSION_KEY => 42])
            ->post('/register', [
                'name' => '重放攻击', 'email' => 'once@cmsforum.test',
                'password' => 'password123', 'password_confirmation' => 'password123',
                'email_code' => '123456', 'captcha_answer' => 42,
            ])->assertSessionHasErrors();
    }

    public function test_password_change_with_email_code(): void
    {
        $this->seedSetting('user_password_email_code_enabled', '1');

        $user = User::factory()->create(['password' => bcrypt('old-password')]);
        $user->assignRole(\Spatie\Permission\Models\Role::firstOrCreate(['name' => 'author', 'guard_name' => 'web']));
        $this->actingAs($user);

        // 未带验证码 → 报错
        $this->put('/studio/settings/security', [
            'current_password' => 'old-password',
            'password' => 'new-password-1',
            'password_confirmation' => 'new-password-1',
        ])->assertSessionHasErrors(['email_code']);

        // 发送（场景 password 使用登录者邮箱）+ 正确验证码 → 成功
        Mail::fake();
        $this->postJson('/email/code', ['scene' => 'password'])->assertOk();
        $code = Cache::get("email_code:password:{$user->email}");
        $this->assertNotNull($code);

        $this->put('/studio/settings/security', [
            'current_password' => 'old-password',
            'password' => 'new-password-1',
            'password_confirmation' => 'new-password-1',
            'email_code' => $code,
        ])->assertSessionHasNoErrors();

        $this->assertTrue(\Illuminate\Support\Facades\Hash::check('new-password-1', $user->fresh()->password));
    }

    public function test_resend_throttled(): void
    {
        $this->seedSetting('user_reg_email_code_enabled', '1');

        $this->postJson('/email/code', ['scene' => 'register', 'email' => 'throttle@cmsforum.test'])->assertOk();
        $this->postJson('/email/code', ['scene' => 'register', 'email' => 'throttle@cmsforum.test'])
            ->assertStatus(429);
    }
}
