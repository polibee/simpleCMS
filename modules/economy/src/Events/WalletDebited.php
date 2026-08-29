<?php

namespace Modules\Economy\Events;

use Illuminate\Foundation\Events\Dispatchable;
use Modules\Economy\Models\WalletLedger;

/**
 * 钱包扣减事件（debit）。
 */
class WalletDebited
{
    use Dispatchable;

    public function __construct(
        public readonly WalletLedger $ledger,
    ) {}
}
