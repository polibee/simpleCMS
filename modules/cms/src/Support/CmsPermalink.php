<?php

namespace Modules\CMS\Support;

use Illuminate\Support\Facades\Schema;
use Miran\Mksine\Models\Post;

/**
 * CMS 固定链接（Permalink）引擎 —— WordPress 式链接结构设置。
 *
 * 设置项存底座 settings 表，后台"外观 → CMS 固定链接"可改：
 * - cms_posts_url        文章列表前缀，默认 /articles
 * - cms_single_post_url  详情模式，支持 {slug} 与 {id} 占位符，默认 /articles/{slug}
 *
 * 安全约束：模式仅允许小写字母/数字/连字符/斜杠与占位符（白名单校验），
 * 防止路径注入；路由由应用层入口优先注册以接管底座默认文章路由。
 */
final class CmsPermalink
{
    public const POSTS_URL_KEY = 'cms_posts_url';

    public const SINGLE_POST_URL_KEY = 'cms_single_post_url';

    public const DEFAULT_POSTS_URL = '/articles';

    public const DEFAULT_SINGLE_POST_URL = '/articles/{slug}';

    /** 模式白名单：小写字母、数字、连字符、斜杠、{slug}/{id} 占位符。 */
    private const PATTERN_RULE = '/^\/[a-z0-9\-\/]*(\{slug\}|\{id\})?[a-z0-9\-\/]*$/';

    /**
     * 文章列表 URL。
     */
    public static function postsUrl(): string
    {
        return self::normalize(self::setting(self::POSTS_URL_KEY, self::DEFAULT_POSTS_URL));
    }

    /**
     * 文章详情模式（含占位符）。
     */
    public static function singlePostUrl(): string
    {
        return self::normalize(self::setting(self::SINGLE_POST_URL_KEY, self::DEFAULT_SINGLE_POST_URL));
    }

    /**
     * 按当前固定链接设置生成某篇文章的详情 URL。
     */
    public static function postUrl(Post $post): string
    {
        $pattern = self::singlePostUrl();

        if (str_contains($pattern, '{id}')) {
            return str_replace('{id}', (string) $post->id, $pattern);
        }

        $identifier = $post->slug !== '' ? $post->slug : (string) $post->id;

        return str_replace('{slug}', $identifier, $pattern);
    }

    /**
     * 评论提交地址。
     */
    public static function postCommentsUrl(Post $post): string
    {
        return self::postUrl($post).'/comments';
    }

    /**
     * 校验详情模式：白名单 + 必须包含占位符。
     */
    public static function isValidSinglePattern(string $pattern): bool
    {
        return (bool) preg_match(self::PATTERN_RULE, $pattern)
            && (str_contains($pattern, '{slug}') || str_contains($pattern, '{id}'));
    }

    /**
     * 校验列表前缀：白名单且不含占位符。
     */
    public static function isValidPostsUrl(string $url): bool
    {
        return (bool) preg_match('/^\/[a-z0-9\-\/]*$/', $url) && ! str_contains($url, '{');
    }

    /**
     * 规范化：去尾部斜杠、压平重复斜杠、转小写。
     */
    public static function normalize(string $pattern): string
    {
        $pattern = strtolower(trim($pattern));
        $pattern = preg_replace('#/+#', '/', $pattern) ?? $pattern;

        return rtrim($pattern, '/') ?: '/';
    }

    private static function setting(string $key, string $default): string
    {
        try {
            if (! Schema::hasTable('settings')) {
                return $default;
            }

            $value = \Miran\Mksine\Models\Setting::query()->where('key', $key)->value('value');

            return ($value !== null && $value !== '') ? (string) $value : $default;
        } catch (\Throwable) {
            return $default;
        }
    }
}
