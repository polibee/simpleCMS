<?php

use Illuminate\Support\Facades\Route;
use Modules\CryptoPay\Http\Controllers\CheckoutController;
use Modules\CryptoPay\Http\Controllers\WebhookController;

/**
 * CryptoPay 插件路由（MKSINE 插件系统自动加载，web 中间件）。
 */

// 下单（登录用户或游客；游客订单绑定会话）→ 跳转收银台
Route::post('/crypto-pay/checkout', [CheckoutController::class, 'store'])
    ->name('crypto-pay.checkout');

// Mock 收银台（仅 Mock 模式可用；POST = 模拟支付确认）
Route::match(['get', 'post'], '/crypto-pay/mock/{orderNo}', [CheckoutController::class, 'mockCheckout'])
    ->name('crypto-pay.mock.checkout');
