<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>@yield('title', config('app.name', 'CMSForum')) · {{ config('app.name', 'CMSForum') }}</title>
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="min-h-screen bg-slate-50 font-sans text-slate-900 antialiased">
    <header class="border-b border-slate-200 bg-white">
        <div class="mx-auto flex max-w-7xl items-center justify-between px-4 py-4">
            <a href="{{ url('/') }}" class="text-lg font-bold text-slate-900">{{ config('app.name', 'CMSForum') }}</a>
            <nav class="flex items-center gap-4 text-sm">
                @auth
                    <a href="{{ route('users.show', auth()->id()) }}" class="text-slate-600 hover:text-slate-900">个人中心</a>
                    <form method="POST" action="{{ route('logout') }}">
                        @csrf
                        <button type="submit" class="text-slate-600 hover:text-slate-900">退出</button>
                    </form>
                @else
                    <a href="{{ route('login') }}" class="text-slate-600 hover:text-slate-900">登录</a>
                    <a href="{{ route('user.register') }}" class="rounded-lg bg-indigo-600 px-3 py-1.5 text-white hover:bg-indigo-700">注册</a>
                @endauth
            </nav>
        </div>
    </header>

    <main>
        @yield('content')
    </main>
</body>
</html>
