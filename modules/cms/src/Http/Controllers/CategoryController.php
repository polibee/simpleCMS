<?php

declare(strict_types=1);

namespace Modules\CMS\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Miran\Mksine\Models\Category;
use Miran\Mksine\Models\Post;
use Modules\CMS\Support\CmsQuery;

/**
 * 分类文章列表页：/category/{slug}（侧边栏分类卡片与文章分类标签均指向此处）。
 */
class CategoryController extends Controller
{
    public function show(Request $request, string $slug): Response
    {
        $category = Category::query()
            ->where('slug', $slug)
            ->firstOrFail();

        $postsQuery = Post::query()
            ->where('status', 'published')
            ->whereHas('categories', fn ($q) => $q->where('categories.id', $category->id))
            ->with(['author:id,name', 'featuredImage', 'categories:id,name,slug'])
            ->withCount(['comments']);
        \Modules\CMS\Support\CmsQuery::applyPublishedOrder($postsQuery);
        $posts = $postsQuery->paginate(12)->through(fn (Post $post) => [
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
        ]);

        return Inertia::render('CMS/CategoryPage', [
            'category' => [
                'id' => $category->id,
                'name' => $category->name,
                'slug' => $category->slug,
                'description' => $category->description ?? null,
            ],
            'posts' => $posts,
            'layout' => $request->query('layout', 'grid') === 'list' ? 'list' : 'grid',
            'sidebarCards' => app(\App\Core\Sidebar\SidebarManager::class)->cardsFor('cms.sidebar'),
            'categories' => $this->categoryList(),
            'latestPosts' => $this->latestPosts(),
        ]);
    }

    private function categoryList(): array
    {
        return \Miran\Mksine\Models\Category::query()
            ->orderBy('sort_order')
            ->withCount(['posts' => fn ($q) => $q->where('status', 'published')])
            ->get()
            ->map(fn ($c) => [
                'id' => $c->id,
                'name' => $c->name,
                'slug' => $c->slug,
                'posts_count' => $c->posts_count,
            ])->all();
    }

    private function latestPosts(): array
    {
        $q = Post::query()->where('status', 'published')->with(['author:id,name'])->limit(5);
        \Modules\CMS\Support\CmsQuery::applyPublishedOrder($q);

        return $q->get()->map(fn ($p) => [
            'id' => $p->id,
            'title' => $p->title,
            'slug' => $p->slug,
            'url' => \Modules\CMS\Support\CmsPermalink::postUrl($p),
            'published_at' => optional($p->published_at)->toDateString(),
            'author' => $p->author?->name,
        ])->all();
    }
}
