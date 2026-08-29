<?php

namespace Modules\CMS\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Support\Captcha;
use Illuminate\Http\JsonResponse;

class CaptchaController extends Controller
{
    /** 生成验证码前端载荷（驱动可切换：math 题目 / turnstile sitekey）。 */
    public function show(): JsonResponse
    {
        return response()->json(\App\Support\Captcha::payload());
    }
}
