<?php

return [
    /*
    |--------------------------------------------------------------------------
    | 验证码驱动
    |--------------------------------------------------------------------------
    | math      ：自研数学算术验证码（无外部依赖，开发/低流量默认）
    | turnstile ：Cloudflare Turnstile（生产推荐；需下方 site_key/secret）
    |
    | 开发阶段保持 math 即可不接外部服务。
    */
    'driver' => env('CAPTCHA_DRIVER', 'math'),

    'turnstile' => [
        'site_key' => env('TURNSTILE_SITE_KEY'),
        'secret' => env('TURNSTILE_SECRET_KEY'),
    ],
];
