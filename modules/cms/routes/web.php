<?php

use Illuminate\Support\Facades\Route;
use Modules\CMS\Http\Controllers\Author\AuthorController;

/**
 * CMS 模块 Web 路由。
 *
 * 文章列表/详情/评论路由已由 CmsRoutes::register() 在应用层按固定链接
 * 设置动态注册（见 routes/web.php），此处不再硬编码，避免与可配置的
 * permalink 结构冲突。
 */

// 插件打包下载（super_admin）：独立普通路由，Livewire 动作里只做重定向，
// 浏览器 GET 该地址触发浏览器原生附件下载
Route::get('/admin/plugin-packager/{pluginId}/download', function (string $pluginId) {
    $user = auth()->user();
    abort_unless($user && method_exists($user, 'hasRole') && $user->hasRole('super_admin'), 403);

    $manager = app(\Miran\Mksine\Core\Plugins\PluginManager::class);
    abort_unless(array_key_exists($pluginId, $manager->discover()), 404);

    [$zipPath, $filename] = \App\Support\PluginPackager::package($pluginId);

    return response()->download($zipPath, $filename);
})->name('plugin-packager.download');

// 站点单页（后台"单页管理"维护）：/p/{slug} + 常用固定入口
Route::get('/p/{slug}', [\Modules\CMS\Http\Controllers\StaticPageController::class, 'show'])->name('cms.page');
Route::get('/privacy', fn () => app(\Modules\CMS\Http\Controllers\StaticPageController::class)->show(request(), 'privacy'))->name('cms.privacy');
Route::get('/terms', fn () => app(\Modules\CMS\Http\Controllers\StaticPageController::class)->show(request(), 'terms'))->name('cms.terms');
Route::get('/about', fn () => app(\Modules\CMS\Http\Controllers\StaticPageController::class)->show(request(), 'about'))->name('cms.about');

// 全文搜索 + RSS Feed（搜索为 longtext LIKE 全表扫描，单独限流）
Route::get('/search', [\Modules\CMS\Http\Controllers\CmsExtraController::class, 'search'])
    ->middleware('throttle:search')
    ->name('cms.search');
Route::get('/feed', [\Modules\CMS\Http\Controllers\CmsExtraController::class, 'feed'])->name('cms.feed');

// 站点地图 /sitemap.xml（1 小时缓存；提供首页、文章列表与全部已发布文章，含 lastmod）
Route::get('/sitemap.xml', function () {
    $xml = \Illuminate\Support\Facades\Cache::remember('cms.sitemap.xml', 3600, function () {
        $base = rtrim(url('/'), '/');
        $listUrl = \Modules\CMS\Support\CmsPermalink::postsUrl();
        $lines = [
            '<?xml version="1.0" encoding="UTF-8"?>',
            '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">',
            '  <url><loc>'.e($base.'/').'</loc><changefreq>daily</changefreq><priority>1.0</priority></url>',
            '  <url><loc>'.e($base.$listUrl).'</loc><changefreq>daily</changefreq><priority>0.9</priority></url>',
        ];

        \Miran\Mksine\Models\Post::query()
            ->where('status', 'published')
            ->orderByDesc('id')
            ->chunk(500, function ($posts) use (&$lines, $base) {
                foreach ($posts as $post) {
                    $lastmod = optional($post->updated_at ?: $post->published_at)->toAtomString();
                    $lines[] = '  <url><loc>'.e($base.\Modules\CMS\Support\CmsPermalink::postUrl($post)).'</loc>'
                        .($lastmod ? '<lastmod>'.$lastmod.'</lastmod>' : '')
                        .'<changefreq>weekly</changefreq><priority>0.7</priority></url>';
                }
            });

        $lines[] = '</urlset>';

        return implode("\n", $lines);
    });

    return response($xml, 200, ['Content-Type' => 'application/xml; charset=UTF-8']);
})->name('cms.sitemap');

// 创作中心入口：/studio 与 /studio/ 均重定向到文章列表
Route::redirect('/studio', '/studio/posts');
Route::get('/studio/', fn () => redirect()->to('/studio/posts'));

// 创作中心（前台作者）：auth 登录 + author 角色（admin 在后台用户页授予）。
// 注意用 /studio 前缀：底座已占用 GET /author/{id}（作者主页），会抢走 /author/posts。
Route::middleware(['auth', 'author'])
    ->prefix('studio')
    ->name('cms.author.')
    ->group(function () {
        Route::get('/posts', [AuthorController::class, 'index'])->name('posts.index');
        Route::get('/posts/create', [AuthorController::class, 'create'])->name('posts.create');
        Route::post('/posts', [AuthorController::class, 'store'])->name('posts.store');
        Route::get('/posts/{post}/edit', [AuthorController::class, 'edit'])->name('posts.edit');
        Route::put('/posts/{post}', [AuthorController::class, 'update'])->name('posts.update');
        Route::delete('/posts/{post}', [AuthorController::class, 'destroy'])->name('posts.destroy');
        Route::get('/settings', [AuthorController::class, 'settings'])->name('settings');
        Route::put('/settings', [AuthorController::class, 'updateSettings'])->name('settings.update');
        Route::get('/settings/security', [AuthorController::class, 'security'])->name('settings.security');
        Route::put('/settings/security', [AuthorController::class, 'updateSecurity'])->name('settings.security.update');
        Route::post('/settings/security/code', [AuthorController::class, 'sendSecurityCode'])->name('settings.security.code');
    });
