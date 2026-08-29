<?php

namespace Modules\CMS\Support;

use Illuminate\Support\Facades\Route;
use Modules\CMS\Http\Controllers\ArticleController;
use Modules\CMS\Http\Controllers\CommentController;

/**
 * CMS 文章路由注册器（WordPress 式固定链接）。
 *
 * 必须由应用层 routes/web.php 在最早期调用：Laravel 按注册顺序匹配，
 * 先注册即可接管底座默认文章路由（/posts、/post/{slug} 的 Livewire 前台），
 * 使后台设置的固定链接结构真正生效。
 */
final class CmsRoutes
{
    public static function register(): void
    {
        if (! self::cmsActive()) {
            return;
        }

        try {
            $listUrl = CmsPermalink::postsUrl();
            $singleUrl = CmsPermalink::singlePostUrl();
        } catch (\Throwable) {
            return; // 设置表未就绪时跳过（迁移期）
        }

        // 文章列表
        Route::get($listUrl, [ArticleController::class, 'index'])->name('cms.articles.index');

        // 文章详情 + 评论提交（占位符编译为 Laravel 路由参数）
        [$uri, $constraints] = self::compileSingle($singleUrl);
        Route::get($uri, [ArticleController::class, 'show'])
            ->name('cms.articles.show')
            ->where($constraints);
        Route::post($uri.'/comments', [CommentController::class, 'store'])
            ->name('cms.comments.store');

        // 游客评论验证码题目
        Route::get('/comment/captcha', [\Modules\CMS\Http\Controllers\CaptchaController::class, 'show'])
            ->name('cms.captcha');

        // 分类文章列表页（侧边栏分类卡片 / 文章分类标签 → /c/{slug}；
        // 不能用 /category/{slug}——底座 FrontendResolver 已占用该前缀且先于本路由注册）
        Route::get('/c/{slug}', [\Modules\CMS\Http\Controllers\CategoryController::class, 'show'])
            ->name('cms.category.show');

        // 旧默认链接 301 兼容（结构被修改后，旧 URL 永久重定向到新结构）
        if ($singleUrl !== CmsPermalink::DEFAULT_SINGLE_POST_URL) {
            Route::get('/articles/{slugOrId}', function (string $slugOrId) {
                $post = \Miran\Mksine\Models\Post::query()
                    ->where('status', 'published')
                    ->when(ctype_digit($slugOrId), fn ($q) => $q->whereKey((int) $slugOrId), fn ($q) => $q->where('slug', $slugOrId))
                    ->first();

                if (! $post) {
                    abort(404);
                }

                return redirect()->to(CmsPermalink::postUrl($post), 301);
            })->name('cms.articles.legacy');
        }

        if ($listUrl !== CmsPermalink::DEFAULT_POSTS_URL) {
            Route::get('/articles', fn () => redirect()->to($listUrl, 301))
                ->name('cms.posts.legacy');
        }
    }

    /**
     * 把固定链接模式编译为 Laravel 路由 URI 与约束。
     * {slug}/{id} 统一编译为 {slugOrId}；{id} 模式限纯数字。
     *
     * @return array{0: string, 1: array<string, string>}
     */
    private static function compileSingle(string $pattern): array
    {
        $isIdMode = str_contains($pattern, '{id}');
        $uri = str_replace(['{slug}', '{id}'], '{slugOrId}', $pattern);

        return [
            $uri,
            ['slugOrId' => $isIdMode ? '[0-9]+' : '[^/]+'],
        ];
    }

    /**
     * CMS 模块是否启用（直查 mks_plugins 表；本方法在 withRouting 阶段执行，
     * 插件管理器尚未初始化。表不存在时视为未启用）。
     */
    private static function cmsActive(): bool
    {
        try {
            if (! \Illuminate\Support\Facades\Schema::hasTable('mks_plugins')) {
                return false;
            }

            return \Illuminate\Support\Facades\DB::table('mks_plugins')
                ->where('plugin_id', 'cms')
                ->where('status', 'active')
                ->exists();
        } catch (\Throwable) {
            return false;
        }
    }
}
