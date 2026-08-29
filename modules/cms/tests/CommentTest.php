<?php

namespace Modules\CMS\Tests;

use App\Models\User;
use Miran\Mksine\Models\Comment;
use Miran\Mksine\Models\Post;
use Modules\Tests\ModuleTestCase;

class CommentTest extends ModuleTestCase
{
    protected array $activeModules = ['user', 'cms'];

    private User $user;

    private Post $post;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->create();
        $this->post = Post::query()->create([
            'title' => '评论测试文章',
            'slug' => 'comment-test-post',
            'content' => '<p>内容</p>',
            'status' => 'published',
            'author_id' => $this->user->id,
            'published_at' => now(),
        ]);
    }

    public function test_guest_cannot_comment(): void
    {
        $resp = $this->post('/articles/comment-test-post/comments', ['content' => '游客评论']);

        $this->assertTrue($resp->isRedirect());
        $this->assertSame(0, Comment::count());
    }

    public function test_user_can_comment_with_ua_tracking(): void
    {
        $this->actingAs($this->user);

        // math 验证码：预置答案到 session 再提交
        $resp = $this->withSession([\App\Support\Captcha::SESSION_KEY => 42])
            ->post('/articles/comment-test-post/comments', [
                'content' => '**加粗** 的 `Markdown` 评论',
                'captcha_answer' => 42,
            ], [
                'X-Forwarded-For' => '203.0.113.10',
                'User-Agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 Chrome/126.0 Safari/537.36',
            ]);

        $resp->assertRedirect();
        $this->assertSame(1, Comment::count());

        $comment = Comment::first();
        $this->assertSame($this->user->id, $comment->user_id);
        $this->assertStringContainsString('Chrome/126', $comment->user_agent);
        $this->assertSame('approved', $comment->status);

        // 详情页 props 带出 UA 识别结果与 Markdown 渲染 HTML
        $page = $this->get('/articles/comment-test-post');
        $page->assertOk();
        $body = $page->getContent();
        $this->assertStringContainsStringIgnoringCase('Chrome', $body);
        $this->assertStringContainsString('Windows', $body);
    }

    public function test_reply_creates_nested_comment(): void
    {
        $this->actingAs($this->user);

        $this->withSession([\App\Support\Captcha::SESSION_KEY => 42])
            ->post('/articles/comment-test-post/comments', [
                'content' => '主楼评论',
                'captcha_answer' => 42,
            ]);
        $parent = Comment::query()->whereNull('parent_id')->firstOrFail();

        $other = User::factory()->create(['name' => 'Bob']);
        $this->actingAs($other);
        $this->withSession([\App\Support\Captcha::SESSION_KEY => 42])
            ->post('/articles/comment-test-post/comments', [
                'content' => '楼中楼回复',
                'parent_id' => $parent->id,
                'captcha_answer' => 42,
            ]);

        $reply = Comment::query()->whereNotNull('parent_id')->firstOrFail();
        $this->assertSame($parent->id, $reply->parent_id);
        $this->assertSame($other->id, $reply->user_id);

        // 详情页树中包含回复（页面 JSON 为 unicode 转义，检查转义形式）
        $page = $this->get('/articles/comment-test-post');
        $this->assertStringContainsString('\u697c\u4e2d\u697c', $page->getContent()); // "楼中楼"
    }

    public function test_guest_can_comment_with_name_email_and_captcha(): void
    {
        $resp = $this->withSession([\App\Support\Captcha::SESSION_KEY => 42])
            ->post('/articles/comment-test-post/comments', [
                'content' => '游客评论内容',
                'author_name' => '路过的游客',
                'author_email' => 'guest@example.com',
                'captcha_answer' => 42,
            ]);

        $resp->assertRedirect();
        $comment = Comment::query()->where('content', '游客评论内容')->firstOrFail();
        $this->assertNull($comment->user_id);
        $this->assertSame('路过的游客', $comment->author_name);
        $this->assertSame('guest@example.com', $comment->author_email);
    }

    public function test_guest_comment_requires_captcha(): void
    {
        $resp = $this->from('/articles/comment-test-post')
            ->post('/articles/comment-test-post/comments', [
                'content' => '没有验证码的游客评论',
                'author_name' => 'bot',
                'author_email' => 'bot@example.com',
            ]);

        $resp->assertSessionHasErrors(['captcha_answer']);
        fwrite(STDERR, "\nDEBUG count=".Comment::count());
        $this->assertSame(0, Comment::count());
    }

    public function test_invalid_parent_rejected(): void
    {
        $this->actingAs($this->user);

        $resp = $this->from('/articles/comment-test-post')->post('/articles/comment-test-post/comments', [
            'content' => '无效父级',
            'parent_id' => 99999,
        ]);

        $resp->assertSessionHasErrors(['parent_id']);
        $this->assertSame(0, Comment::whereNotNull('parent_id')->count());
    }
}
