<?php

declare(strict_types=1);

namespace Modules\Invite\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Modules\Invite\Models\InviteCode;
use Modules\Invite\Models\InviteOrder;
use Modules\Invite\Services\InviteService;
use Modules\CryptoPay\Services\CodePayClient;
use Modules\CryptoPay\Services\XunHuPayClient;

/**
 * 邀请码中心（前台，登录用户）：我的邀请码 + 金币/加密购买。
 */
class InviteCenterController extends Controller
{
    // auth 中间件由路由组（modules/invite/routes/web.php）施加；
    // Laravel 13 控制器内 $this->middleware() 已移除

    public function index(Request $request): Response
    {
        // 后台开关：关闭后前台购买页 404（后台生成/注册核销不受影响）
        abort_unless(\App\Support\SiteSettings::get('invite_center_enabled', '1') !== '0', 404);

        $user = $request->user();

        $codes = InviteCode::query()
            ->where('created_by', $user->getAuthIdentifier())
            ->orderByDesc('id')
            ->limit(50)
            ->get()
            ->map(fn (InviteCode $c) => [
                'id' => $c->id,
                'code' => $c->code,
                'source' => $c->source,
                'status' => $c->status,
                'gold_spent' => $c->gold_spent,
                'paid_amount' => $c->paid_amount,
                'expires_at' => optional($c->expires_at)->toDateString(),
                'created_at' => optional($c->created_at)->toDateString(),
            ])->all();

        return Inertia::render('Invite/Center', [
            'codes' => $codes,
            'options' => [
                'allowGold' => \App\Support\SiteSettings::bool('invite_allow_gold'),
                'goldPrice' => InviteService::goldPrice(),
                'allowCrypto' => \App\Support\SiteSettings::bool('invite_allow_crypto'),
                'cryptoPrice' => InviteService::cryptoPrice(),
                'mockMode' => \App\Support\SiteSettings::bool('crypto_pay_mock_mode'),
                'payMethods' => \Modules\CryptoPay\Services\PaymentGateway::availableMethods(),
            ],
        ]);
    }

    public function purchaseGold(Request $request): RedirectResponse
    {
        try {
            $code = app(InviteService::class)->purchaseWithGold($request->user());

            return back()->with('success', '购买成功，邀请码：'.$code->code);
        } catch (\Throwable $e) {
            return back()->with('error', $e->getMessage());
        }
    }

    public function purchaseCrypto(Request $request): RedirectResponse
    {
        try {
            [, $payUrl, $kind] = app(InviteService::class)->purchaseWithCrypto(
                $request->user(),
                $request->input('method'),
            );

            // 二维码通道（码支付/虎皮椒扫码）→ 通用二维码页
            if ($kind === 'qrcode') {
                return redirect()->to(
                    \Modules\CryptoPay\Services\PaymentGateway::qrPageUrl($payUrl, '/invite'),
                );
            }

            return redirect()->to($payUrl);
        } catch (\Throwable $e) {
            return back()->with('error', $e->getMessage());
        }
    }

    /** PayPal 批准后回跳：capture → 标记支付。 */
    public function paypalReturn(Request $request): RedirectResponse
    {
        $token = (string) $request->query('token');

        if ($token === '') {
            return redirect()->to('/invite')->with('error', '缺少 PayPal 订单令牌。');
        }

        try {
            [$captured, $orderNo] = \Modules\CryptoPay\Services\PayPalClient::capture($token);
        } catch (\Throwable $e) {
            return redirect()->to('/invite')->with('error', 'PayPal 收款失败：'.$e->getMessage());
        }

        $order = InviteOrder::query()->where('order_no', $orderNo)->first();

        if ($captured && $order) {
            app(InviteService::class)->markOrderPaid($order);

            return redirect()->to('/invite')->with('success', '支付成功，邀请码已发放。');
        }

        return redirect()->to('/invite')->with('error', 'PayPal 订单未完成支付。');
    }

    /** 码支付/虎皮椒异步通知（GET/POST 通用）：尝试双通道验签。 */
    public function notify(Request $request): Response
    {
        $params = $request->isMethod('get') ? $request->query->all() : $request->post();
        $orderNo = '';

        // 码支付验签
        if (CodePayClient::configured() && CodePayClient::verifyNotify($params)) {
            $orderNo = (string) ($params['out_trade_no'] ?? '');
        }

        // 虎皮椒验签
        if ($orderNo === '' && XunHuPayClient::configured() && XunHuPayClient::verifyNotify($params, XunHuPayClient::notifyAppSecret())) {
            $orderNo = (string) ($params['trade_order_id'] ?? '');
        }

        if ($orderNo !== '') {
            $order = InviteOrder::query()->where('order_no', $orderNo)->first();
            if ($order) {
                app(InviteService::class)->markOrderPaid($order);
            }

            return response('success');
        }

        return response('fail', 200);
    }

    /** Mock 收银台（联调用）：POST 需登录 + 订单归属校验。 */
    public function mockCheckout(Request $request, string $orderNo): Response|RedirectResponse
    {
        $order = InviteOrder::query()->where('order_no', $orderNo)->firstOrFail();

        if ($request->isMethod('post')) {
            abort_unless($request->user() && (int) $order->user_id === (int) $request->user()->getAuthIdentifier(), 403);

            if ($order->status === 'pending') {
                app(InviteService::class)->markOrderPaid($order);
            }

            return redirect()->to('/invite')->with('success', '支付成功，邀请码已发放。');
        }

        return Inertia::render('Invite/MockCheckout', [
            'order' => [
                'order_no' => $order->order_no,
                'amount' => $order->amount,
                'currency' => $order->fiat_currency,
                'status' => $order->status,
            ],
        ]);
    }
}
