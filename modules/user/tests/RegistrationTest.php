<?php

namespace Modules\User\Tests;

use App\Models\User;
use Illuminate\Support\Facades\Event;
use Modules\Tests\ModuleTestCase;
use Modules\User\Events\UserRegistered;

class RegistrationTest extends ModuleTestCase
{
    public function setUp(): void
    {
        parent::setUp();

        // 隔离其它用例残留的全局开关（settings 表不参与截断）
        foreach (['invite_registration_enabled', 'user_reg_whitelist_enabled', 'user_reg_email_code_enabled'] as $key) {
            \Miran\Mksine\Models\Setting::updateOrCreate(['key' => $key], ['value' => '0']);
        }
        \App\Support\SiteSettings::flush();
    }

    public function test_registration_creates_user_and_dispatches_event(): void
    {
        Event::fake([UserRegistered::class]);

        // 预置验证码答案到 session，再提交匹配值
        $response = $this->withSession([\App\Support\Captcha::SESSION_KEY => 42])->post('/register', [
            'name' => '测试用户',
            'email' => 'test@cmsforum.test',
            'password' => 'password123',
            'password_confirmation' => 'password123',
            'invite_code' => 'INV-TEST',
            'captcha_answer' => 42,
        ]);

        $response->assertRedirect('/');
        $this->assertDatabaseHas('users', ['email' => 'test@cmsforum.test']);

        Event::assertDispatched(UserRegistered::class, function (UserRegistered $e) {
            return $e->user->email === 'test@cmsforum.test'
                && $e->inviteCode === 'INV-TEST';
        });
    }

    public function test_registration_validation_fails_does_not_create_user(): void
    {
        $response = $this->post('/register', [
            'name' => 'x',
            'email' => 'not-an-email',
            'password' => 'short',
            'password_confirmation' => 'different',
        ]);

        $response->assertSessionHasErrors(['email', 'password']);
        $this->assertDatabaseCount('users', 0);
    }

    public function test_user_center_page_shows_user(): void
    {
        $user = User::factory()->create(['name' => 'Alice']);

        $response = $this->get("/users/{$user->id}");

        $response->assertOk();
        $response->assertSee('Alice');
    }
}
