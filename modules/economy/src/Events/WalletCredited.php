<?php

namespace Modules\Economy\Events;

use Illuminate\Foundation\Events\Dispatchable;
use Modules\Economy\Models\WalletLedger;

/**
 * 钱包入账事件（credit）。
 * 幂等保证由 WalletService 处理；监听方（InviteCode/Notification）只读 ledger 数据。
 */
class WalletCredited
{
    use Dispatchable;

    public function __construct(
        public readonly WalletLedger $ledger,
    ) {}
}
