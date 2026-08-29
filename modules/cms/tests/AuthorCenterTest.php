<?php

namespace Modules\CMS\Tests;

use App\Models\User;
use Database\Seeders\AuthorRoleSeeder;
use Miran\Mksine\Models\Category;
use Modules\Tests\ModuleTestCase;

/**
 * 创作中心（前台作者）端到端：权限门禁 → 撰写发布 → 前台可见。
 */
class AuthorCenterTest extends ModuleTestCase
{
    protected array $activeModules = ['user', 'cms'];

    protected function setUp(): void
    {
        parent::setUp();
    }

    public function test_guest_is_redirected_to_login(): void
    {
        $this->get('/studio/posts')->assertRedirect(route('login'));
    }

    public function test_user_without_author_role_gets_403(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->get('/studio/posts')->assertForbidden();
        $this->actingAs($user)->get('/studio/settings')->assertForbidden();
    }

    public function test_author_can_create_and_publish_post_visible_on_frontend(): void
    {
        // 角色 + 授权（与生产一致：管理员在后台用户页勾选 author）
        $this->seed(AuthorRoleSeeder::class);
        $author = User::where('email', 'author@cmsforum.test')->first();

        // 前端入口 prop
        $this->actingAs($author)
            ->get('/')
            ->assertOk()
            ->assertInertia(fn ($page) => $page->where('auth.can_author', true));

        // 撰写并发布
        $category = Category::create(['name' => '测试分类', 'slug' => 'test-cat']);

        $response = $this->actingAs($author)->post('/studio/posts', [
            'title' => '我的第一篇创作中心文章',
            'excerpt' => '摘要内容',
            'status' => 'published',
            'content' => '<p>正文段落<script>alert(1)</script></p>',
            'category_ids' => [$category->id],
        ]);

        $response->assertRedirect('/studio/posts');
        $response->assertSessionHas('success');

        $post = \Miran\Mksine\Models\Post::query()->where('title', '我的第一篇创作中心文章')->first();
        $this->assertNotNull($post, '文章应已创建');
        $this->assertSame('published', $post->status);
        $this->assertSame((string) $author->id, (string) $post->author_id);
        $this->assertNotNull($post->published_at);

        // 服务端 HTML 清洗：<script> 标签必须被剥离，正文保留
        $this->assertStringNotContainsString('<script', (string) $post->content);
        $this->assertStringContainsString('正文段落', (string) $post->content);

        // 分类已关联
        $this->assertTrue($post->categories()->whereKey($category->id)->exists());

        // 前台可见（固定链接详情页：把 {slug}/{id} 占位符替换为真实 slug）
        $detailUrl = str_replace(
            ['{slug}', '{id}'],
            $post->slug,
            \Modules\CMS\Support\CmsPermalink::singlePostUrl(),
        );
        $front = $this->get($detailUrl);
        // 无 SSR：标题在 Inertia 页面数据中，用组件断言而非原文 assertSee
        $front->assertOk()->assertInertia(fn ($page) => $page
            // 组件文件在模块目录下，服务端默认路径校验不了 → 跳过存在性检查
            ->component('CMS/ArticleShow', false)
            ->where('article.title', '我的第一篇创作中心文章')
            ->where('article.author', '演示作者'));
    }

    public function test_draft_posts_are_hidden_from_public_listing(): void
    {
        $this->seed(AuthorRoleSeeder::class);
        $author = User::where('email', 'author@cmsforum.test')->first();

        $this->actingAs($author)->post('/studio/posts', [
            'title' => '未发布草稿文',
            'status' => 'draft',
            'content' => '<p>草稿正文</p>',
        ]);

        $post = \Miran\Mksine\Models\Post::query()->where('title', '未发布草稿文')->firstOrFail();
        $this->assertSame('draft', $post->status);
        $this->assertNull($post->published_at);

        // 公开列表不含草稿（Inertia 数据断言）
        $listUrl = \Modules\CMS\Support\CmsPermalink::postsUrl();
        $this->get($listUrl)->assertOk()->assertInertia(fn ($page) => $page
            ->component('CMS/ArticleIndex', false)
            ->where('posts.data', fn ($rows) => collect($rows)->every(fn ($row) => $row['title'] !== '未发布草稿文')));

        // 详情直接访问 404（占位符替换为真实 slug）
        $detailUrl = str_replace(
            ['{slug}', '{id}'],
            $post->slug,
            \Modules\CMS\Support\CmsPermalink::singlePostUrl(),
        );
        $this->get($detailUrl)->assertNotFound();
    }

    public function test_settings_save_markdown_bio_and_donation_then_profile_page_renders(): void
    {
        $this->seed(AuthorRoleSeeder::class);
        $author = User::query()->where('email', 'author@cmsforum.test')->firstOrFail();

        // 个人设置：Markdown 简介 + 创作者捐赠链接
        $this->actingAs($author)->put('/studio/settings', [
            'name' => '演示作者',
            'email' => 'author@cmsforum.test',
            'bio' => "## 关于我\n- 爱写 **Markdown**\n<script>alert(1)</script>",
            'website' => 'https://example.com',
            'donation_url' => 'https://donatr.ee/demo-author',
            'donation_text' => '请我喝咖啡',
        ])->assertSessionHasNoErrors();

        $profile = \Modules\User\Models\UserProfile::query()
            ->where('user_id', $author->id)->firstOrFail();
        $this->assertStringContainsString('**Markdown**', (string) $profile->bio); // 原始 Markdown 入库
        $this->assertSame('https://donatr.ee/demo-author', $profile->donation_url);

        // 个人主页：Markdown 渲染 + XSS 转义 + 捐赠信息下发
        $this->get("/users/{$author->id}")
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('User/ProfileShow', false)
                ->where('profileUser.name', '演示作者')
                ->where('profileUser.donation_url', 'https://donatr.ee/demo-author')
                ->where('profileUser.bio_html', fn ($v) => str_contains((string) $v, 'Markdown')
                    && str_contains((string) $v, '&lt;script&gt;') // 原始 HTML 被转义
                    && ! str_contains((string) $v, '<script>')));

        // 文章详情捐赠按钮：优先用作者自己的链接
        $post = \Miran\Mksine\Models\Post::create([
            'title' => '捐赠按钮优先级验证文',
            'slug' => 'donation-priority-post-'.uniqid(),
            'content' => '<p>x</p>',
            'status' => 'published',
            'author_id' => $author->id,
            'published_at' => now(),
        ]);
        $detailUrl = str_replace(['{slug}', '{id}'], $post->slug, \Modules\CMS\Support\CmsPermalink::singlePostUrl());
        $this->get($detailUrl)
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('CMS/ArticleShow', false)
                ->where('donation.url', 'https://donatr.ee/demo-author')
                ->where('donation.text', '请我喝咖啡'));
    }

    public function test_plugin_packager_creates_zip_for_download(): void
    {
        [$zipPath, $filename] = \App\Support\PluginPackager::package('cms');

        try {
            $this->assertFileExists($zipPath);
            $this->assertMatchesRegularExpression('/^cms-[\w.\-]+\.zip$/', $filename);
            $this->assertGreaterThan(1024, filesize($zipPath), '打包产物不应为空');
        } finally {
            @unlink($zipPath);
        }
    }

    public function test_author_cannot_edit_or_delete_others_posts(): void
    {
        $this->seed(AuthorRoleSeeder::class);
        $other = User::factory()->create();
        $intruder = User::factory()->create();
        $intruder->assignRole('author');

        $post = \Miran\Mksine\Models\Post::create([
            'title' => '他人文章',
            'slug' => 'others-post-'.uniqid(),
            'content' => '<p>x</p>',
            'status' => 'published',
            'author_id' => $other->id,
            'published_at' => now(),
        ]);

        $this->actingAs($intruder)->put("/studio/posts/{$post->id}", [
            'title' => '篡改标题',
            'status' => 'published',
            'content' => '<p>hacked</p>',
        ])->assertForbidden();

        $this->actingAs($intruder)->delete("/studio/posts/{$post->id}")->assertForbidden();

        $this->assertSame('他人文章', $post->fresh()->title);
    }
}
