<?php

namespace Modules\Quest\Tests;

use App\Models\User;
use Miran\Mksine\Core\Events\Posts\PostCreated;
use Modules\Economy\Services\WalletService;
use Modules\Quest\Models\QuestCheckin;
use Modules\Quest\Services\CheckinService;
use Modules\Tests\ModuleTestCase;

class QuestTest extends ModuleTestCase
{
    protected array $activeModules = ['user', 'economy', 'quest'];

    private CheckinService $service;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();

        // 种子任务规则
        \Illuminate\Support\Facades\DB::table('quests')->updateOrInsert(
            ['key' => 'daily_checkin'],
            ['name' => '每日签到', 'reward_currency' => 'gold', 'reward_amount' => 5, 'daily_limit' => 1, 'enabled' => 1],
        );
        \Illuminate\Support\Facades\DB::table('quests')->updateOrInsert(
            ['key' => 'publish_article'],
            ['name' => '发布文章', 'reward_currency' => 'gold', 'reward_amount' => 20, 'daily_limit' => 10, 'enabled' => 1],
        );

        $this->service = app(CheckinService::class);
        $this->user = User::factory()->create();
    }

    public function test_checkin_grants_reward(): void
    {
        $result = $this->service->checkin($this->user);

        $this->assertTrue($result['ok']);
        $this->assertSame(5.0, app(WalletService::class)->balance($this->user, 'gold'));
    }

    public function test_checkin_is_idempotent_per_day(): void
    {
        $first = $this->service->checkin($this->user);
        $second = $this->service->checkin($this->user);

        $this->assertTrue($first['ok']);
        $this->assertFalse($second['ok'], '同一天重复签到应被拒绝');
        $this->assertSame(5.0, app(WalletService::class)->balance($this->user, 'gold'), '重复签到不应重复发奖');
        $this->assertSame(1, QuestCheckin::where('user_id', $this->user->id)->count());
    }

    public function test_has_checked_in_today(): void
    {
        $this->assertFalse($this->service->hasCheckedInToday($this->user));
        $this->service->checkin($this->user);
        $this->assertTrue($this->service->hasCheckedInToday($this->user));
    }

    public function test_published_post_grants_reward_once(): void
    {
        $wallets = app(WalletService::class);

        // 模拟底座发布文章事件（published）
        event(new PostCreated(
            data: ['title' => '测试文章', 'status' => 'published'],
            context: ['post_id' => 101, 'user_id' => $this->user->id],
        ));

        $this->assertSame(20.0, $wallets->balance($this->user, 'gold'));

        // 同一 post_id 再次触发（如重放）不应重复发奖
        event(new PostCreated(
            data: ['title' => '测试文章', 'status' => 'published'],
            context: ['post_id' => 101, 'user_id' => $this->user->id],
        ));
        $this->assertSame(20.0, $wallets->balance($this->user, 'gold'));
    }

    public function test_draft_post_does_not_reward(): void
    {
        event(new PostCreated(
            data: ['title' => '草稿', 'status' => 'draft'],
            context: ['post_id' => 102, 'user_id' => $this->user->id],
        ));

        $this->assertSame(0.0, app(WalletService::class)->balance($this->user, 'gold'));
    }

    public function test_checkin_route_requires_auth_and_works(): void
    {
        // 未登录 → 重定向到登录页
        $resp = $this->post('/quest/checkin');
        $this->assertTrue($resp->isRedirect(), '未登录应被 auth 中间件拦截');

        // 登录后签到成功（JSON 请求）
        $this->actingAs($this->user);
        $resp2 = $this->postJson('/quest/checkin');
        $data = $resp2->json();
        $this->assertTrue($data['ok']);
        $this->assertNotNull($data['reward']);
    }
}
