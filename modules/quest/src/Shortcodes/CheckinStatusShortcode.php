<?php

namespace Modules\Quest\Shortcodes;

use Illuminate\Support\Facades\Auth;
use Miran\Mksine\Core\Shortcodes\ShortcodeContext;
use Miran\Mksine\Core\Shortcodes\ShortcodeHandlerInterface;
use Modules\Quest\Services\CheckinService;

/**
 * [checkin_status] — 显示今日签到状态（已签到打勾 / 未登录提示登录）。
 */
class CheckinStatusShortcode implements ShortcodeHandlerInterface
{
    public function handle(array $attrs, ?string $content, ShortcodeContext $context): string
    {
        $user = Auth::user();

        if (! $user) {
            return '<a href="'.e(route('login')).'" class="text-indigo-600">登录后可签到</a>';
        }

        $checked = app(CheckinService::class)->hasCheckedInToday($user);

        return $checked
            ? '<span class="text-emerald-600">✓ 今日已签到</span>'
            : '<button type="button" data-checkin class="rounded bg-indigo-600 px-3 py-1 text-white">签到</button>';
    }
}
