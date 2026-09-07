<?php

declare(strict_types=1);

namespace Modules\Shop\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Modules\Shop\Models\ShopOrder;
use Modules\Shop\Models\ShopProduct;
use Modules\Shop\Services\ShopService;

/**
 * 商城前台：商品列表 / 详情 / 购买 / 我的订单 / Mock 收银台 / PayPal 回跳。
 */
class ShopController extends Controller
{
    public function index(Request $request): Response
    {
        $this->abortIfShopDisabled();
        $query = ShopProduct::query()->where('status', 'on_sale');

        // 分类筛选
        if ($category = $request->query('category')) {
            $query->where('category', $category);
        }

        // 关键词搜索
        if ($q = trim((string) $request->query('q'))) {
            $query->where(fn ($sub) => $sub
                ->where('name', 'like', "%{$q}%")
                ->orWhere('description', 'like', "%{$q}%"));
        }

        $products = $query->orderByDesc('id')->get()->map(fn (ShopProduct $p) => [
            'id' => $p->id,
            'name' => $p->name,
            'slug' => $p->slug,
            'price' => $p->price,
            'currency' => $p->currency,
            'stock' => $p->stock,
            'sold' => $p->sold,
            'image_url' => $p->image_url ?: static::defaultProductImage(),
            'category' => $p->category,
            'url' => '/shop/'.$p->slug,
        ])->all();

        // 前端分类 Tab（有分类的商品才出 Tab）
        $categories = collect($products)->pluck('category')->filter()->unique()->sort()->values()->all();

        return Inertia::render('Shop/Index', [
            'products' => $products,
            'categories' => $categories,
            'mockMode' => ShopService::mockMode(),
            'payMethods' => \Modules\CryptoPay\Services\PaymentGateway::availableMethods(),
        ]);
    }

    public function detail(string $slug): Response
    {
        $this->abortIfShopDisabled();
        $product = ShopProduct::query()
            ->where('slug', $slug)
            ->where('status', 'on_sale')
            ->firstOrFail();

        return Inertia::render('Shop/Detail', [
            'product' => [
                'id' => $product->id,
                'name' => $product->name,
                'slug' => $product->slug,
                // 后台用 RichEditor 录入（存 HTML），前台 v-html 输出前必须过白名单清洗，
                // 否则一旦后台账号被入侵或权限配置过宽即为存储型 XSS（P2-5）
                'description' => \App\Support\HtmlSanitizer::clean($product->description),
                'price' => $product->price,
                'currency' => $product->currency,
                'stock' => $product->stock,
                'sold' => $product->sold,
                'image_url' => $product->image_url ?: static::defaultProductImage(),
            ],
            'mockMode' => ShopService::mockMode(),
            // 用户可选支付方式（当前通道支持的方法列表）
            'payMethods' => \Modules\CryptoPay\Services\PaymentGateway::availableMethods(),
        ]);
    }

    /** 商品未设置图片时的默认占位图（前后端统一用这张）。 */
    public static function defaultProductImage(): string
    {
        return url('/images/product-placeholder.svg');
    }

    public function buy(Request $request): RedirectResponse|\Symfony\Component\HttpFoundation\Response
    {
        $this->abortIfShopDisabled();
        $data = $request->validate([
            'slug' => ['required', 'string', 'max:220'],
            // 支付方式：CNY 通道（码支付/虎皮椒）用 alipay/wxpay/qqpay；
            // PayPal/crypto 通道由 PaymentGateway 自行路由，无需校验到具体值
            'method' => ['nullable', 'string', 'max:20'],
        ]);

        $product = ShopProduct::query()
            ->where('slug', $data['slug'])
            ->where('status', 'on_sale')
            ->firstOrFail();

        try {
            [$order, $payUrl, $kind] = app(ShopService::class)->checkout($request->user(), $product, $data['method'] ?? null);

            // 二维码通道（码支付/虎皮椒扫码）→ 通用二维码页
            if ($kind === 'qrcode') {
                $payUrl = \Modules\CryptoPay\Services\PaymentGateway::qrPageUrl($payUrl, '/shop/orders');
            }

            // Inertia XHR 请求必须用整页跳转：外部收银台（pay.xca.sh / PayPal）若直接
            // 返回 302，浏览器 XHR 跨域跟随会被 CORS 拦截，表现为「点了去支付没反应」
            if ($request->header('X-Inertia')) {
                return inertia()->location($payUrl);
            }

            return redirect()->to($payUrl);
        } catch (\Throwable $e) {
            return back()->with('error', $e->getMessage());
        }
    }

    public function orders(Request $request): Response
    {
        $orders = ShopOrder::query()
            ->where('user_id', $request->user()->getAuthIdentifier())
            ->with('product:id,name,slug')
            ->orderByDesc('id')
            ->limit(50)
            ->get()
            ->map(fn (ShopOrder $o) => [
                'id' => $o->id,
                'order_no' => $o->order_no,
                'product' => $o->product?->name ?? '—',
                'amount' => $o->amount,
                'currency' => $o->currency,
                'channel' => $o->channel,
                'status' => $o->status,
                'paid_at' => optional($o->paid_at)->toDateTimeString(),
                'created_at' => optional($o->created_at)->toDateTimeString(),
                'delivery' => $o->delivered_data,
            ])->all();

        return Inertia::render('Shop/Orders', [
            'orders' => $orders,
        ]);
    }

    public function mockCheckout(Request $request, string $orderNo): Response|RedirectResponse
    {
        $order = ShopOrder::query()->where('order_no', $orderNo)->firstOrFail();

        // 安全：POST 确认需要登录且是订单所有者
        if ($request->isMethod('post')) {
            abort_unless($request->user() && (int) $order->user_id === (int) $request->user()->getAuthIdentifier(), 403);

            if ($order->status === 'pending') {
                app(ShopService::class)->markPaid($order);
            }

            return redirect()->to('/shop/orders')->with('success', '支付成功。');
        }

        return Inertia::render('Shop/MockCheckout', [
            'order' => [
                'order_no' => $order->order_no,
                'amount' => $order->amount,
                'currency' => $order->currency,
                'status' => $order->status,
            ],
        ]);
    }

    /** PayPal 批准后回跳：capture → 标记支付。 */
    public function paypalReturn(Request $request): RedirectResponse
    {
        $token = (string) $request->query('token'); // PayPal orderId

        if ($token === '') {
            return redirect()->to('/shop')->with('error', '缺少 PayPal 订单令牌。');
        }

        try {
            [$captured, $orderNo] = \Modules\CryptoPay\Services\PayPalClient::capture($token);
        } catch (\Throwable $e) {
            return redirect()->to('/shop')->with('error', 'PayPal 收款失败：'.$e->getMessage());
        }

        $order = ShopOrder::query()->where('order_no', $orderNo)->first();

        if ($captured && $order) {
            app(ShopService::class)->markPaid($order);

            return redirect()->to('/shop/orders')->with('success', '支付成功。');
        }

        return redirect()->to('/shop/orders')->with('error', 'PayPal 订单未完成支付。');
    }

    /**
     * 码支付/虎皮椒异步通知（GET/POST 通用）：验签 → 校验金额 → 标记支付 → 输出平台要求的应答。
     *
     * 通道按**订单自身**的 channel 字段判定，而不是全局设置 crypto_pay_channel：
     * 后者在管理员切换支付通道后会让在途订单的回调全部失配，静默丢单（P1-2）。
     */
    public function notify(Request $request): Response
    {
        $service = app(ShopService::class);
        $params = $request->isMethod('get') ? $request->query->all() : $request->post();

        $orderNo = (string) ($params['out_trade_no'] ?? $params['trade_order_id'] ?? '');
        $order = $orderNo === '' ? null : ShopOrder::query()->where('order_no', $orderNo)->first();

        if (! $order) {
            return response('fail', 200);
        }

        // 已支付：直接应答成功（幂等，避免渠道重复通知造成重复处理）
        if ($order->status === 'paid') {
            return response('success');
        }

        $verified = match ($order->channel) {
            'codepay' => \Modules\CryptoPay\Services\CodePayClient::verifyNotify($params),
            'xunhupay' => \Modules\CryptoPay\Services\XunHuPayClient::verifyNotify(
                $params,
                \Modules\CryptoPay\Services\XunHuPayClient::notifyAppSecret(),
            ),
            default => false,
        };

        if (! $verified) {
            return response('fail', 200);
        }

        // 金额校验：以订单金额为准，避免金额被篡改的回调直接放行进账
        if (! $this->amountMatches($order, $params)) {
            \Illuminate\Support\Facades\Log::warning('商城回调金额与订单不符，已拒绝', [
                'order_no' => $order->order_no,
                'order_amount' => $order->amount,
                'params' => array_intersect_key($params, array_flip(['money', 'total_fee', 'amount', 'out_trade_no', 'trade_order_id'])),
            ]);

            return response('fail', 200);
        }

        $service->markPaid($order);

        return response('success');
    }

    /**
     * 回调金额与订单金额是否一致（容忍 0.01 的浮点/分位误差）。
     *
     * 取不到金额字段时视为通过——部分渠道的同步回跳不带金额，
     * 真正的入账以异步通知为准。
     *
     * @param  array<string, mixed>  $params
     */
    private function amountMatches(ShopOrder $order, array $params): bool
    {
        $paid = $params['money'] ?? $params['total_fee'] ?? $params['amount'] ?? null;

        if ($paid === null || $paid === '') {
            return true;
        }

        return abs((float) $paid - (float) $order->amount) <= 0.01;
    }

    /** 码支付/虎皮椒页面跳转通知（同步回跳，验证后直接转订单页）。 */
    public function notifyReturn(Request $request): RedirectResponse
    {
        $this->notify($request);

        return redirect()->to('/shop/orders');
    }

    private function abortIfShopDisabled(): void
    {
        abort_unless(\App\Support\SiteSettings::get('shop_enabled', '1') !== '0', 404);
    }
}
