<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        // JS 写入的 Cookie 同意标记（未加密）需豁免解密，否则服务端读取不到
        $middleware->encryptCookies(except: [
            'cookie_consent',
        ]);

        $middleware->web(append: [
            \App\Http\Middleware\HandleInertiaRequests::class,
            // 性能优化：WP Super Cache 式整页缓存（含命中统计，图表数据源）
            \Modules\Performance\Http\Middleware\PerformancePageCache::class,
        ]);

        $middleware->alias([
            'author' => \App\Http\Middleware\AuthorMiddleware::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*') || $request->expectsJson(),
        );
    })->create();
