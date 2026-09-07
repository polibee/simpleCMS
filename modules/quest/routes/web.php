<?php

use Illuminate\Support\Facades\Route;
use Modules\Quest\Http\Controllers\CheckinController;

/**
 * Quest 模块 Web 路由（由 MKSINE 插件系统自动加载，已包 web 中间件）。
 */

// 每日签到（限流见 RateLimitServiceProvider：签到本身有唯一约束，此处防刷接口与写放大）
Route::post('/quest/checkin', [CheckinController::class, 'store'])
    ->middleware(['auth', 'throttle:checkin'])
    ->name('quest.checkin');
