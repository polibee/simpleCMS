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
            'image_url' => $p->image_url,
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
                'description' => $product->description,
                'price' => $product->price,
                'currency' => $product->currency,
                'stock' => $product->stock,
                'sold' => $product->sold,
                'image_url' => $product->image_url,
            ],
            'mockMode' => ShopService::mockMode(),
            // 用户可选支付方式（当前通道支持的方法列表）
            'payMethods' => \Modules\CryptoPay\Services\PaymentGateway::availableMethods(),
        ]);
    }

    public function buy(Request $request): RedirectResponse
    {
        $this->abortIfShopDisabled();
        $data = $request->validate([
            'slug' => ['required', 'string', 'max:220'],
            'method' => ['nullable', 'string', 'in:alipay,wxpay,qqpay'],
        ]);

        $product = ShopProduct::query()
            ->where('slug', $data['slug'])
            ->where('status', 'on_sale')
            ->firstOrFail();

        try {
            [$order, $payUrl, $kind] = app(ShopService::class)->checkout($request->user(), $product, $data['method'] ?? null);

            // 二维码通道（码支付/虎皮椒扫码）→ 通用二维码页
            if ($kind === 'qrcode') {
                return redirect()->to(
                    \Modules\CryptoPay\Services\PaymentGateway::qrPageUrl($payUrl, '/shop/orders'),
                );
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

    /** 码支付/虎皮椒异步通知（GET/POST 通用）：验签 → 标记支付 → 输出平台要求的应答。 */
    public function notify(Request $request): Response
    {
        $service = app(ShopService::class);
        $params = $request->isMethod('get') ? $request->query->all() : $request->post();
        $channel = (string) \App\Support\SiteSettings::get('crypto_pay_channel', '');

        // 码支付：MD5 验签 + TRADE_SUCCESS
        if ($channel === 'codepay') {
            if (\Modules\CryptoPay\Services\CodePayClient::verifyNotify($params)) {
                $order = ShopOrder::query()->where('order_no', (string) ($params['out_trade_no'] ?? ''))->first();
                if ($order) {
                    $service->markPaid($order);
                }

                return response('success');
            }

            return response('fail', 200);
        }

        // 虎皮椒：MD5 验签 + status=OD
        if ($channel === 'xunhupay') {
            if (\Modules\CryptoPay\Services\XunHuPayClient::verifyNotify($params, \Modules\CryptoPay\Services\XunHuPayClient::notifyAppSecret())) {
                $order = ShopOrder::query()->where('order_no', (string) ($params['trade_order_id'] ?? ''))->first();
                if ($order) {
                    $service->markPaid($order);
                }

                return response('success');
            }

            return response('fail', 200);
        }

        return response('unsupported channel', 200);
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
