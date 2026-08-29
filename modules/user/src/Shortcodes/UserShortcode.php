<?php

namespace Modules\User\Shortcodes;

use Illuminate\Support\Facades\Auth;
use Miran\Mksine\Core\Shortcodes\ShortcodeContext;
use Miran\Mksine\Core\Shortcodes\ShortcodeHandlerInterface;

/**
 * [user field="name" id=""] — 输出当前用户或指定用户的资料字段。
 * 字段：id / name / email / avatar / bio / website / signature / joined_at / posts_count
 */
class UserShortcode implements ShortcodeHandlerInterface
{
    public function handle(array $attrs, ?string $content, ShortcodeContext $context): string
    {
        $field = strtolower((string) ($attrs['field'] ?? 'name'));

        $user = null;
        if (isset($attrs['id']) && ctype_digit((string) $attrs['id'])) {
            $user = app(config('mksine.user_model', \App\Models\User::class))->query()->find((int) $attrs['id']);
        } else {
            $user = Auth::user();
        }

        if (! $user) {
            return '';
        }

        return match ($field) {
            'id' => (string) $user->getAuthIdentifier(),
            'name' => e((string) $user->name),
            'email' => e((string) $user->email),
            'avatar' => $this->avatar($user),
            'bio' => e((string) optional($user->profile)->bio),
            'website' => $this->website($user),
            'signature' => e((string) optional($user->profile)->signature),
            'joined_at' => e((string) $user->created_at?->toDateString()),
            'posts_count' => (string) ($user->posts_count ?? 0),
            default => '',
        };
    }

    private function avatar($user): string
    {
        $url = optional($user->profile)->avatar_media_id
            ? asset('storage/media/'.optional($user->profile)->avatar_media_id)
            : asset('images/avatar-placeholder.png');

        return '<img src="'.e($url).'" alt="'.e((string) $user->name).'" loading="lazy">';
    }

    private function website($user): string
    {
        $url = optional($user->profile)->website;
        if (! $url) {
            return '';
        }

        return '<a href="'.e($url).'" rel="nofollow noopener" target="_blank">'.e($url).'</a>';
    }
}
