<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Mock 收银台 · {{ config('app.name') }}</title>
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="flex min-h-screen items-center justify-center bg-slate-100 p-6">
    <div class="w-full max-w-md rounded-2xl border border-slate-200 bg-white p-8 shadow-sm">
        <p class="text-xs font-semibold uppercase tracking-wide text-amber-600">模拟收银台（仅本地联调）</p>
        <h1 class="mt-2 text-xl font-bold text-slate-900">CoinPayments Mock Checkout</h1>

        <dl class="mt-5 space-y-2 text-sm">
            <div class="flex justify-between"><dt class="text-slate-500">订单号</dt><dd class="font-mono">{{ $order->order_no }}</dd></div>
            <div class="flex justify-between"><dt class="text-slate-500">金额</dt><dd class="font-semibold">${{ number_format((float) $order->amount, 2) }} USD</dd></div>
            <div class="flex justify-between"><dt class="text-slate-500">文章 ID</dt><dd>{{ $order->post_id }}</dd></div>
            <div class="flex justify-between"><dt class="text-slate-500">状态</dt><dd>{{ $order->status }}</dd></div>
        </dl>

        @if ($order->status === 'paid')
            <p class="mt-6 rounded-lg bg-emerald-50 p-3 text-center text-sm font-medium text-emerald-700">✓ 已支付，文章已解锁</p>
        @else
            <form method="POST" action="{{ url()->current() }}?confirm=1" class="mt-6">
                @csrf
                <button type="submit"
                        class="w-full rounded-lg bg-indigo-600 px-4 py-2.5 text-sm font-medium text-white hover:bg-indigo-700">
                    模拟支付成功（{{ number_format((float) $order->amount, 2) }} USD）
                </button>
            </form>
        @endif

        <p class="mt-4 text-center text-xs text-slate-400">正式环境请关闭 Mock 模式并配置 CoinPayments 凭证</p>
    </div>
</body>
</html>
