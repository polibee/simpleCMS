<?php

use Illuminate\Support\Facades\Route;
use Modules\Invite\Http\Controllers\InviteCenterController;

/**
 * 邀请码模块 Web 路由（MKSINE 自动加载，web 中间件组）。
 * 支付回调 /invite/webhook 在 InvitePlugin::boot() 裸注册（无 CSRF）。
 */

Route::middleware(['auth'])->prefix('invite')->name('invite.')->group(function () {
    Route::get('/', [InviteCenterController::class, 'index'])->name('center');
    Route::post('/purchase/gold', [InviteCenterController::class, 'purchaseGold'])->name('purchase.gold');
    Route::post('/purchase/crypto', [InviteCenterController::class, 'purchaseCrypto'])->name('purchase.crypto');

    // Mock 收银台（联调）：GET 查看 / POST 确认支付
    Route::match(['get', 'post'], '/mock/{orderNo}', [InviteCenterController::class, 'mockCheckout'])
        ->name('mock.checkout');
});
