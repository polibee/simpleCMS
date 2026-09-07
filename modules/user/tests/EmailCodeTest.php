<?php

namespace Modules\User\Tests;

use App\Models\User;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Mail;
use Miran\Mksine\Models\Setting;
use Modules\Tests\ModuleTestCase;

/**
 * §邮件功能：注册/改密邮箱验证码（后台开关 + 限频 + 校验 + 防爆破）。
 * 默认 mail driver=log（本地联调不外发）。
 *
 * 说明：验证码在缓存中只存 SHA-256 摘要，因此测试通过 EmailCode::send() 的
 * 返回值取码，而不是回读缓存（见 P1-1 加固）。
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

    /** 注册请求体（含算术验证码：直接把答案写进 session）。 */
    private function register(array $overrides = [])
    {
        return $this->withSession([\App\Support\Captcha::SESSION_KEY => 42])
            ->post('/register', array_merge([
                'name' => '验证码用户',
                'email' => 'codetest@cmsforum.test',
                'password' => 'password123',
                'password_confirmation' => 'password123',
                'captcha_answer' => 42,
            ], $overrides));
    }

    public function test_disabled_by_default_no_field_required(): void
    {
        $this->register(['name' => '无验证码用户', 'email' => 'nocode@cmsforum.test'])
            ->assertSessionHasNoErrors()
            ->assertRedirect('/');

        $this->assertDatabaseHas('users', ['email' => 'nocode@cmsforum.test']);
    }

    public function test_register_requires_email_code_when_enabled(): void
    {
        $this->seedSetting('user_reg_email_code_enabled', '1');

        // 未带验证码 → 报错
        $this->register(['email' => 'codetest@cmsforum.test'])
            ->assertSessionHasErrors(['email_code']);

        // 发送 + 携带正确验证码 → 成功
        $code = \App\Support\EmailCode::send('register', 'codetest@cmsforum.test');
        $this->assertMatchesRegularExpression('/^\d{6}$/', $code);

        $this->register(['email' => 'codetest@cmsforum.test', 'email_code' => $code])
            ->assertSessionHasNoErrors()
            ->assertRedirect('/');

        $this->assertDatabaseHas('users', ['email' => 'codetest@cmsforum.test']);
    }

    public function test_code_is_not_stored_in_plaintext(): void
    {
        $this->seedSetting('user_reg_email_code_enabled', '1');
        \App\Support\EmailCode::send('register', 'hash@cmsforum.test');

        $cached = Cache::get('email_code:register:hash@cmsforum.test');

        $this->assertIsString($cached);
        $this->assertMatchesRegularExpression('/^[a-f0-9]{64}$/', $cached, '缓存中应只存 SHA-256 摘要，不落明文');
    }

    public function test_wrong_code_rejected_and_single_use(): void
    {
        $this->seedSetting('user_reg_email_code_enabled', '1');

        $code = \App\Support\EmailCode::send('register', 'single@cmsforum.test');

        // 错误验证码 → 拒绝
        $this->register(['email' => 'single@cmsforum.test', 'email_code' => '000000'])
            ->assertSessionHasErrors(['email_code']);

        // 正确验证码 → 通过
        $this->register(['name' => '一次性用户', 'email' => 'single@cmsforum.test', 'email_code' => $code])
            ->assertSessionHasNoErrors()
            ->assertRedirect('/');

        // 用后即焚：缓存中的验证码已被消费
        $this->assertNull(
            Cache::get('email_code:register:single@cmsforum.test'),
            '验证通过后验证码应被清除（一次性）',
        );
    }

    public function test_too_many_wrong_attempts_invalidates_code(): void
    {
        $this->seedSetting('user_reg_email_code_enabled', '1');

        $code = \App\Support\EmailCode::send('register', 'brute@cmsforum.test');

        // 直接调用服务层连续输错，避免受注册路由自身限流干扰
        for ($i = 0; $i < \App\Support\EmailCode::MAX_ATTEMPTS; $i++) {
            try {
                \App\Support\EmailCode::verify('register', 'brute@cmsforum.test', '000000');
                $this->fail('错误验证码不应通过');
            } catch (\Illuminate\Validation\ValidationException) {
                // 预期
            }
        }

        // 达到上限后验证码作废：即使输入正确码也拒绝
        try {
            \App\Support\EmailCode::verify('register', 'brute@cmsforum.test', $code);
            $this->fail('达到失败上限后正确验证码也应失效');
        } catch (\Illuminate\Validation\ValidationException) {
            // 预期
        }

        // 且进入锁定期，重新发送也被拒
        $this->expectException(\RuntimeException::class);
        \App\Support\EmailCode::send('register', 'brute@cmsforum.test');
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
        $code = \App\Support\EmailCode::send('password', $user->email);

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

    /**
     * 命名限流器隔离：/email/code 与 /register 不应共用计数桶。
     *
     * 行内 throttle:N,1 对游客按「域名|IP」计数（不含路径），会串桶；
     * 这里断言取验证码不会消耗注册额度。
     */
    public function test_email_code_and_register_have_separate_buckets(): void
    {
        $this->seedSetting('user_reg_email_code_enabled', '1');

        // 连续取 3 次验证码（不同邮箱，绕过 60s 限发）
        for ($i = 0; $i < 3; $i++) {
            $this->postJson('/email/code', ['scene' => 'register', 'email' => "bucket{$i}@cmsforum.test"])
                ->assertOk();
        }

        // 注册仍应可用（不被验证码请求的计数挤掉）
        $code = \App\Support\EmailCode::send('register', 'bucket-reg@cmsforum.test');
        $this->register(['email' => 'bucket-reg@cmsforum.test', 'email_code' => $code])
            ->assertSessionHasNoErrors()
            ->assertRedirect('/');
    }
}
