@extends('layouts.app')

@section('title', $user->name)

@section('content')
<div class="mx-auto max-w-5xl px-4 py-8">
    <div class="grid grid-cols-1 gap-6 lg:grid-cols-12">
        {{-- 主栏：用户资料 --}}
        <main class="lg:col-span-8">
            <div class="rounded-2xl border border-slate-200 bg-white p-8 shadow-sm">
                <div class="flex items-center gap-4">
                    <div class="flex h-20 w-20 items-center justify-center rounded-full bg-indigo-100 text-2xl font-bold text-indigo-700">
                        {{ strtoupper(mb_substr($user->name, 0, 1)) }}
                    </div>
                    <div>
                        <h1 class="text-2xl font-semibold text-slate-900">{{ $user->name }}</h1>
                        <p class="text-sm text-slate-500">加入于 {{ $user->created_at?->toDateString() }}</p>
                    </div>
                </div>

                @if ($user->profile?->bio)
                    <p class="mt-6 text-slate-700">{{ $user->profile->bio }}</p>
                @endif

                @if ($user->profile?->website)
                    <p class="mt-3 text-sm">
                        <a href="{{ $user->profile->website }}" target="_blank" rel="nofollow noopener"
                           class="text-indigo-600 hover:text-indigo-500">{{ $user->profile->website }}</a>
                    </p>
                @endif

                @if ($user->profile?->signature)
                    <p class="mt-4 border-t border-slate-100 pt-4 text-sm italic text-slate-500">{{ $user->profile->signature }}</p>
                @endif
            </div>
        </main>

        {{-- 侧边栏：作者中心卡片（User 模块提供，CMS/Forum 可复用） --}}
        <aside class="lg:col-span-4">
            <div class="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm">
                <h2 class="text-sm font-semibold uppercase tracking-wide text-slate-500">关于作者</h2>
                <dl class="mt-4 space-y-2 text-sm">
                    <div class="flex justify-between">
                        <dt class="text-slate-500">昵称</dt>
                        <dd class="text-slate-900">{{ $user->name }}</dd>
                    </div>
                    <div class="flex justify-between">
                        <dt class="text-slate-500">注册时间</dt>
                        <dd class="text-slate-900">{{ $user->created_at?->toDateString() }}</dd>
                    </div>
                </dl>
            </div>
        </aside>
    </div>
</div>
@endsection
