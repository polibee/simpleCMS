<?php

namespace Modules\Shop\Tests;

use App\Models\User;
use Illuminate\Support\Facades\DB;
use Modules\Shop\Models\ShopOrder;
use Modules\Shop\Models\ShopProduct;
use Modules\Shop\Services\ShopService;
use Modules\Tests\ModuleTestCase;

/**
 * 商城：上架/库存/购买闭环（Mock 通道）/订单流水。
 */
class ShopTest extends ModuleTestCase
{
    protected array $activeModules = ['user', 'shop'];

    public function setUp(): void
    {
        parent::setUp();

        DB::table('settings')->updateOrInsert(['key' => 'crypto_pay_mock_mode'], ['value' => '1']);
        \App\Support\SiteSettings::flush();
    }

    private function product(): ShopProduct
    {
        return ShopProduct::create([
            'name' => '测试商品 A',
            'slug' => 'test-product-a',
            'description' => '<p>商品介绍</p>',
            'price' => 9.99,
            'currency' => 'USD',
            'stock' => 5,
            'image_url' => 'https://picsum.photos/id/1/600/400',
            'status' => 'on_sale',
        ]);
    }

    public function test_off_sale_product_cannot_be_purchased(): void
    {
        $product = $this->product();
        $product->update(['status' => 'off_sale']);

        $user = User::factory()->create();
        // 商品查询仅匹配 on_sale → 下架商品视为不存在
        $this->actingAs($user)->post('/shop/buy', ['slug' => $product->slug])->assertNotFound();
    }

    public function test_mock_purchase_flow_deducts_stock_and_creates_paid_order(): void
    {
        $product = $this->product();
        $user = User::factory()->create();
        $this->actingAs($user);

        // 购买 → 跳转 Mock 收银台
        $resp = $this->post('/shop/buy', ['slug' => $product->slug]);
        $order = ShopOrder::query()->where('user_id', $user->id)->firstOrFail();
        $resp->assertRedirect("/shop/mock/{$order->order_no}");

        // Mock 收银台确认支付
        $this->post("/shop/mock/{$order->order_no}")->assertRedirect('/shop/orders');

        $order->refresh();
        $this->assertSame('paid', $order->status);
        $this->assertSame(4, $product->fresh()->stock, '库存应扣减 1');
        $this->assertSame(1, $product->fresh()->sold, '销量应 +1');

        // 我的订单页可见
        $this->get('/shop/orders')->assertOk()->assertInertia(fn ($page) => $page
            ->where('orders.0.order_no', $order->order_no));
    }

    public function test_stock_zero_blocks_checkout(): void
    {
        $product = $this->product();
        $product->update(['stock' => 0]);

        $user = User::factory()->create();

        $this->expectException(\RuntimeException::class);
        app(ShopService::class)->checkout($user, $product);
    }

    public function test_inertia_buy_returns_location_redirect_for_external_gateway(): void
    {
        $product = $this->product();
        $user = User::factory()->create();

        // Mock 通道：验证 Inertia XHR 分支（整页跳转收银台，而非 302 被 CORS 拦截）。
        // 外部网关（xcash/paypal）无真实凭据时走 mock 同样命中同一分支。
        DB::table('settings')->updateOrInsert(['key' => 'crypto_pay_mock_mode'], ['value' => '1']);
        \App\Support\SiteSettings::flush();

        $response = $this->actingAs($user)->post('/shop/buy', [
            'slug' => $product->slug,
            'method' => 'mock',
        ], ['X-Inertia' => '1', 'X-Inertia-Version' => '1']);

        // Inertia XHR 请求应返回 409 + X-Inertia-Location（整页跳转收银台），而非 302
        $response->assertStatus(409);
        $response->assertHeader('X-Inertia-Location');

        $order = ShopOrder::query()->where('user_id', $user->id)->firstOrFail();
        $this->assertSame('pending', $order->status);
        $this->assertSame('mock', $order->channel);
    }

    public function test_product_without_image_uses_default_placeholder(): void
    {
        $product = $this->product();
        $product->update(['image_url' => null]);

        $this->actingAs(User::factory()->create())
            ->get('/shop/'.$product->slug)
            ->assertInertia(fn ($page) => $page
                ->where('product.image_url', url('/images/product-placeholder.svg')));

        $this->get('/shop')
            ->assertInertia(fn ($page) => $page
                ->where('products.0.image_url', url('/images/product-placeholder.svg')));
    }
}
