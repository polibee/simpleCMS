<?php

namespace Modules\Ads\Tests;

use Illuminate\Support\Facades\Schema;
use Miran\Mksine\Models\Post;
use Modules\Ads\Models\Ad;
use Modules\Ads\Services\AdService;
use Modules\Tests\ModuleTestCase;

/**
 * Ads 插件：广告位 payload 按位置聚合 + 前端共享注入。
 */
class AdsTest extends ModuleTestCase
{
    protected array $activeModules = ['user', 'cms', 'ads'];

    public function test_ads_map_grouped_by_position_and_sorted(): void
    {
        $this->assertTrue(Schema::hasTable('ads'));

        Ad::create(['name' => 'a2', 'position' => 'banner_below', 'type' => 'text', 'title' => 'B', 'enabled' => true, 'sort' => 2]);
        Ad::create(['name' => 'a1', 'position' => 'banner_below', 'type' => 'text', 'title' => 'A', 'enabled' => true, 'sort' => 1]);
        Ad::create(['name' => 'a3', 'position' => 'banner_below', 'type' => 'text', 'title' => 'off', 'enabled' => false, 'sort' => 0]);
        Ad::create(['name' => 'a4', 'position' => 'article_inline', 'type' => 'image', 'image_url' => 'https://cdn.example.com/x.png', 'paragraph' => 2, 'enabled' => true, 'sort' => 0]);

        $map = AdService::mapForFrontend();

        $this->assertCount(2, $map['banner_below']);
        $this->assertSame('A', $map['banner_below'][0]['title']); // sort 升序
        $this->assertSame('B', $map['banner_below'][1]['title']);
        $this->assertSame(2, $map['article_inline'][0]['paragraph']);
        $this->assertArrayNotHasKey('comments_above', $map); // 无广告的位置不出现
    }

    public function test_ads_shared_to_frontend_pages(): void
    {
        Ad::create(['name' => 'inline', 'position' => 'article_inline', 'type' => 'text', 'title' => '段落广告', 'paragraph' => 1, 'enabled' => true, 'sort' => 0]);

        $author = \App\Models\User::create(['name' => '广告作者', 'email' => 'ads-author@cmsforum.test', 'password' => bcrypt('password123')]);

        $post = Post::create([
            'title' => '广告注入验证文',
            'slug' => 'ads-inject-post-'.uniqid(),
            'content' => '<p>第一段</p><p>第二段</p>',
            'status' => 'published',
            'author_id' => $author->id,
            'published_at' => now(),
        ]);

        $detailUrl = str_replace(
            ['{slug}', '{id}'],
            $post->slug,
            \Modules\CMS\Support\CmsPermalink::singlePostUrl(),
        );

        $this->get($detailUrl)
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->where('ads.article_inline.0.title', '段落广告')
                ->where('ads.article_inline.0.paragraph', 1));
    }

    public function test_ad_slot_sidebar_type_registered(): void
    {
        $manager = app(\App\Core\Sidebar\SidebarManager::class);
        $this->assertNotNull($manager->type('ad_slot'));
    }
}
