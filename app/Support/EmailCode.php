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
 */
final class EmailCode
{
    public const TTL_SECONDS = 600;

    public const RESEND_INTERVAL = 60;

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
     * @throws \RuntimeException
     */
    public static function send(string $scene, string $email): void
    {
        $email = strtolower(trim($email));

        if (! filter_var($email, FILTER_VALIDATE_EMAIL)) {
            throw new \RuntimeException('邮箱格式不正确。');
        }

        $throttleKey = "email_code_sent:{$scene}:{$email}";

        if (Cache::has($throttleKey)) {
            throw new \RuntimeException('发送过于频繁，请稍后再试。');
        }

        $code = (string) random_int(100000, 999999);

        Mail::raw(
            "【".config('app.name', 'CMSForum')."】您的验证码是：{$code}（10 分钟内有效）。\n\n".
            "如非本人操作，请忽略本邮件。",
            function ($message) use ($email) {
                $message->to($email)->subject('邮箱验证码');
            },
        );

        Cache::put("email_code:{$scene}:{$email}", $code, self::TTL_SECONDS);
        Cache::put($throttleKey, 1, self::RESEND_INTERVAL);
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
        $key = "email_code:{$scene}:{$email}";
        $expected = Cache::get($key);

        if (trim($code) === '' || ! $expected || ! hash_equals((string) $expected, trim($code))) {
            throw \Illuminate\Validation\ValidationException::withMessages([
                'email_code' => '邮箱验证码错误或已过期，请重新获取。',
            ]);
        }

        Cache::forget($key);
    }
}
