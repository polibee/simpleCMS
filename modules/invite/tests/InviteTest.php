<?php

namespace Modules\Invite\Tests;

use App\Models\User;
use Illuminate\Support\Facades\DB;
use Modules\Economy\Models\Wallet;
use Modules\Economy\Services\WalletService;
use Modules\Invite\Models\InviteCode;
use Modules\Invite\Services\InviteService;
use Modules\Tests\ModuleTestCase;

/**
 * 邀请码模块：管理员生成 / 注册开关校验 / 金币购买 / 加密 Mock 购买。
 */
class InviteTest extends ModuleTestCase
{
    protected array $activeModules = ['user', 'economy', 'quest', 'invite'];

    private function enableSettings(): void
    {
        foreach ([
            'invite_registration_enabled' => '1',
            'invite_allow_gold' => '1',
            'invite_gold_price' => '50',
            'invite_allow_crypto' => '1',
            'invite_crypto_price' => '1.99',
        ] as $k => $v) {
            \Miran\Mksine\Models\Setting::updateOrCreate(['key' => $k], ['value' => $v]);
        }
        \App\Support\SiteSettings::flush();
    }

    public function test_admin_generate_and_registration_requires_code(): void
    {
        $this->enableSettings();

        $codes = app(InviteService::class)->generate(3, 'admin');
        $this->assertCount(3, $codes);

        // 无邀请码注册 → 拒绝
        $this->withSession([\App\Support\Captcha::SESSION_KEY => 42])
            ->post('/register', [
                'name' => '无码用户', 'email' => 'noinvite@cmsforum.test',
                'password' => 'password123', 'password_confirmation' => 'password123',
                'captcha_answer' => 42,
            ])->assertSessionHasErrors(['invite_code']);

        // 无效码 → 拒绝
        $this->withSession([\App\Support\Captcha::SESSION_KEY => 42])
            ->post('/register', [
                'name' => '假码用户', 'email' => 'fakecode@cmsforum.test',
                'password' => 'password123', 'password_confirmation' => 'password123',
                'invite_code' => 'INV-FAKECODE1', 'captcha_answer' => 42,
            ])->assertSessionHasErrors(['invite_code']);

        // 有效码 → 注册成功且核销
        $this->withSession([\App\Support\Captcha::SESSION_KEY => 42])
            ->post('/register', [
                'name' => '有码用户', 'email' => 'withcode@cmsforum.test',
                'password' => 'password123', 'password_confirmation' => 'password123',
                'invite_code' => $codes[0]->code, 'captcha_answer' => 42,
            ])->assertRedirect('/');

        $this->assertDatabaseHas('users', ['email' => 'withcode@cmsforum.test']);
        $this->assertSame('used', $codes[0]->fresh()->status);

        // 已核销的码不能再注册
        $this->withSession([\App\Support\Captcha::SESSION_KEY => 42])
            ->post('/register', [
                'name' => '重用用户', 'email' => 'reuse@cmsforum.test',
                'password' => 'password123', 'password_confirmation' => 'password123',
                'invite_code' => $codes[0]->code, 'captcha_answer' => 42,
            ])->assertSessionHasErrors(['invite_code']);
    }

    public function test_registration_mode_off_allows_code_free_registration(): void
    {
        // 邀请注册关闭（注册限制卡片"不使用"）：无邀请码也能注册
        \Miran\Mksine\Models\Setting::updateOrCreate(['key' => 'invite_registration_enabled'], ['value' => '0']);
        \App\Support\SiteSettings::flush();

        $this->assertFalse(\Modules\Invite\Services\InviteService::registrationRequired());

        $this->withSession([\App\Support\Captcha::SESSION_KEY => 42])
            ->post('/register', [
                'name' => '开放用户', 'email' => 'open@cmsforum.test',
                'password' => 'password123', 'password_confirmation' => 'password123',
                'captcha_answer' => 42,
            ])->assertRedirect('/');

        $this->assertDatabaseHas('users', ['email' => 'open@cmsforum.test']);
    }

    public function test_gold_purchase_creates_code_and_debits_wallet(): void
    {
        $this->enableSettings();
        $user = User::factory()->create();

        // 先给金币（economy 配置由迁移默认）
        $wallet = app(WalletService::class);
        $wallet->apply($user, 'gold', 'credit', 200, 'test_setup', 'seed-1');

        $code = app(InviteService::class)->purchaseWithGold($user);

        $this->assertSame('gold', $code->source);
        $this->assertSame(50, $code->gold_spent);
        $this->assertSame((int) $user->id, (int) $code->created_by);
        $this->assertSame(150.0, $wallet->balance($user, 'gold'));
    }

    public function test_crypto_mock_purchase_flow(): void
    {
        $this->enableSettings();
        \Miran\Mksine\Models\Setting::updateOrCreate(['key' => 'crypto_pay_mock_mode'], ['value' => '1']);
        \App\Support\SiteSettings::flush();

        $user = User::factory()->create();

        [$order, $url] = app(InviteService::class)->purchaseWithCrypto($user);
        $this->assertSame('mock', $order->channel);
        $this->assertSame(1.99, $order->amount);
        $this->assertStringContainsString('/invite/mock/'.$order->order_no, $url);

        // Mock 收银台确认 → 支付 + 发码
        $this->actingAs($user)->post("/invite/mock/{$order->order_no}")->assertRedirect('/invite');
        $this->assertSame('paid', $order->fresh()->status);

        $code = InviteCode::query()->where('source', 'crypto')->where('created_by', $user->id)->first();
        $this->assertNotNull($code, '支付后应发放邀请码');
        $this->assertSame(1.99, $code->paid_amount);
    }
}
