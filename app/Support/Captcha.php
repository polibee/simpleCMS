<?php

namespace App\Support;

use App\Support\SafeHttpClient;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Illuminate\Support\Facades\Http;

/**
 * 验证码门面（模块化，驱动可切换）：
 *
 * - math      ：自研数学算术验证码（session 存储，无外部依赖；开发默认）
 * - turnstile ：Cloudflare Turnstile（生产推荐；注册/登录/评论）
 *
 * 统一入口：
 * - Captcha::renderWidget()   表单内输出（Blade 用 {!! !!}）
 * - Captcha::verify(Request)  服务端校验（失败抛 ValidationException）
 * - Captcha::payload()        前端接口描述（math 题目 / turnstile sitekey）
 *
 * 安全约束：Turnstile siteverify 为服务端外呼，经 SafeHttpClient 校验
 * （仅 https、拒绝内网/保留地址、challenges.cloudflare.com 白名单）。
 */
final class Captcha
{
    public const SESSION_KEY = 'captcha_answer';

    /** 失败次数键（达到上限即强制重新出题，封住穷举面）。 */
    public const SESSION_FAILS = 'captcha_fails';

    /** 连续失败多少次后作废当前题目。 */
    public const MAX_FAILS = 5;

    private const TURNSTILE_VERIFY_URL = 'https://challenges.cloudflare.com/turnstile/v0/siteverify';

    public static function driver(): string
    {
        // 站点设置（后台→站点设置→Turnstile）优先于 env：启用且双 key 配齐即用 turnstile
        if (\App\Support\SiteSettings::bool('captcha_turnstile_enabled')
            && \App\Support\SiteSettings::get('captcha_turnstile_site_key')
            && \App\Support\SiteSettings::get('captcha_turnstile_secret_key')) {
            return 'turnstile';
        }

        return in_array(config('captcha.driver'), ['math', 'turnstile'], true)
            ? (string) config('captcha.driver')
            : 'math';
    }

    public static function turnstileEnabled(): bool
    {
        return self::driver() === 'turnstile'
            && (bool) self::turnstileSiteKey()
            && (bool) self::turnstileSecretKey();
    }

    /** Turnstile Site Key：站点设置优先，回退 env。 */
    public static function turnstileSiteKey(): string
    {
        return (string) (\App\Support\SiteSettings::get('captcha_turnstile_site_key')
            ?? config('captcha.turnstile.site_key', ''));
    }

    /** Turnstile Secret Key：站点设置优先，回退 env。 */
    private static function turnstileSecretKey(): string
    {
        return (string) (\App\Support\SiteSettings::get('captcha_turnstile_secret_key')
            ?? config('captcha.turnstile.secret', ''));
    }

    // ------------------------------------------------------------------
    // math 驱动
    // ------------------------------------------------------------------

    /**
     * 生成数学题存 session，返回题目文本（turnstile 模式返回空串）。
     *
     * 操作数取 -50..50 的随机整数，四种运算，答案空间远大于旧版 1..9 的
     * 加减（有效答案仅 19 种，可被脚本穷举 —— 见 P0-2）。
     */
    public static function generate(): string
    {
        if (self::turnstileEnabled()) {
            return '';
        }

        $op = random_int(0, 3);

        if ($op === 2) {
            // 乘法：控制位数，保证可心算
            $a = random_int(2, 12);
            $b = random_int(2, 9);
            $question = "{$a} × {$b}";
            $answer = $a * $b;
        } else {
            $a = random_int(-50, 50);
            $b = random_int(-50, 50);

            $question = match ($op) {
                0 => "{$a} + {$b}",
                1 => "{$a} - {$b}",
                default => "{$a} + {$b}",
            };

            $answer = match ($op) {
                0 => $a + $b,
                1 => $a - $b,
                default => $a + $b,
            };
        }

        session([
            self::SESSION_KEY => $answer,
            self::SESSION_FAILS => 0,
        ]);

        return $question.' = ?';
    }

    // ------------------------------------------------------------------
    // 统一输出 / 校验
    // ------------------------------------------------------------------

    /**
     * 表单内渲染验证码控件（Blade：{!! Captcha::renderWidget() !!}）。
     * turnstile：widget 容器 + 脚本；math：题目 + 输入框。
     */
    public static function renderWidget(): string
    {
        if (self::turnstileEnabled()) {
            $siteKey = e(self::turnstileSiteKey());

            return <<<HTML
            <div class="cf-turnstile" data-sitekey="{$siteKey}" data-theme="light"></div>
            <script src="https://challenges.cloudflare.com/turnstile/v0/api.js" async defer></script>
            HTML;
        }

        $question = e(self::generate());
        // Blade 指令经 {!! !!} 输出不会被编译，错误提示改用 PHP 直接取值
        $error = optional(session('errors'))->first('captcha_answer');
        $errorHtml = $error ? '<p class="mt-1 text-xs text-red-600">'.e($error).'</p>' : '';

        return <<<HTML
        <div>
            <label for="captcha_answer" class="block text-sm font-medium text-slate-700">
                验证码：<span class="font-mono text-indigo-600">{$question}</span>
            </label>
            <input id="captcha_answer" type="number" name="captcha_answer" required
                   class="mt-1 block w-full rounded-lg border border-slate-300 px-3 py-2 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
            {$errorHtml}
        </div>
        HTML;
    }

    /**
     * 校验请求（注册/登录/评论共用）；失败抛 ValidationException。
     * turnstile 校验 cf-turnstile-response；math 校验 captcha_answer。
     *
     * @throws ValidationException
     */
    public static function verify(Request $request): void
    {
        if (self::turnstileEnabled()) {
            self::verifyTurnstile($request);

            return;
        }

        self::verifyMath($request);
    }

    /**
     * 前端接口描述（游客评论表单拉取）：math 题目 / turnstile sitekey。
     *
     * @return array{type: string, question?: string, sitekey?: string}
     */
    public static function payload(): array
    {
        if (self::turnstileEnabled()) {
            return [
                'type' => 'turnstile',
                'sitekey' => self::turnstileSiteKey(),
            ];
        }

        return [
            'type' => 'math',
            'question' => self::generate(),
        ];
    }

    // ------------------------------------------------------------------
    // 内部校验
    // ------------------------------------------------------------------

    private static function verifyMath(Request $request): void
    {
        $expected = session(self::SESSION_KEY);
        $given = $request->input('captcha_answer');

        // 用后即焚，防重放
        session()->forget(self::SESSION_KEY);

        if ($expected === null || $given === null || (int) $given !== (int) $expected) {
            // 失败计数：达到上限即清空题目并要求重新获取（旧版可无限次重放同一题）
            $fails = (int) session(self::SESSION_FAILS, 0) + 1;
            session([self::SESSION_FAILS => $fails]);

            if ($fails >= self::MAX_FAILS) {
                session()->forget([self::SESSION_KEY, self::SESSION_FAILS]);

                throw ValidationException::withMessages([
                    'captcha_answer' => '错误次数过多，请刷新页面重新获取验证码。',
                ]);
            }

            throw ValidationException::withMessages([
                'captcha_answer' => '验证码不正确，请重试。',
            ]);
        }

        session()->forget(self::SESSION_FAILS);
    }

    private static function verifyTurnstile(Request $request): void
    {
        $token = (string) $request->input('cf-turnstile-response');

        if ($token === '') {
            throw ValidationException::withMessages([
                'captcha_answer' => '请先完成人机验证。',
            ]);
        }

        $verifyUrl = self::TURNSTILE_VERIFY_URL;
        // SSRF 约束：服务端外呼前校验（https + Cloudflare 官方域白名单 + 拒绝内网）
        SafeHttpClient::assertUrlAllowed($verifyUrl, ['challenges.cloudflare.com']);

        $response = Http::asForm()->timeout(10)->post($verifyUrl, [
            'secret' => self::turnstileSecretKey(),
            'response' => $token,
            'remoteip' => $request->ip(),
        ]);

        $result = $response->json();

        if (! ($result['success'] ?? false)) {
            throw ValidationException::withMessages([
                'captcha_answer' => '人机验证未通过，请重试。',
            ]);
        }
    }
}
