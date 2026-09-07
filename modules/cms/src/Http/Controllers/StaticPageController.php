<?php

declare(strict_types=1);

namespace Modules\CMS\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Support\BioMarkdown;
use App\Support\SiteSettings;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Modules\CMS\Models\SitePage;

/**
 * 站点单页（前台）：/p/{slug} 及 privacy / terms / about 固定入口。
 * 内容为后台"单页管理"维护的 Markdown。
 */
class StaticPageController extends Controller
{
    public function show(Request $request, string $slug): Response
    {
        $page = SitePage::query()
            ->where('slug', $slug)
            ->where('enabled', true)
            ->firstOrFail();

        // 兼容两种内容源：基座富文本（HTML）→ 白名单清洗；Markdown → 安全渲染。
        //
        // 判定方式修正：旧实现用 str_contains($content, '<') 猜测类型，正文里
        // 出现 "a < b" 这类纯文本就会被误判为 HTML 并送去清洗（P3-7）。
        // 改为匹配真正的标签形态，只有确有标签才走清洗分支。
        $content = (string) $page->content;
        $html = self::looksLikeHtml($content)
            ? \App\Support\HtmlSanitizer::clean($content)
            : (BioMarkdown::toHtml($content) ?? '');

        return Inertia::render('CMS/StaticPage', [
            'page' => [
                'slug' => $page->slug,
                'title' => $page->title,
                'html' => $html,
                'updated_at' => optional($page->updated_at)->toDateString(),
            ],
            // 隐私政策页顶部展示生效日期（站点设置→隐私与 Cookie）
            'effectiveDate' => $slug === 'privacy'
                ? SiteSettings::get('privacy_policy_updated_at', '2026-01-01')
                : null,
        ]);
    }

    /**
     * 内容是否为 HTML（含成对的标签），而非 Markdown 或碰巧带 < 的纯文本。
     */
    private static function looksLikeHtml(string $content): bool
    {
        return preg_match('/<\/?[a-z][a-z0-9]*(\s[^<>]*)?>/i', $content) === 1;
    }
}
