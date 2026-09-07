<?php

use Illuminate\Support\Facades\Route;
use Modules\Shop\Http\Controllers\ShopController;

/**
 * 商城前台路由（MKSINE 自动加载，web 组）。
 * Xcash 回调 /shop/webhook 在 ShopPlugin::boot() 裸注册（无 CSRF）。
 */

Route::get('/shop', [ShopController::class, 'index'])->name('shop.index');
Route::get('/shop/orders', [ShopController::class, 'orders'])->middleware('auth')->name('shop.orders');
Route::get('/shop/paypal/return', [ShopController::class, 'paypalReturn'])->middleware('auth')->name('shop.paypal.return');
Route::get('/shop/{slug}', [ShopController::class, 'detail'])->name('shop.detail');
// 游客也可购买（须提供 guest_email 用于邮件交付 / 收据）
Route::post('/shop/buy', [ShopController::class, 'buy'])->name('shop.buy');

// Mock 收银台（联调）：GET 查看 / POST 确认支付
Route::match(['get', 'post'], '/shop/mock/{orderNo}', [ShopController::class, 'mockCheckout'])
    ->name('shop.mock.checkout');
// 码支付/虎皮椒 notify + notify/return 在 ShopPlugin::boot() 裸注册（无 CSRF）
