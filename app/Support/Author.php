<?php

namespace App\Support;

use Illuminate\Contracts\Auth\Authenticatable;

/**
 * 创作中心门禁：管理员授予「author」「super_admin」角色之一的用户
 * 才允许进入写作 / 文章管理页面。前台 UI 与服务端中间件共用同一判定。
 */
final class Author
{
    /** 拥有写作权限的角色名单。 */
    public const AUTHOR_ROLES = ['author', 'super_admin'];

    /**
     * 当前登录用户是否具备创作权限。
     */
    public static function isAuthor(?Authenticatable $user = null): bool
    {
        $user ??= auth()->user();

        if (! $user) {
            return false;
        }

        foreach (self::AUTHOR_ROLES as $role) {
            try {
                if ($user->hasRole($role)) {
                    return true;
                }
            } catch (\Throwable) {
                // 角色表未就绪（迁移期）时视为无权限
            }
        }

        return false;
    }
}