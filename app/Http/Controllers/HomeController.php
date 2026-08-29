<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Miran\Mksine\Models\Post;

class HomeController extends Controller
{
    /**
     * 首页：Hero + 最新文章流 + 侧边栏（复用系统 Sidebar 引擎）。
     */
    public function index(Request $request): Response
    {
        $posts = Post::query()
            ->where('status', 'published')
            ->with(['author:id,name', 'featuredImage', 'categories:id,name,slug'])
            ->withCount(['comments']);
        \Modules\CMS\Support\CmsQuery::applyPublishedOrder($posts);
        \Modules\CMS\Support\CmsQuery::applyLocaleScope($posts, \Modules\CMS\Support\CmsQuery::detectLocale($request));
        $posts = $posts
            ->paginate(8)
            ->through(fn (Post $post) => [
                'id' => $post->id,
                'title' => $post->title,
                'slug' => $post->slug,
                'url' => \Modules\CMS\Support\CmsPermalink::postUrl($post),
                'excerpt' => \Illuminate\Support\Str::limit(strip_tags((string) $post->excerpt), 110),
                'published_at' => optional($post->published_at)->toDateString(),
                'author' => $post->author?->name,
                'categories' => $post->categories->map(fn ($c) => ['name' => $c->name, 'slug' => $c->slug]),
                'thumbnail_url' => $post->featuredImage?->full_url,
                'comments_count' => $post->comments_count,
            ]);

        $featuredQuery = Post::query()
            ->where('status', 'published')
            ->with(['author:id,name']);
        \Modules\CMS\Support\CmsQuery::applyPublishedOrder($featuredQuery);
        $featured = $featuredQuery->first();

        return Inertia::render('Home', [
            'posts' => $posts,
            // 置顶头条（最新一篇）
            'featured' => $featured ? [
                'title' => $featured->title,
                'slug' => $featured->slug,
                'excerpt' => \Illuminate\Support\Str::limit(strip_tags((string) $featured->excerpt), 140),
                'author' => $featured->author?->name,
                'published_at' => optional($featured->published_at)->toDateString(),
            ] : null,
            // 首页轮播图（后台"内容→首页轮播图"管理）
            'banners' => \Modules\CMS\Models\CmsBanner::query()
                ->where('enabled', true)
                ->orderBy('sort')
                ->get(['id', 'title', 'image_url', 'link_url', 'new_tab'])
                ->map(fn ($b) => [
                    'id' => $b->id,
                    'title' => $b->title,
                    'image_url' => $b->image_url,
                    'link_url' => $b->link_url,
                    'new_tab' => (bool) $b->new_tab,
                ])->all(),
            // 侧边栏：复用系统 Sidebar 引擎（后台"侧边栏管理"统一控制）
            'sidebarCards' => app(\App\Core\Sidebar\SidebarManager::class)->cardsFor('cms.sidebar'),
            'categories' => \Miran\Mksine\Models\Category::query()
                ->orderBy('sort_order')
                ->withCount(['posts' => fn ($q) => $q->where('status', 'published')])
                ->get()
                ->map(fn ($c) => [
                    'id' => $c->id,
                    'name' => $c->name,
                    'slug' => $c->slug,
                    'posts_count' => $c->posts_count,
                ])->all(),
            'latestPosts' => tap(Post::query()->where('status', 'published')
                ->with(['author:id,name'])->limit(5), fn ($q) => \Modules\CMS\Support\CmsQuery::applyPublishedOrder($q))
                ->get()->map(fn ($p) => [
                    'id' => $p->id,
                    'title' => $p->title,
                    'slug' => $p->slug,
                    'url' => \Modules\CMS\Support\CmsPermalink::postUrl($p),
                    'published_at' => optional($p->published_at)->toDateString(),
                    'author' => $p->author?->name,
                ])->all(),
        ]);
    }
}
