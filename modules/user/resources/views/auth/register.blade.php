@extends('layouts.app')

@section('title', '注册')

@section('content')
<div class="mx-auto max-w-md px-4 py-12">
    <div class="rounded-2xl border border-slate-200 bg-white p-8 shadow-sm">
        <h1 class="text-2xl font-semibold text-slate-900">注册账号</h1>
        <p class="mt-1 text-sm text-slate-500">加入 {{ config('app.name') }}，开始创作与交流。</p>

        <form method="POST" action="{{ route('user.register') }}" class="mt-6 space-y-4">
            @csrf

            <div>
                <label for="name" class="block text-sm font-medium text-slate-700">昵称</label>
                <input id="name" type="text" name="name" value="{{ old('name') }}" required autofocus
                       class="mt-1 block w-full rounded-lg border border-slate-300 px-3 py-2 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                @error('name') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
            </div>

            <div>
                <label for="email" class="block text-sm font-medium text-slate-700">邮箱</label>
                <input id="email" type="email" name="email" value="{{ old('email') }}" required
                       class="mt-1 block w-full rounded-lg border border-slate-300 px-3 py-2 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                @error('email') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
            </div>

            <div>
                <label for="password" class="block text-sm font-medium text-slate-700">密码</label>
                <input id="password" type="password" name="password" required
                       class="mt-1 block w-full rounded-lg border border-slate-300 px-3 py-2 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                @error('password') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
            </div>

            <div>
                <label for="password_confirmation" class="block text-sm font-medium text-slate-700">确认密码</label>
                <input id="password_confirmation" type="password" name="password_confirmation" required
                       class="mt-1 block w-full rounded-lg border border-slate-300 px-3 py-2 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
            </div>

            {{-- 邮箱验证码（后台开关 §邮件功能；开启时必填，点击发送到邮箱） --}}
            @if (\App\Support\EmailCode::registerEnabled())
                <div>
                    <label for="email_code" class="block text-sm font-medium text-slate-700">邮箱验证码 <span class="text-red-500">*</span></label>
                    <div class="mt-1 flex gap-2">
                        <input id="email_code" type="text" name="email_code" value="{{ old('email_code') }}" maxlength="6" inputmode="numeric"
                               class="block w-full rounded-lg border border-slate-300 px-3 py-2 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                        <button type="button" id="send-email-code"
                                class="shrink-0 rounded-lg border border-indigo-300 px-3 text-xs font-medium text-indigo-600 hover:bg-indigo-50">
                            发送验证码
                        </button>
                    </div>
                    <p id="email-code-tip" class="mt-1 hidden text-xs text-slate-500"></p>
                    @error('email_code') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                </div>
            @endif

            {{-- 验证码（驱动可切换：math / Cloudflare Turnstile） --}}
            {!! \App\Support\Captcha::renderWidget() !!}

            {{-- 邀请码：invite 模块 + 后台开关 → 必填；否则选填 --}}
            <div>
                <label for="invite_code" class="block text-sm font-medium text-slate-700">
                    邀请码 <span class="text-xs font-normal text-slate-400">（{{ $inviteRequired ?? false ? '必填' : '选填' }}）</span>
                </label>
                <input id="invite_code" type="text" name="invite_code" value="{{ old('invite_code') }}" maxlength="64"
                       class="mt-1 block w-full rounded-lg border border-slate-300 px-3 py-2 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                @error('invite_code') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
            </div>

            <button type="submit"
                    class="w-full rounded-lg bg-indigo-600 px-4 py-2 text-sm font-medium text-white shadow-sm hover:bg-indigo-700 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2">
                注册
            </button>
        </form>

        <p class="mt-4 text-center text-sm text-slate-500">
            已有账号？<a href="{{ route('login') }}" class="font-medium text-indigo-600 hover:text-indigo-500">登录</a>
        </p>
    </div>
</div>

{{-- 发送邮箱验证码（邮件功能开启时渲染） --}}
@if (\App\Support\EmailCode::registerEnabled())
<script>
    document.getElementById('send-email-code')?.addEventListener('click', async function () {
        const email = document.getElementById('email')?.value?.trim();
        const tip = document.getElementById('email-code-tip');
        const btn = this;
        if (!email) { tip.textContent = '请先填写邮箱'; tip.classList.remove('hidden'); return; }
        btn.disabled = true; btn.textContent = '发送中…'; tip.classList.add('hidden');
        try {
            const res = await fetch('{{ url('/email/code') }}', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content ?? document.querySelector('input[name="_token"]')?.value ?? '' },
                body: JSON.stringify({ scene: 'register', email }),
            });
            const data = await res.json().catch(() => ({}));
            if (!res.ok) { throw new Error(data.message || '发送失败'); }
            tip.textContent = '验证码已发送，10 分钟内有效；请查收邮箱（未收到请检查垃圾箱）。';
            tip.classList.remove('hidden');
            let left = 60;
            const timer = setInterval(() => {
                btn.textContent = left-- + 's 后重发';
                if (left < 0) { clearInterval(timer); btn.disabled = false; btn.textContent = '发送验证码'; }
            }, 1000);
        } catch (e) {
            tip.textContent = e.message; tip.classList.remove('hidden');
            btn.disabled = false; btn.textContent = '发送验证码';
        }
    });
</script>
@endif
@endsection
