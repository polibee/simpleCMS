<?php

namespace App\Http\Middleware;

use App\Support\Author;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * 保护创作中心路由：未登录跳转登录，登录但无作者角色 → 403。
 */
class AuthorMiddleware
{
    public function handle(Request $request, Closure $next): Response
    {
        if (! $request->user()) {
            return redirect()->guest(route('login'));
        }

        if (! Author::isAuthor($request->user())) {
            abort(403, '该操作需要作者权限，请联系管理员授予角色。');
        }

        return $next($request);
    }
}