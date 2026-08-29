<?php

namespace Modules\Economy\Tests;

use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Event;
use Modules\Economy\Events\WalletCredited;
use Modules\Economy\Events\WalletDebited;
use Modules\Economy\Models\WalletLedger;
use Modules\Economy\Services\WalletService;
use Modules\Tests\ModuleTestCase;

class WalletServiceTest extends ModuleTestCase
{
    use DatabaseTransactions;

    protected array $activeModules = ['user', 'economy'];

    private WalletService $service;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();

        $this->service = app(WalletService::class);
        $this->user = User::factory()->create();
    }

    public function test_credit_increases_balance(): void
    {
        $ledger = $this->service->apply($this->user, 'gold', 'credit', 100, 'test', 1, '测试入账');

        $this->assertNotNull($ledger);
        $this->assertSame(100.0, $this->service->balance($this->user, 'gold'));
        $this->assertSame('credit', $ledger->type);
        $this->assertSame(100.0, (float) $ledger->balance_after);
    }

    public function test_credit_is_idempotent(): void
    {
        $this->service->apply($this->user, 'gold', 'credit', 100, 'quest:checkin', 1, '签到');
        $second = $this->service->apply($this->user, 'gold', 'credit', 100, 'quest:checkin', 1, '重复签到');

        $this->assertNull($second, '同一 ref 重复入账应被幂等拦截');
        $this->assertSame(100.0, $this->service->balance($this->user, 'gold'));
        $this->assertSame(1, WalletLedger::where('ref_type', 'quest:checkin')->where('ref_id', '1')->count());
    }

    public function test_debit_decreases_balance(): void
    {
        $this->service->apply($this->user, 'gold', 'credit', 100, 'test', 1, '入账');
        $ledger = $this->service->apply($this->user, 'gold', 'debit', 30, 'invite:redeem', 1, '兑换');

        $this->assertNotNull($ledger);
        $this->assertSame(70.0, $this->service->balance($this->user, 'gold'));
        $this->assertSame('debit', $ledger->type);
    }

    public function test_debit_insufficient_balance_throws(): void
    {
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('余额不足');

        $this->service->apply($this->user, 'gold', 'debit', 10, 'invite:redeem', 1, '兑换');
    }

    public function test_debit_is_idempotent(): void
    {
        $this->service->apply($this->user, 'gold', 'credit', 100, 'test', 1, '入账');
        $this->service->apply($this->user, 'gold', 'debit', 30, 'invite:redeem', 1, '兑换');
        $second = $this->service->apply($this->user, 'gold', 'debit', 30, 'invite:redeem', 1, '重复兑换');

        $this->assertNull($second);
        $this->assertSame(70.0, $this->service->balance($this->user, 'gold'));
    }

    public function test_transfer_moves_balance(): void
    {
        $recipient = User::factory()->create();

        $this->service->apply($this->user, 'gold', 'credit', 100, 'test', 1, '入账');
        $this->service->transfer($this->user, $recipient, 'gold', 40, '转账');

        $this->assertSame(60.0, $this->service->balance($this->user, 'gold'));
        $this->assertSame(40.0, $this->service->balance($recipient, 'gold'));
    }

    public function test_events_dispatched(): void
    {
        Event::fake([WalletCredited::class, WalletDebited::class]);

        $this->service->apply($this->user, 'gold', 'credit', 50, 'test', 1, '入账');
        $this->service->apply($this->user, 'gold', 'debit', 10, 'test', 2, '扣减');

        Event::assertDispatched(WalletCredited::class);
        Event::assertDispatched(WalletDebited::class);
    }
}
