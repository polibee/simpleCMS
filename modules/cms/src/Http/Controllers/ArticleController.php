<?php

namespace Modules\CMS\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Miran\Mksine\Models\Category;
use Miran\Mksine\Models\Post;

class ArticleController extends Controller
{
    /**
     * 文章列表（支持 ?layout=grid|list 切换，默认 grid 卡片网格）。
     */
    public function index(Request $request): Response
    {
        $layout = in_array($request->query('layout'), ['grid', 'list'], true)
            ? $request->query('layout')
            : 'grid';

        $posts = Post::query()
            ->where('status', 'published')
            ->with(['author:id,name', 'featuredImage', 'categories:id,name,slug'])
            ->withCount(['comments']);
        \Modules\CMS\Support\CmsQuery::applyPublishedOrder($posts);
        \Modules\CMS\Support\CmsQuery::applyLocaleScope($posts, \Modules\CMS\Support\CmsQuery::detectLocale($request));
        $posts = $posts
            ->paginate(12)
            ->through(fn (Post $post) => $this->postCard($post));

        return Inertia::render('CMS/ArticleIndex', [
            'posts' => $posts,
            'layout' => $layout,
            // 侧边栏：后台配置的卡片 + 分类目录 + 最新文章
            'sidebarCards' => $this->sidebarCards(),
            'categories' => $this->categoryList(),
            'latestPosts' => $this->latestPosts(),
        ]);
    }

    /**
     * 文章详情：同时支持 slug（SEO 友好）与数字 ID 两种标识。
     * /articles/hello-cmsforum 或 /articles/1 均可访问。
     */
    public function show(Request $request, string $slugOrId): Response
    {
        $query = Post::query()
            ->where('status', 'published')
            ->with(['author', 'featuredImage', 'categories:id,name,slug']);

        $post = ctype_digit($slugOrId)
            ? $query->whereKey((int) $slugOrId)->firstOrFail()
            : $query->where('slug', $slugOrId)->firstOrFail();

        // 浏览量（底座字段自增）
        $post->increment('views_count');

        $author = $post->author;

        $renderer = app(\Miran\Mksine\Core\Shortcodes\ContentRenderer::class);
        // 短码上下文：让 [coinpay_buy] 等短码能拿到当前文章
        $context = \Miran\Mksine\Core\Shortcodes\ShortcodeContext::make(post: $post);

        // 付费解锁判定（CryptoPay 插件；未启用时全部免费）
        // 价格来源（优先级从高到低）：正文 [coinpay_buy price="X"] 属性 → crypto_post_prices 表
        $paywalled = false;
        $unlocked = true;
        $price = null;
        if (module_enabled('crypto-pay')) {
            $pay = app(\Modules\CryptoPay\Services\CoinPayService::class);
            $inlinePrice = static::inlineCoinpayPrice((string) $post->content);
            $price = $inlinePrice ?? $pay->priceFor((int) $post->id);

            if ($price !== null && (float) $price > 0) {
                $paywalled = true;
                $unlocked = $pay->hasAccess($request->user(), (int) $post->id);
            }
        }

        // 分段付费：正文内 [coinpay_buy] 之前的免费展示，之后的内容付费隐藏；
        // 未插入短码时整篇付费（仅摘要预览）。
        $body = '';
        $sectionMode = false;

        if ($paywalled && ! $unlocked) {
            // 兼容 [coinpay_buy] 与 [coinpay_buy price="X"] 两种写法
            preg_match('/\[coinpay_buy[^\]]*\]/i', (string) $post->content, $m, PREG_OFFSET_CAPTURE);
            $marker = $m[0][1] ?? false;

            if ($marker !== false) {
                // 分段模式：短码前的内容免费
                $sectionMode = true;
                $freePart = substr((string) $post->content, 0, $marker);
                $body = $renderer->render($freePart, $context);
            } else {
                // 整篇付费：仅摘要
                $body = '';
            }
        } else {
            // 免费 / 已解锁：全文渲染（短码渲染为状态提示）
            $body = $renderer->render((string) $post->content, $context);
        }

        return Inertia::render('CMS/ArticleShow', [
            'article' => [
                'id' => $post->id,
                'title' => $post->title,
                'slug' => $post->slug,
                'url' => \Modules\CMS\Support\CmsPermalink::postUrl($post),
                'body' => $body,
                'published_at' => optional($post->published_at)->toDateString(),
                'views_count' => (int) $post->views_count,
                'author' => $author?->name,
                'author_id' => $author?->getAuthIdentifier(),
                'author_bio' => $author?->profile?->bio,
                'categories' => $post->categories->map(fn ($c) => ['name' => $c->name, 'slug' => $c->slug]),
                'cover_url' => $post->featuredImage?->full_url,
                // 上一篇 / 下一篇（按发布时间相邻）
                'prev' => $this->adjacentPost($post, 'prev'),
                'next' => $this->adjacentPost($post, 'next'),
            ],
            // 付费墙（CryptoPay 插件）：section=分段（短码切分）/ full=整篇
            'paywall' => $paywalled ? [
                'price' => $price,
                'unlocked' => $unlocked,
                'mode' => $sectionMode ? 'section' : 'full',
                'preview' => $sectionMode
                    ? null
                    : \Illuminate\Support\Str::limit(strip_tags((string) $post->excerpt !== '' ? $post->excerpt : $post->content), 200),
            ] : null,
            // 捐赠按钮（优先作者创作中心配置；未配置回退站点"外观→捐赠按钮"；均无则不显示）
            'donation' => $this->donationProps($author),
            // 侧边栏：系统 Sidebar 引擎下发 + 最新文章
            'sidebarCards' => $this->sidebarCards($author, [
                'articles_count' => $author
                    ? Post::query()->where('status', 'published')->where('author_id', $author->getAuthIdentifier())->count()
                    : 0,
            ]),
            'latestPosts' => $this->latestPosts($post->id),
            // 分类目录（侧边栏分类卡片数据；详情页也需要，否则卡片只显示标题）
            'categories' => $this->categoryList(),
            // 相关文章（同分类优先，排除自身）
            'relatedPosts' => $this->relatedPosts($post),
            // 评论树
            'comments' => app(\Modules\CMS\Services\CommentService::class)->treeFor($post->id),
        ]);
    }

    /**
     * 从正文中解析 [coinpay_buy price="X"] 的内联价格（无属性/非法时返回 null）。
     */
    private static function inlineCoinpayPrice(string $content): ?float
    {
        if (! preg_match('/\[coinpay_buy[^\]]*\]/i', $content, $m)) {
            return null;
        }

        if (preg_match('/price\s*=\s*["\']?([0-9]*\.?[0-9]+)["\']?/i', $m[0], $p)) {
            $value = (float) $p[1];

            return $value > 0 ? round($value, 2) : null;
        }

        return null;
    }

    /**
     * 捐赠按钮配置：优先作者个人配置（创作中心→个人设置），未配置回退站点设置。
     */
    private function donationProps(?object $articleAuthor): ?array
    {
        try {
            // 作者自定义捐赠链接（user_profiles.donation_url）
            $authorUrl = $articleAuthor?->profile?->donation_url;
            if ($authorUrl) {
                return [
                    'url' => (string) $authorUrl,
                    'text' => (string) ($articleAuthor?->profile?->donation_text ?: '支持作者'),
                    'source' => 'author',
                ];
            }

            if (! \Illuminate\Support\Facades\Schema::hasTable('settings')) {
                return null;
            }

            $url = \Miran\Mksine\Models\Setting::query()->where('key', 'site_donation_url')->value('value');

            if (! $url) {
                return null;
            }

            return [
                'url' => (string) $url,
                'text' => (string) (\Miran\Mksine\Models\Setting::query()->where('key', 'site_donation_text')->value('value') ?: '支持作者'),
                'source' => 'site',
            ];
        } catch (\Throwable) {
            return null;
        }
    }

    /**
     * 相邻文章（上一篇/下一篇，按 published_at）。
     */
    private function adjacentPost(Post $post, string $direction): ?array
    {
        $query = Post::query()
            ->where('status', 'published')
            ->whereNotNull('published_at')
            ->where('id', '!=', $post->id);

        $current = optional($post->published_at)->format('Y-m-d H:i:s');

        $target = $direction === 'prev'
            ? $query->where('published_at', '<', $current)->orderByDesc('published_at')->first()
            : $query->where('published_at', '>', $current)->orderBy('published_at')->first();

        if (! $target) {
            return null;
        }

        return [
            'title' => $target->title,
            'slug' => $target->slug,
            'url' => \Modules\CMS\Support\CmsPermalink::postUrl($target),
        ];
    }

    /**
     * 相关文章：同分类的已发布文章（排除自身），不足时以最新文章补齐。
     */
    private function relatedPosts(Post $post, int $limit = 4): array
    {
        $categoryIds = $post->categories->pluck('id');

        $related = Post::query()
            ->where('status', 'published')
            ->where('id', '!=', $post->id)
            ->when($categoryIds->isNotEmpty(), fn ($q) => $q->whereHas('categories', fn ($c) => $c->whereIn('categories.id', $categoryIds)))
            ->with(['author:id,name']);
        \Modules\CMS\Support\CmsQuery::applyPublishedOrder($related);
        $related = $related->limit($limit)->get();

        if ($related->count() < $limit) {
            $exclude = $related->pluck('id')->push($post->id);

            $filler = Post::query()
                ->where('status', 'published')
                ->whereNotIn('id', $exclude)
                ->with(['author:id,name']);
            \Modules\CMS\Support\CmsQuery::applyPublishedOrder($filler);
            $filler = $filler->limit($limit - $related->count())->get();

            $related = $related->merge($filler);
        }

        return $related->map(fn ($p) => [
            'id' => $p->id,
            'title' => $p->title,
            'slug' => $p->slug,
            'url' => \Modules\CMS\Support\CmsPermalink::postUrl($p),
            'published_at' => optional($p->published_at)->toDateString(),
            'author' => $p->author?->name,
        ])->all();
    }

    /**
     * 列表卡片载荷（url 按固定链接设置生成）。
     */
    private function postCard(Post $post): array
    {
        return [
            'id' => $post->id,
            'title' => $post->title,
            'slug' => $post->slug,
            'url' => \Modules\CMS\Support\CmsPermalink::postUrl($post),
            'excerpt' => \Illuminate\Support\Str::limit(strip_tags((string) $post->excerpt), 120),
            'published_at' => optional($post->published_at)->toDateString(),
            'author' => $post->author?->name,
            'categories' => $post->categories->map(fn ($c) => ['name' => $c->name, 'slug' => $c->slug]),
            'thumbnail_url' => $post->featuredImage?->full_url,
            'comments_count' => $post->comments_count,
        ];
    }

    /**
     * 系统级 Sidebar 引擎：某区域的启用卡片（ADR-010）。
     * author_center 卡片在详情页注入当前文章作者数据。
     */
    private function sidebarCards(?object $articleAuthor = null, array $authorStats = []): array
    {
        return collect(\App\Core\Sidebar\Sidebar::cardsFor('cms.sidebar'))
            ->when($articleAuthor !== null, function ($cards) use ($articleAuthor, $authorStats) {
                // 给 author_center 卡片补作者实时数据
                return $cards->map(function ($card) use ($articleAuthor, $authorStats) {
                    if ($card['type'] === 'author_center') {
                        $card['author'] = array_merge([
                            'name' => $articleAuthor?->name,
                            'id' => $articleAuthor?->getAuthIdentifier(),
                            'bio' => $articleAuthor?->profile?->bio,
                            'joined_at' => optional($articleAuthor?->created_at)->toDateString(),
                        ], $authorStats);
                    }

                    return $card;
                });
            })
            ->values()
            ->all();
    }

    /**
     * 分类目录（含已发布文章数）。
     */
    private function categoryList(): array
    {
        return Category::query()
            ->orderBy('sort_order')
            ->withCount(['posts' => fn ($q) => $q->where('status', 'published')])
            ->get()
            ->map(fn ($c) => [
                'id' => $c->id,
                'name' => $c->name,
                'slug' => $c->slug,
                'posts_count' => $c->posts_count,
            ])
            ->all();
    }

    /**
     * 最新文章（可选排除某篇，详情页"更多文章"用）。
     */
    private function latestPosts(?int $excludeId = null, int $limit = 5): array
    {
        return Post::query()
            ->where('status', 'published')
            ->when($excludeId, fn ($q) => $q->where('id', '!=', $excludeId))
            ->with(['author:id,name'])
            ->latest('published_at')
            ->limit($limit)
            ->get()
            ->map(fn ($p) => [
                'id' => $p->id,
                'title' => $p->title,
                'slug' => $p->slug,
                'url' => \Modules\CMS\Support\CmsPermalink::postUrl($p),
                'published_at' => optional($p->published_at)->toDateString(),
                'author' => $p->author?->name,
            ])
            ->all();
    }
}
