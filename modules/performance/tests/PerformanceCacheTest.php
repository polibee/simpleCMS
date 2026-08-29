<?php

namespace Modules\Performance\Tests;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Miran\Mksine\Models\Setting;
use Modules\Performance\Services\StatsService;
use Modules\Tests\ModuleTestCase;

/**
 * 页面缓存（WP Super Cache 式）：游客命中、登录绕过、后台排除、图表统计。
 */
class PerformanceCacheTest extends ModuleTestCase
{
    protected array $activeModules = ['user', 'cms', 'invite'];

    public function setUp(): void
    {
        parent::setUp();

        Setting::updateOrCreate(['key' => 'performance_page_cache_enabled'], ['value' => '1']);
        Setting::updateOrCreate(['key' => 'performance_page_cache_ttl'], ['value' => '3600']);
        Setting::updateOrCreate(['key' => 'invite_center_enabled'], ['value' => '1']);
        \App\Support\SiteSettings::flush();

        // 清空既有页面缓存（前次运行的暖缓存会污染命中统计）
        \Modules\Performance\Http\Middleware\PerformancePageCache::clearAll();
    }

    private function stats(): array
    {
        $row = DB::table('performance_stats')->where('date', now()->toDateString())->first();

        return [(int) ($row->hits ?? 0), (int) ($row->misses ?? 0)];
    }

    public function test_guest_second_request_is_cache_hit(): void
    {
        $this->get('/')->assertOk();

        [$hits, $misses] = $this->stats();
        $this->assertSame(1, $misses, '首个请求应为未命中');

        $this->get('/')->assertOk();
        [$hits] = $this->stats();
        $this->assertSame(1, $hits, '第二个请求应命中缓存');
    }

    public function test_logged_in_user_bypasses_cache(): void
    {
        $user = \App\Models\User::factory()->create();

        $this->actingAs($user)->get('/')->assertOk();
        [$hits, $misses] = $this->stats();
        $this->assertSame(0, $hits + $misses, '登录用户不参与页面缓存统计');
    }

    public function test_admin_pages_excluded(): void
    {
        $this->get('/admin/login')->assertOk();
        [$hits, $misses] = $this->stats();
        $this->assertSame(0, $hits + $misses, '后台路径不参与缓存');
    }

    public function test_nocache_query_bypasses(): void
    {
        $this->get('/?nocache=1')->assertOk();
        [$hits, $misses] = $this->stats();
        $this->assertSame(0, $hits + $misses, 'nocache 请求不缓存不计数');
    }

    public function test_invite_center_toggle(): void
    {
        $user = \App\Models\User::factory()->create();

        // 默认开启
        $this->actingAs($user)->get('/invite')->assertOk();

        // 关闭 → 404
        Setting::updateOrCreate(['key' => 'invite_center_enabled'], ['value' => '0']);
        \App\Support\SiteSettings::flush();
        $this->actingAs($user)->get('/invite')->assertNotFound();
    }
}
