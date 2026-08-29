<?php

namespace Modules\Quest\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Modules\Quest\Services\CheckinService;

class CheckinController extends Controller
{
    public function store(CheckinService $service): RedirectResponse|JsonResponse
    {
        $user = auth()->user();

        if (! $user) {
            return request()->wantsJson()
                ? response()->json(['ok' => false, 'message' => '请先登录'], 401)
                : redirect()->route('login');
        }

        $result = $service->checkin($user);

        if (request()->wantsJson()) {
            return response()->json($result);
        }

        return back()->with('quest_checkin', $result['message']);
    }
}
