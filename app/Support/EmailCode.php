<?php

namespace App\Support;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Mail;

/**
 * 邮箱验证码（§邮件功能，后台可开关）：
 * - 注册验证码：user_reg_email_code_enabled
 * - 修改密码验证码：user_password_email_code_enabled
 * 发送经 Laravel Mail（驱动由站点设置控制：log/smtp/resend）。
 * 验证码 6 位数字，10 分钟有效，同一场景+邮箱 60 秒限发一次。
 *
 * 安全约束（P1-1 加固）：
 * - 缓存只存验证码的 SHA-256 摘要，不落明文（缓存落 Redis/database 时，
 *   任何有缓存读权限的人都无法直接读出验证码）；
 * - 校验失败计数：同一场景+邮箱连续失败 ≥ 5 次即作废验证码并锁定 15 分钟，
 *   封住「6 位数字 + 10 分钟有效期 + 无失败限制」的穷举面；
 * - 发送端还有 60 秒限发（配合 throttle:email-code 命名限流器）。
 *
 * 刻意不做「会话绑定」：用户在手机取码、回到电脑提交是常见场景，
 * 绑定 session 会误伤；穷举风险已由失败计数与路由限流覆盖。
 */
final class EmailCode
{
    public const TTL_SECONDS = 600;

    public const RESEND_INTERVAL = 60;

    /** 连续失败多少次后作废验证码。 */
    public const MAX_ATTEMPTS = 5;

    /** 触发失败上限后的锁定时长（秒）。 */
    public const LOCKOUT_SECONDS = 900;

    public static function registerEnabled(): bool
    {
        return SiteSettings::bool('user_reg_email_code_enabled');
    }

    public static function passwordEnabled(): bool
    {
        return SiteSettings::bool('user_password_email_code_enabled');
    }

    /**
     * 发送验证码邮件。失败抛 RuntimeException（由调用方转提示）。
     *
     * 返回本次生成的验证码明文——缓存里只留摘要，明文不落盘；
     * 调用方与测试可直接使用返回值，无需回读缓存。
     *
     * @throws \RuntimeException
     */
    public static function send(string $scene, string $email): string
    {
        $email = strtolower(trim($email));

        if (! filter_var($email, FILTER_VALIDATE_EMAIL)) {
            throw new \RuntimeException('邮箱格式不正确。');
        }

        $throttleKey = "email_code_sent:{$scene}:{$email}";

        if (Cache::has($throttleKey)) {
            throw new \RuntimeException('发送过于频繁，请稍后再试。');
        }

        if (self::lockedOut($scene, $email)) {
            throw new \RuntimeException('尝试次数过多，请 15 分钟后再试。');
        }

        $code = (string) random_int(100000, 999999);

        Mail::raw(
            "【".config('app.name', 'CMSForum')."】您的验证码是：{$code}（10 分钟内有效）。\n\n".
            "如非本人操作，请忽略本邮件。",
            function ($message) use ($email) {
                $message->to($email)->subject('邮箱验证码');
            },
        );

        // 只存摘要，避免明文验证码泄露（缓存落 Redis/database 时尤其重要）
        Cache::put(self::codeKey($scene, $email), self::hash($code), self::TTL_SECONDS);

        Cache::put($throttleKey, 1, self::RESEND_INTERVAL);
        Cache::forget(self::failsKey($scene, $email));

        return $code;
    }

    /**
     * 校验并消费验证码；不匹配/为空抛 ValidationException（空码不消费缓存，
     * 避免"未填写"的先行请求把已发送验证码作废）。
     *
     * @throws \Illuminate\Validation\ValidationException
     */
    public static function verify(string $scene, string $email, string $code): void
    {
        $email = strtolower(trim($email));
        $code = trim($code);

        if ($code === '') {
            throw \Illuminate\Validation\ValidationException::withMessages([
                'email_code' => '请填写邮箱验证码。',
            ]);
        }

        if (self::lockedOut($scene, $email)) {
            throw \Illuminate\Validation\ValidationException::withMessages([
                'email_code' => '尝试次数过多，请 15 分钟后重新获取验证码。',
            ]);
        }

        $key = self::codeKey($scene, $email);
        $stored = Cache::get($key);

        if ($stored === null) {
            throw \Illuminate\Validation\ValidationException::withMessages([
                'email_code' => '邮箱验证码错误或已过期，请重新获取。',
            ]);
        }

        // 兼容升级前遗留的明文记录（6 位明文 → 先哈希再比对）
        $stored = (string) $stored;
        $expectedHash = preg_match('/^[a-f0-9]{64}$/', $stored) === 1 ? $stored : self::hash($stored);

        if (! hash_equals($expectedHash, self::hash($code))) {
            // 用 get+put 而非 increment：不同缓存驱动对「键不存在时自增」语义不一致
            $failsKey = self::failsKey($scene, $email);
            $fails = (int) Cache::get($failsKey, 0) + 1;
            Cache::put($failsKey, $fails, self::TTL_SECONDS);

            if ($fails >= self::MAX_ATTEMPTS) {
                Cache::forget($key);
                Cache::put(self::lockKey($scene, $email), 1, self::LOCKOUT_SECONDS);
                Cache::forget($failsKey);

                throw \Illuminate\Validation\ValidationException::withMessages([
                    'email_code' => '错误次数过多，验证码已作废，请重新获取。',
                ]);
            }

            throw \Illuminate\Validation\ValidationException::withMessages([
                'email_code' => '邮箱验证码错误或已过期，请重新获取。',
            ]);
        }

        Cache::forget($key);
        Cache::forget(self::failsKey($scene, $email));
    }

    // ------------------------------------------------------------------
    // 内部
    // ------------------------------------------------------------------

    private static function hash(string $code): string
    {
        return hash('sha256', $code);
    }

    private static function codeKey(string $scene, string $email): string
    {
        return "email_code:{$scene}:{$email}";
    }

    private static function failsKey(string $scene, string $email): string
    {
        return "email_code_fails:{$scene}:{$email}";
    }

    private static function lockKey(string $scene, string $email): string
    {
        return "email_code_lock:{$scene}:{$email}";
    }

    private static function lockedOut(string $scene, string $email): bool
    {
        return Cache::has(self::lockKey($scene, $email));
    }
}
