<?php

namespace Modules\CMS\Http\Controllers\Author;

use App\Http\Controllers\Controller;
use App\Support\HtmlSanitizer;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules\Password;
use Inertia\Inertia;
use Inertia\Response;
use Miran\Mksine\Models\Category;
use Miran\Mksine\Models\Post;
use Modules\User\Models\UserProfile;

/**
 * 创作中心（前台作者）：撰写 / 管理自己的文章 + 个人设置 + 安全设置。
 * 路由经 auth + author 中间件保护，普通用户 403；越权编辑他人文章同样拒绝。
 */
class AuthorController extends Controller
{
    /** 编辑器可选的发布状态。 */
    private const STATUSES = ['draft', 'published'];

    // ------------------------------------------------------------------
    // 文章管理
    // ------------------------------------------------------------------

    public function index(Request $request): Response
    {
        $posts = Post::query()
            ->where('author_id', $request->user()->getAuthIdentifier())
            ->with(['categories:id,name,slug'])
            ->withCount('comments')
            ->orderByDesc('id') // 最新创建的在前（与前台排序一致）
            ->get()
            ->map(fn (Post $post) => [
                'id' => $post->id,
                'title' => $post->title,
                'slug' => $post->slug,
                'status' => $post->status,
                'views_count' => (int) $post->views_count,
                'comments_count' => (int) $post->comments_count,
                'published_at' => optional($post->published_at)->toDateTimeString(),
                'created_at' => optional($post->created_at)->toDateString(),
                'url' => \Modules\CMS\Support\CmsPermalink::postUrl($post),
                'categories' => $post->categories->map(fn ($c) => ['id' => $c->id, 'name' => $c->name])->values(),
            ]);

        return Inertia::render('CMS/Author/PostsIndex', [
            'posts' => $posts,
        ]);
    }

    public function create(Request $request): Response
    {
        return Inertia::render('CMS/Author/PostEditor', [
            'post' => null,
            'categories' => $this->categoryOptions(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $this->validatePost($request);

        // locale 不在 MKSINE Post::$fillable 中，创建后单独设置
        $post = Post::create([
            ...collect($data)->except('locale')->all(),
            'slug' => $this->uniqueSlug($request),
            'content' => HtmlSanitizer::clean($data['content']),
            'author_id' => $request->user()->getAuthIdentifier(),
            'published_at' => $data['status'] === 'published' ? now() : null,
        ]);
        $post->setAttribute('locale', $data['locale'] ?? 'zh');
        $post->save();

        $post->categories()->sync($this->categoryIds($request));

        return redirect()
            ->route('cms.author.posts.index')
            ->with('success', $data['status'] === 'published' ? '文章已发布。' : '草稿已保存。');
    }

    public function edit(Request $request, Post $post): Response
    {
        abort_unless((int) $post->author_id === (int) $request->user()->getAuthIdentifier(), 403);

        return Inertia::render('CMS/Author/PostEditor', [
            'post' => [
                'id' => $post->id,
                'title' => $post->title,
                'slug' => $post->slug,
                'content' => $post->content,
                'excerpt' => $post->excerpt,
                'status' => $post->status,
                'locale' => $post->locale ?? 'zh',
                'category_ids' => $post->categories->pluck('id')->values(),
            ],
            'categories' => $this->categoryOptions(),
        ]);
    }

    public function update(Request $request, Post $post): RedirectResponse
    {
        abort_unless((int) $post->author_id === (int) $request->user()->getAuthIdentifier(), 403);

        $data = $this->validatePost($request);

        $wasPublished = $post->status === 'published';

        $post->update(collect($data)->except('locale')->all());
        $post->content = HtmlSanitizer::clean($data['content']);
        // 首次发布记录时间；已发布文章保留原时间
        if ($data['status'] === 'published' && ! $wasPublished && ! $post->published_at) {
            $post->published_at = now();
        }
        $post->setAttribute('locale', $data['locale'] ?? 'zh');
        $post->save();

        $post->categories()->sync($this->categoryIds($request));

        return redirect()
            ->route('cms.author.posts.index')
            ->with('success', '文章已更新。');
    }

    public function destroy(Request $request, Post $post): RedirectResponse
    {
        abort_unless((int) $post->author_id === (int) $request->user()->getAuthIdentifier(), 403);

        $post->delete();

        return redirect()
            ->route('cms.author.posts.index')
            ->with('success', '文章已删除。');
    }

    // ------------------------------------------------------------------
    // 个人设置 / 安全设置
    // ------------------------------------------------------------------

    public function settings(Request $request): Response
    {
        $user = $request->user();

        return Inertia::render('CMS/Author/Settings', [
            'profile' => [
                'name' => $user->name,
                'email' => $user->email,
                // 简介为 Markdown 源文本，主页渲染为 HTML
                'bio' => $user->profile?->bio,
                'website' => $user->profile?->website,
                'donation_url' => $user->profile?->donation_url,
                'donation_text' => $user->profile?->donation_text,
            ],
        ]);
    }

    public function updateSettings(Request $request): RedirectResponse
    {
        $user = $request->user();

        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => [
                'required',
                'string',
                'lowercase',
                'email',
                'max:255',
                \Illuminate\Validation\Rule::unique('users', 'email')->ignore($user->getAuthIdentifier()),
            ],
            // Markdown 个人简介
            'bio' => ['nullable', 'string', 'max:20000'],
            'website' => ['nullable', 'string', 'max:255', 'url'],
            // 创作者自定义捐赠按钮
            'donation_url' => ['nullable', 'string', 'max:500', 'url'],
            'donation_text' => ['nullable', 'string', 'max:100'],
        ]);

        $user->forceFill([
            'name' => $data['name'],
            'email' => $data['email'],
        ])->save();

        UserProfile::query()->updateOrCreate(
            ['user_id' => $user->getAuthIdentifier()],
            [
                'bio' => $data['bio'] ?? null,
                'website' => $data['website'] ?? null,
                'donation_url' => $data['donation_url'] ?? null,
                'donation_text' => $data['donation_text'] ?? null,
            ],
        );

        return back()->with('success', '个人资料已更新。');
    }

    public function security(Request $request): Response
    {
        $user = $request->user();

        return Inertia::render('CMS/Author/Security', [
            'emailCodeEnabled' => \App\Support\EmailCode::passwordEnabled(),
            'maskedEmail' => $this->maskEmail($user->email),
            'codeSent' => $request->session()->get('email_code_sent') === 'password',
        ]);
    }

    public function sendSecurityCode(Request $request): RedirectResponse
    {
        try {
            \App\Support\EmailCode::send('password', $request->user()->email);
        } catch (\Throwable $e) {
            return back()->with('error', $e->getMessage());
        }

        return back()->with('email_code_sent', 'password')->with('success', '验证码已发送到您的邮箱。');
    }

    public function updateSecurity(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'current_password' => ['required', 'current_password'],
            'password' => ['required', 'confirmed', Password::defaults()],
            'email_code' => \App\Support\EmailCode::passwordEnabled()
                ? ['required', 'string', 'max:6']
                : ['nullable'],
        ]);

        if (\App\Support\EmailCode::passwordEnabled()) {
            \App\Support\EmailCode::verify('password', $request->user()->email, (string) $data['email_code']);
        }

        $request->user()->forceFill([
            'password' => Hash::make($data['password']),
        ])->save();

        return back()->with('success', '密码已修改。');
    }

    /** 邮箱脱敏展示（a***@domain）。 */
    private function maskEmail(string $email): string
    {
        [$local, $domain] = array_pad(explode('@', $email, 2), 2, '');

        return mb_substr($local, 0, 1).'***@'.$domain;
    }

    // ------------------------------------------------------------------
    // 内部
    // ------------------------------------------------------------------

    /**
     * 文章表单校验：标题 / 正文必填，状态白名单，分类 ID 存在性校验。
     */
    private function validatePost(Request $request): array
    {
        return $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'excerpt' => ['nullable', 'string', 'max:500'],
            'status' => ['required', 'in:'.implode(',', self::STATUSES)],
            'content' => ['required', 'string', 'max:200000'],
            'locale' => ['nullable', 'in:zh,en'],
            'category_ids' => ['nullable', 'array'],
            'category_ids.*' => ['integer', 'exists:categories,id'],
        ], [], [
            'title' => '标题',
            'content' => '正文',
            'excerpt' => '摘要',
            'status' => '状态',
            'locale' => '语言',
        ]);
    }

    private function categoryIds(Request $request): array
    {
        return array_slice(array_map('intval', $request->input('category_ids', [])), 0, 10);
    }

    private function categoryOptions(): array
    {
        return Category::query()
            ->orderBy('name')
            ->get(['id', 'name'])
            ->map(fn ($c) => ['id' => $c->id, 'name' => $c->name])
            ->values()
            ->all();
    }

    /**
     * slug：前端可传自定义（暂不启用），默认基于 title 转写并保证唯一。
     */
    private function uniqueSlug(Request $request): string
    {
        $base = Str::slug((string) $request->input('title')) ?: 'post-'.Str::random(8);

        $slug = $base;
        $i = 1;
        while (Post::query()->where('slug', $slug)->exists()) {
            $slug = $base.'-'.(++$i);
        }

        return $slug;
    }
}
