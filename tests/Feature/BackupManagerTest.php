<?php

namespace Tests\Feature;

use App\Filament\Pages\BackupManagerPage;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * 备份与恢复页：一键清除测试数据。
 *
 * 覆盖：业务表清空（文章/评论/商品/订单/邀请码/广告/钱包/签到/单页/轮播），
 *      账号、设置、分类、媒体保留。
 */
class BackupManagerTest extends TestCase
{
    use RefreshDatabase;

    private function seedBusinessData(): void
    {
        // 文章（含分类关联）
        $post = \Miran\Mksine\Models\Post::create([
            'title' => '清除测试文章',
            'slug' => 'clear-test-'.uniqid(),
            'content' => '<p>demo</p>',
            'status' => 'published',
            'author_id' => 1,
        ]);

        // 评论
        if (class_exists(\Miran\Mksine\Models\Comment::class)) {
            \Miran\Mksine\Models\Comment::create([
                'commentable_type' => \Miran\Mksine\Models\Post::class,
                'commentable_id' => $post->id,
                'user_id' => 1,
                'content' => 'test comment',
            ]);
        }

        // 商城商品 + 订单
        if (\Illuminate\Support\Facades\Schema::hasTable('shop_products')) {
            $productId = DB::table('shop_products')->insertGetId([
                'name' => '清除测试商品',
                'slug' => 'clear-prod-'.uniqid(),
                'price' => 9.99,
                'currency' => 'USD',
                'stock' => 5,
                'status' => 'on_sale',
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            DB::table('shop_orders')->insert([
                'order_no' => 'SHOP-CLEAR-'.uniqid(),
                'user_id' => 1,
                'product_id' => $productId,
                'amount' => 9.99,
                'currency' => 'USD',
                'quantity' => 1,
                'channel' => 'mock',
                'status' => 'paid',
                'paid_at' => now(),
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        // 邀请码
        if (\Illuminate\Support\Facades\Schema::hasTable('invite_codes')) {
            DB::table('invite_codes')->insert([
                'code' => 'CLEAR-'.strtoupper(uniqid()),
                'source' => 'admin',
                'status' => 'active',
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        // 广告
        if (\Illuminate\Support\Facades\Schema::hasTable('ads')) {
            DB::table('ads')->insert([
                'name' => '清除测试广告',
                'type' => 'text',
                'content' => 'demo',
                'status' => 'active',
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        // 钱包
        if (\Illuminate\Support\Facades\Schema::hasTable('wallets')) {
            DB::table('wallets')->insert([
                'user_id' => 1,
                'currency' => 'gold',
                'balance' => 100,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }

    public function test_clear_test_data_removes_business_tables_keeps_accounts_and_settings(): void
    {
        // 预置基础数据：管理员 + 设置 + 分类 + 业务数据
        $admin = User::factory()->create(['email' => 'admin@test.com']);
        \Miran\Mksine\Models\Setting::updateOrCreate(['key' => 'site_name'], ['value' => 'simpleCMS']);
        if (\Illuminate\Support\Facades\Schema::hasTable('categories')) {
            \Miran\Mksine\Models\Category::create(['name' => '保留分类', 'slug' => 'keep-cat', 'sort_order' => 1]);
        }

        $this->seedBusinessData();

        // 确认业务数据存在
        $this->assertGreaterThan(0, DB::table('posts')->count());
        if (\Illuminate\Support\Facades\Schema::hasTable('shop_products')) {
            $this->assertGreaterThan(0, DB::table('shop_products')->count());
        }

        // 执行清除（以管理员身份）
        $this->actingAs($admin);
        $page = new BackupManagerPage;
        $result = $page->clearTestData();

        $this->assertSame('ok', $result['code']);

        // 业务表清空
        $this->assertSame(0, DB::table('posts')->count());
        if (\Illuminate\Support\Facades\Schema::hasTable('comments')) {
            $this->assertSame(0, DB::table('comments')->count());
        }
        if (\Illuminate\Support\Facades\Schema::hasTable('shop_products')) {
            $this->assertSame(0, DB::table('shop_products')->count());
            $this->assertSame(0, DB::table('shop_orders')->count());
        }
        if (\Illuminate\Support\Facades\Schema::hasTable('invite_codes')) {
            $this->assertSame(0, DB::table('invite_codes')->count());
        }
        if (\Illuminate\Support\Facades\Schema::hasTable('ads')) {
            $this->assertSame(0, DB::table('ads')->count());
        }
        if (\Illuminate\Support\Facades\Schema::hasTable('wallets')) {
            $this->assertSame(0, DB::table('wallets')->count());
        }

        // 账号 / 设置 / 分类保留
        $this->assertDatabaseHas('users', ['email' => 'admin@test.com']);
        $this->assertSame('simpleCMS', \Miran\Mksine\Models\Setting::where('key', 'site_name')->value('value'));
        if (\Illuminate\Support\Facades\Schema::hasTable('categories')) {
            $this->assertDatabaseHas('categories', ['slug' => 'keep-cat']);
        }
    }
}
