<?php

namespace Modules\User\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Support\BioMarkdown;
use Illuminate\Contracts\Auth\Authenticatable;
use Inertia\Inertia;
use Inertia\Response;
use Miran\Mksine\Models\Post;

/**
 * 用户个人主页（前台）：简介卡片（Markdown 渲染）+ 创作统计 + 发布文章列表。
 */
class UserController extends Controller
{
    public function show(int $id): Response
    {
        $userModel = config('mksine.user_model', \App\Models\User::class);

        /** @var Authenticatable $user */
        $user = $userModel::query()
            ->with(['profile'])
            ->findOrFail($id);

        $profile = $user->profile;

        // 已发布文章（复用 CMS 排序约定：最新在前，带固定链接 url）
        $postsQuery = Post::query()
            ->where('status', 'published')
            ->where('author_id', $user->getAuthIdentifier())
            ->with(['featuredImage', 'categories:id,name,slug']);
        \Modules\CMS\Support\CmsQuery::applyPublishedOrder($postsQuery);
        $posts = $postsQuery->limit(12)->get()->map(fn (Post $post) => [
            'id' => $post->id,
            'title' => $post->title,
            'url' => \Modules\CMS\Support\CmsPermalink::postUrl($post),
            'excerpt' => \Illuminate\Support\Str::limit(strip_tags((string) ($post->excerpt !== '' ? $post->excerpt : $post->content)), 90),
            'published_at' => optional($post->published_at)?->toDateString(),
            'views_count' => (int) $post->views_count,
            'cover_url' => $post->featuredImage?->full_url,
            'categories' => $post->categories->map(fn ($c) => ['name' => $c->name, 'slug' => $c->slug]),
        ])->all();

        // 统计（卡片列表上限内的浏览量）
        $stats = [
            'posts' => count($posts),
            'views' => array_sum(array_map(fn ($p) => $p['views_count'], $posts)),
        ];

        return Inertia::render('User/ProfileShow', [
            'profileUser' => [
                'id' => $user->getAuthIdentifier(),
                'name' => $user->name,
                'initial' => mb_strtoupper(mb_substr((string) $user->name, 0, 1)),
                'joined_at' => optional($user->created_at)?->toDateString(),
                'website' => $profile?->website,
                'bio_markdown' => $profile?->bio,
                'bio_html' => BioMarkdown::toHtml($profile?->bio),
                'signature' => $profile?->signature,
                'donation_url' => $profile?->donation_url,
                'donation_text' => $profile?->donation_text,
            ],
            'stats' => $stats,
            'posts' => $posts,
        ]);
    }
}
