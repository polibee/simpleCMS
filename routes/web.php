<?php

use App\Http\Controllers\AuthController;
use App\Http\Controllers\HomeController;
use Illuminate\Support\Facades\Route;

// CMS 文章路由（WordPress 式固定链接，读后台设置动态注册；
// 必须最先注册以优先于底座默认文章路由）
\Modules\CMS\Support\CmsRoutes::register();

// 前台登录/登出（Laravel 13 默认无 auth 脚手架，这里补齐）
Route::get('/login', [AuthController::class, 'showLogin'])->name('login');
Route::post('/login', [AuthController::class, 'login'])->name('login.store');
Route::post('/logout', [AuthController::class, 'logout'])->name('logout');

// 首页：Hero + 最新文章流 + 侧边栏
Route::get('/', [HomeController::class, 'index'])->name('home');

// 隐私政策 / 服务条款 / 关于 / 通用单页 → 由 CMS"单页管理"提供（见 modules/cms/routes/web.php）

// 邮箱验证码发送（注册/改密场景；后台开关 + 60s 限频，见 App\Support\EmailCode）
Route::post('/email/code', function (\Illuminate\Http\Request $request) {
    $data = $request->validate([
        'scene' => ['required', 'in:register,password'],
        'email' => ['required_unless:scene,password', 'nullable', 'string', 'email', 'max:255'],
    ]);

    if ($data['scene'] === 'password') {
        $user = $request->user();
        abort_unless($user && \App\Support\EmailCode::passwordEnabled(), 403);

        $email = $user->email;
    } else {
        abort_unless(\App\Support\EmailCode::registerEnabled(), 403);

        $email = (string) $data['email'];
    }

    try {
        \App\Support\EmailCode::send($data['scene'], $email);
    } catch (\Throwable $e) {
        return response()->json(['message' => $e->getMessage()], 429);
    }

    return response()->json(['message' => '验证码已发送']);
})->middleware('throttle:10,1')->name('email.code.send');

// 备份文件下载（super_admin；basename 强校验防路径穿越）
Route::get('/admin/backups/{name}/download', function (string $name) {
    $user = auth()->user();
    abort_unless($user && method_exists($user, 'hasRole') && $user->hasRole('super_admin'), 403);

    // basename 剥离任何目录成分，杜绝 ../ 穿越
    $safeName = basename($name);
    abort_unless($safeName === $name && preg_match('/^backup-[\w.-]+\.sql$/', $safeName), 404);

    $path = storage_path('app/backups/'.$safeName);
    abort_unless(is_file($path), 404);

    return response()->download($path, $safeName);
})->name('backup.download');

// 订单流水导出 CSV（super_admin；type=shop|crypto）
Route::get('/admin/export/orders/{type}', function (string $type) {
    $user = auth()->user();
    abort_unless($user && method_exists($user, 'hasRole') && $user->hasRole('super_admin'), 403);

    $fileName = 'orders-'.($type === 'crypto' ? 'crypto-pay' : 'shop').'-'.date('Ymd-His').'.csv';

    return response()->streamDownload(function () use ($type) {
        $out = fopen('php://output', 'w');
        // Excel 兼容 BOM
        fwrite($out, "\xEF\xBB\xBF");

        if ($type === 'crypto') {
            fputcsv($out, ['订单号', '商品/文章 ID', '买家 ID', '金额', '通道', '状态', '创建时间', '支付时间']);
            \Modules\CryptoPay\Models\CryptoOrder::query()
                ->orderByDesc('id')->chunk(500, function ($rows) use ($out) {
                    foreach ($rows as $o) {
                        fputcsv($out, [$o->order_no, $o->post_id, $o->user_id, $o->amount, $o->channel, $o->status, $o->created_at, $o->paid_at]);
                    }
                });
        } else {
            fputcsv($out, ['订单号', '商品', '买家 ID', '数量', '金额', '通道', '状态', '创建时间', '支付时间']);
            \Modules\Shop\Models\ShopOrder::query()->with('product:id,name')
                ->orderByDesc('id')->chunk(500, function ($rows) use ($out) {
                    foreach ($rows as $o) {
                        fputcsv($out, [$o->order_no, $o->product?->name ?? $o->product_id, $o->user_id, $o->quantity, $o->amount, $o->channel, $o->status, $o->created_at, $o->paid_at]);
                    }
                });
        }

        fclose($out);
    }, $fileName, [
        'Content-Type' => 'text/csv; charset=UTF-8',
    ]);
})->name('orders.export');
