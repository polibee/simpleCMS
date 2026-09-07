<?php

namespace App\Providers;

use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;

/**
 * 全站频率限制（命名限流器）。
 *
 * 为什么不用行内 `throttle:N,1`：
 * Laravel 的 ThrottleRequests 对游客按 `域名|IP` 生成限流键（不含路由路径），
 * 因此所有行内 throttle 路由会**共用同一个计数桶**——任一被限流的请求都会消耗
 * 其它路由的额度， tighten 的最小阈值会变成全站上限，NAT/代理出口下还会误伤
 * 正常用户。命名限流器配合 Limit::by() 可为每条路由提供隔离的键。
 *
 * 用法：路由上写 `->middleware('throttle:login')` 等。
 */
class RateLimitServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        // 登录：按 IP + 目标邮箱限流，兼顾爆破与撞库
        RateLimiter::for('login', function (Request $request) {
            $email = strtolower((string) $request->input('email'));

            return Limit::perMinute(5)->by('login|'.$request->ip().'|'.$email);
        });

        // 注册：按 IP 限流（邮箱验证码另有 5 次失败锁定的细粒度保护）
        RateLimiter::for('register', function (Request $request) {
            return Limit::perMinute(5)->by('register|'.$request->ip());
        });

        // 邮箱验证码发送（叠加 EmailCode 内部 60 秒限发）
        RateLimiter::for('email-code', function (Request $request) {
            return Limit::perMinute(10)->by('email-code|'.$request->ip());
        });

        // 评论提交（游客可写，防刷屏）
        RateLimiter::for('comment', function (Request $request) {
            return Limit::perMinute(6)->by('comment|'.$request->ip());
        });

        // 签到：登录用户按用户 ID，避免同 IP 互相牵连
        RateLimiter::for('checkin', function (Request $request) {
            $key = $request->user()?->getAuthIdentifier() ?: $request->ip();

            return Limit::perMinute(10)->by('checkin|'.$key);
        });

        // 搜索：全表 LIKE 扫描，成本高于普通页面，单独收紧
        RateLimiter::for('search', function (Request $request) {
            return Limit::perMinute(30)->by('search|'.$request->ip());
        });

        // 登出
        RateLimiter::for('logout', function (Request $request) {
            return Limit::perMinute(10)->by('logout|'.$request->ip());
        });
    }
}
