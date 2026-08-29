<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Miran\Mksine\Models\Category;
use Miran\Mksine\Models\Post;

/**
 * CMS 演示数据：分类 + 测试文章（用于前台视觉验收）。
 * php artisan db:seed --class=CmsDemoSeeder
 */
class CmsDemoSeeder extends Seeder
{
    public function run(): void
    {
        // 作者角色（author/super_admin 可进创作中心）+ 演示作者授权
        $this->call(AuthorRoleSeeder::class);

        $author = \App\Models\User::query()->firstOrCreate(
            ['email' => 'author@cmsforum.test'],
            ['name' => '演示作者', 'password' => Hash::make('password123')],
        );

        $categories = collect([
            ['name' => '技术分享', 'slug' => 'tech'],
            ['name' => '产品设计', 'slug' => 'design'],
            ['name' => '社区动态', 'slug' => 'community'],
            ['name' => '教程', 'slug' => 'tutorials'],
        ])->mapWithKeys(function ($c) {
            $cat = Category::query()->firstOrCreate(['slug' => $c['slug']], $c);

            return [$c['slug'] => $cat];
        });

        $posts = [
            ['title' => 'Laravel 13 新特性速览：结构与性能的双重进化', 'slug' => 'laravel-13-features', 'cat' => 'tech', 'days' => 1,
             'excerpt' => '从目录结构到 Octane 深度整合，Laravel 13 带来了哪些值得关注的改进？本文带你快速过一遍重点。'],
            ['title' => '用 shadcn/ui 打造现代后台界面设计系统', 'slug' => 'shadcn-design-system', 'cat' => 'design', 'days' => 2,
             'excerpt' => '设计令牌、组件变体、暗色模式——一套可维护的后台 UI 设计体系是如何搭建起来的。'],
            ['title' => '模块化 CMS 架构实践：插件、主题与钩子系统', 'slug' => 'modular-cms-architecture', 'cat' => 'tech', 'days' => 3,
             'excerpt' => '参考 WordPress 与 Flarum 的扩展机制，我们如何在 Laravel 上实现可插拔的内容平台。'],
            ['title' => '社区周报 #12：新模块上线与生态进展', 'slug' => 'community-weekly-12', 'cat' => 'community', 'days' => 4,
             'excerpt' => '本周社区合并了 18 个 PR，支付底座与邀请码模块进入测试阶段，更多动态见正文。'],
            ['title' => '从零部署生产环境：Nginx、Redis 与队列配置指南', 'slug' => 'production-deploy-guide', 'cat' => 'tutorials', 'days' => 5,
             'excerpt' => '手把手配置一台 2核4G 服务器的完整 LNMP 环境，包含 OPcache 调优与 Supervisor 守护。'],
            ['title' => '设计系统的色彩哲学：为什么我们选择中性色', 'slug' => 'design-color-philosophy', 'cat' => 'design', 'days' => 6,
             'excerpt' => 'zinc 灰阶、oklch 色彩空间与语义化令牌——克制的中性色如何撑起整个产品界面。'],
            ['title' => 'Markdown 编辑器的工程实现：从解析到安全渲染', 'slug' => 'markdown-editor-impl', 'cat' => 'tech', 'days' => 7,
             'excerpt' => 'CommonMark 规范、GFM 扩展与 XSS 防线：一个评论框背后不简单的渲染管线。'],
            ['title' => '新手教程：5 分钟创建你的第一篇文章', 'slug' => 'first-post-tutorial', 'cat' => 'tutorials', 'days' => 8,
             'excerpt' => '从后台登录到发布上线，本教程带你走完内容创作的完整流程。'],
        ];

        foreach ($posts as $p) {
            $post = Post::query()->firstOrCreate(
                ['slug' => $p['slug']],
                [
                    'title' => $p['title'],
                    'content' => "<p>{$p['excerpt']}</p><p>这是一篇用于前台视觉验收的演示文章。正文支持富文本排版：段落、列表、引用、代码块等元素都会在详情页按排版样式呈现。</p><h3>小节标题示例</h3><ul><li>列表项一：内容模块化</li><li>列表项二：主题可覆盖</li><li>列表项三：插件可注入</li></ul><blockquote><p>设计是把复杂留给自己，把简单留给用户。</p></blockquote><pre><code>php artisan serve</code></pre>",
                    'excerpt' => $p['excerpt'],
                    'status' => 'published',
                    'author_id' => $author->id,
                    'published_at' => now()->subDays($p['days']),
                ],
            );

            $post->categories()->syncWithoutDetaching([$categories[$p['cat']]->id]);
        }

        $this->command?->info('CMS 演示数据完成：'.Post::count().' 篇文章，'.Category::count().' 个分类');
    }
}
