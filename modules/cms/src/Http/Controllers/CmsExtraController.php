<?php

declare(strict_types=1);

namespace Modules\CMS\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Support\SiteSettings;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;
use Miran\Mksine\Models\Post;
use Modules\CMS\Support\CmsQuery;

/**
 * CMS 前台扩展路由控制器：全文搜索 + RSS Feed + 标签云数据。
 */
class CmsExtraController extends Controller
{
    /**
     * GET /search?q=关键词
     */
    public function search(Request $request): Response
    {
        $q = trim((string) $request->query('q', ''));
        $posts = collect();

        if (mb_strlen($q) >= 2) {
            $query = Post::query()
                ->where('status', 'published')
                ->where(fn ($sub) => $sub
                    ->where('title', 'like', "%{$q}%")
                    ->orWhere('excerpt', 'like', "%{$q}%")
                    ->orWhere('content', 'like', "%{$q}%"))
                ->with(['author:id,name', 'featuredImage', 'categories:id,name,slug'])
                ->withCount('comments');
            \Modules\CMS\Support\CmsQuery::applyPublishedOrder($query);
            \Modules\CMS\Support\CmsQuery::applyLocaleScope($query, \Modules\CMS\Support\CmsQuery::detectLocale($request));
            $posts = $query->limit(20)->get()->map(fn (Post $p) => [
                'id' => $p->id,
                'title' => $p->title,
                'url' => \Modules\CMS\Support\CmsPermalink::postUrl($p),
                'excerpt' => \Illuminate\Support\Str::limit(strip_tags((string) ($p->excerpt ?: $p->content)), 120),
                'published_at' => optional($p->published_at)?->toDateString(),
                'author' => $p->author?->name,
                'categories' => $p->categories->map(fn ($c) => ['name' => $c->name, 'slug' => $c->slug]),
            ]);
        }

        return Inertia::render('CMS/SearchPage', [
            'q' => $q,
            'posts' => $posts,
        ]);
    }

    /**
     * GET /feed — RSS 2.0 输出最新已发布文章。
     */
    public function feed(Request $request)
    {
        $siteUrl = rtrim(url('/'), '/');
        $siteName = config('app.name', 'CMSForum');

        $posts = Post::query()
            ->where('status', 'published')
            ->with(['author:id,name'])
            ->limit(20);
        \Modules\CMS\Support\CmsQuery::applyPublishedOrder($posts);
        $items = $posts->get()->map(fn (Post $p) => [
            'title' => $p->title,
            'link' => \Modules\CMS\Support\CmsPermalink::postUrl($p),
            'description' => \Illuminate\Support\Str::limit(strip_tags((string) ($p->excerpt ?: $p->content)), 300),
            'author' => $p->author?->name ?? 'Anonymous',
            'pubDate' => optional($p->published_at ?: $p->created_at)->toRfc2822String(),
            'guid' => 'post-'.$p->id,
        ])->all();

        $xml = '<?xml version="1.0" encoding="UTF-8"?>'."\n";
        $xml .= '<rss version="2.0"><channel>'."\n";
        $xml .= '<title>'.e($siteName).'</title>'."\n";
        $xml .= '<link>'.e($siteUrl).'</link>'."\n";
        $xml .= '<description>'.e($siteName.' 最新文章').'</description>'."\n";
        $xml .= '<language>zh-CN</language>'."\n";
        $xml .= '<lastBuildDate>'.now()->toRfc2822String().'</lastBuildDate>'."\n";

        foreach ($items as $item) {
            $xml .= '<item>'."\n";
            $xml .= '<title>'.e($item['title']).'</title>'."\n";
            $xml .= '<link>'.e($siteUrl.$item['link']).'</link>'."\n";
            $xml .= '<description>'.e($item['description']).'</description>'."\n";
            $xml .= '<author>'.e($item['author']).'</author>'."\n";
            $xml .= '<pubDate>'.$item['pubDate'].'</pubDate>'."\n";
            $xml .= '<guid>'.e($item['guid']).'</guid>'."\n";
            $xml .= '</item>'."\n";
        }

        $xml .= '</channel></rss>';

        return response($xml, 200, ['Content-Type' => 'application/rss+xml; charset=UTF-8']);
    }
}
