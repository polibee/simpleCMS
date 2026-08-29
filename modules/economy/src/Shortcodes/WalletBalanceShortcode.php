<?php

namespace Modules\Economy\Shortcodes;

use Illuminate\Support\Facades\Auth;
use Miran\Mksine\Core\Shortcodes\ShortcodeContext;
use Miran\Mksine\Core\Shortcodes\ShortcodeHandlerInterface;
use Modules\Economy\Services\WalletService;

/**
 * [wallet_balance currency="gold"] — 输出当前用户指定货币余额（未登录显示 0）。
 */
class WalletBalanceShortcode implements ShortcodeHandlerInterface
{
    public function handle(array $attrs, ?string $content, ShortcodeContext $context): string
    {
        $currency = strtolower((string) ($attrs['currency'] ?? 'gold'));
        $user = Auth::user();

        if (! $user) {
            return '0';
        }

        $balance = app(WalletService::class)->balance($user, $currency);

        return number_format($balance, 0);
    }
}
