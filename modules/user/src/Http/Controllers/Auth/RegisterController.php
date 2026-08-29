<?php

namespace Modules\User\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use Illuminate\Auth\Events\Registered;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules\Password;
use Illuminate\View\View;
use Modules\User\Events\UserRegistered;

class RegisterController extends Controller
{
    public function show(): View
    {
        return view('user::auth.register', [
            // 邀请码开关（invite 模块启用且后台开启时必填）
            'inviteRequired' => \Modules\Invite\Services\InviteService::registrationRequired(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'lowercase', 'email', 'max:255', 'unique:users,email'],
            'password' => ['required', 'confirmed', Password::defaults()],
            // 邮箱验证码（后台开关开启时必填；§邮件功能）
            'email_code' => \App\Support\EmailCode::registerEnabled()
                ? ['required', 'string', 'max:6']
                : ['nullable', 'string', 'max:6'],
            // 邀请码字段位：InviteCode 模块启用后由它校验/核销；未启用时不强制
            'invite_code' => ['nullable', 'string', 'max:64'],
            // 数学验证码（防机器人批量注册）
            'captcha_answer' => ['required', 'integer'],
        ]);

        \App\Support\Captcha::verify($request);

        // 注册邮箱验证码（后台可开关；§邮件功能）
        if (\App\Support\EmailCode::registerEnabled()) {
            \App\Support\EmailCode::verify('register', $data['email'], (string) ($data['email_code'] ?? ''));
        }

        // §九十九 邮箱白名单：开关开 + 白名单非空时，非白名单域名拒绝注册
        $whitelistEnabled = \App\Support\SiteSettings::bool('user_reg_whitelist_enabled');
        $allowedDomains = collect(explode(',', (string) \App\Support\SiteSettings::get('user_reg_whitelist_domains')))
            ->map(fn ($d) => strtolower(trim($d)))
            ->filter()
            ->values();

        if ($whitelistEnabled && $allowedDomains->isNotEmpty()) {
            $domain = strtolower(substr(strrchr($data['email'], '@') ?: '', 1));

            if (! $allowedDomains->contains($domain)) {
                throw \Illuminate\Validation\ValidationException::withMessages([
                    'email' => '该邮箱域名暂不支持注册，请使用主流邮箱（如 Gmail、QQ 邮箱等）。',
                ]);
            }
        }

        // 邀请码校验（invite 模块启用且后台开关开启）
        $inviteRequired = \Modules\Invite\Services\InviteService::registrationRequired();
        if ($inviteRequired) {
            app(\Modules\Invite\Services\InviteService::class)->validateForRegistration($data['invite_code'] ?? null);
        }

        $userModel = config('mksine.user_model', \App\Models\User::class);

        /** @var \Illuminate\Contracts\Auth\Authenticatable $user */
        $user = $userModel::create([
            'name' => $data['name'],
            'email' => $data['email'],
            'password' => Hash::make($data['password']),
        ]);

        event(new Registered($user));
        event(new UserRegistered($user, $data['invite_code'] ?? null));

        // 邀请码核销（校验已通过，标记已使用）
        if ($inviteRequired) {
            app(\Modules\Invite\Services\InviteService::class)->consume($data['invite_code'], (int) $user->getAuthIdentifier());
        }

        // 认领本会话下的游客支付订单（避免丢单）
        if (module_enabled('crypto-pay')) {
            app(\Modules\CryptoPay\Services\CoinPayService::class)
                ->claimGuestOrders($request->session()->getId(), $user);
        }

        Auth::login($user);

        return redirect()->intended('/');
    }
}
