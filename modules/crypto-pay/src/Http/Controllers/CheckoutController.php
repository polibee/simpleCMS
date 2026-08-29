<?php

namespace Modules\CryptoPay\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Miran\Mksine\Models\Post;
use Symfony\Component\HttpFoundation\Response as SymfonyResponse;
use Modules\CryptoPay\Models\CryptoOrder;
use Modules\CryptoPay\Services\CoinPayService;

class CheckoutController extends Controller
{
    /**
     * 创建订单并跳转收银台。登录用户与游客均可购买；
     * 游客订单绑定 session（同会话解锁），注册后自动认领归属避免丢单。
     */
    public function store(Request $request, CoinPayService $pay): SymfonyResponse
    {
        $user = $request->user();
        $guestEmail = null;

        if (! $user) {
            $guestEmail = $request->validate([
                // 游客可选填邮箱，便于接收收据（不强制注册）
                'guest_email' => ['nullable', 'email', 'max:191'],
            ])['guest_email'] ?? null;
        }

        $data = $request->validate([
            'post_id' => ['required', 'integer'],
        ]);

        $post = Post::query()->where('status', 'published')->findOrFail($data['post_id']);

        try {
            /** @var CryptoOrder $order */
            [$order, $checkoutUrl] = $pay->createCheckout($user, $post, $guestEmail);
        } catch (\RuntimeException $e) {
            return back()->with('crypto_pay_error', $e->getMessage());
        }

        // Inertia 请求：外部收银台需要整页跳转
        if ($request->header('X-Inertia')) {
            return inertia()->location($checkoutUrl);
        }

        return redirect()->away($checkoutUrl);
    }

    /**
     * Mock 收银台（本地联调）：确认后直接标记支付成功并返回文章。
     */
    public function mockCheckout(string $orderNo, Request $request, CoinPayService $pay): mixed
    {
        if (! CoinPayService::mockMode()) {
            abort(404);
        }

        $order = CryptoOrder::query()->where('order_no', $orderNo)->firstOrFail();

        if ($request->boolean('confirm')) {
            $pay->markPaid($order, ['source' => 'mock']);

            $url = \Modules\CMS\Support\CmsPermalink::postUrl(
                Post::query()->find($order->post_id) ?? Post::query()->firstOrFail()
            );

            return redirect()->to($url)->with('crypto_pay_success', '支付成功，文章已解锁');
        }

        return response()->view('crypto-pay::mock-checkout', ['order' => $order]);
    }
}
