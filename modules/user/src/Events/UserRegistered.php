<?php

namespace Modules\User\Events;

use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;
use Illuminate\Contracts\Auth\Authenticatable;

/**
 * 用户注册成功事件。
 * 载荷只携带必要 ID：InviteCode 模块监听后核销邀请码并写邀请关系；
 * Quest 监听后发新手奖励；Notification 监听后发欢迎通知。
 */
class UserRegistered
{
    use Dispatchable;
    use SerializesModels;

    public function __construct(
        public readonly Authenticatable $user,
        public readonly ?string $inviteCode = null,
    ) {}
}
