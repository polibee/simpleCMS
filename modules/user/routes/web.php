<?php

use Illuminate\Support\Facades\Route;
use Modules\User\Http\Controllers\Auth\RegisterController;
use Modules\User\Http\Controllers\UserController;

/**
 * User 模块 Web 路由（由 MKSINE 插件系统自动加载，已包 web 中间件）。
 */

// 注册流程（含邀请码字段位，InviteCode 模块启用后接管核销）
// 限流（命名限流器 register）：防批量注册，也收窄邮箱验证码的穷举面
Route::get('/register', [RegisterController::class, 'show'])->name('user.register');
Route::post('/register', [RegisterController::class, 'store'])
    ->middleware('throttle:register')
    ->name('user.register.store');

// 用户中心 / 作者中心
Route::get('/users/{id}', [UserController::class, 'show'])->name('users.show');
