<?php

namespace Modules\CMS\Support;

use Illuminate\Database\Eloquent\Builder;

/**
 * 文章排序约定：优先发布时间，未发布/遗留缺时间的数据回退 created_at，
 * 再按 id 兜底保证同秒创建时的确定性（新文章永远在最前）。
 */
final class CmsQuery
{
    public static function applyPublishedOrder(Builder $query): Builder
    {
        return $query
            ->orderByRaw('COALESCE(published_at, created_at) DESC')
            ->orderByDesc('id');
    }

    /**
     * 修复存量数据：已发布但 published_at 为空的文章（后台直接建文等场景）
     * 会沉到列表末尾——用 created_at 补齐。
     */
    public static function repairNullPublishedDates(): void
    {
        \Illuminate\Support\Facades\DB::table('posts')
            ->where('status', 'published')
            ->whereNull('published_at')
            ->update(['published_at' => \Illuminate\Support\Facades\DB::raw('created_at')]);
    }

    /**
     * 文章多语言过滤：优先显示用户浏览器语言的文章，
     * 无对应语言文章时回退显示全部（不再分语言）。
     *
     * @param  \Illuminate\Database\Eloquent\Builder  $query
     * @param  string|null  $locale  用户浏览器首选语言（zh/en）
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public static function applyLocaleScope($query, ?string $locale)
    {
        if ($locale && in_array($locale, ['zh', 'en'], true)) {
            $hasLocalized = (clone $query)->where('locale', $locale)->exists();
            if ($hasLocalized) {
                $query->where('locale', $locale);
            }
        }

        return $query;
    }

    /** 从 Accept-Language 头提取首选 CMS 语言（zh/en）。 */
    public static function detectLocale(\Illuminate\Http\Request $request): string
    {
        $preferred = $request->getPreferredLanguage(['zh', 'en']);

        return $preferred ?? 'zh';
    }
}
