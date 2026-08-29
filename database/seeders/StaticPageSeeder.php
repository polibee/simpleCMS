<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Modules\CMS\Models\SitePage;

/**
 * 站点默认单页：隐私政策 / 服务条款 / 关于我们。
 * 已存在的 slug 不会覆盖（保留后台编辑内容）。
 */
class StaticPageSeeder extends Seeder
{
    public function run(): void
    {
        $pages = [
            'privacy' => [
                'title' => '隐私政策',
                'content' => "# 隐私政策\n\n我们使用 Cookie 提供个性化内容、广告与流量统计（含 Google Analytics / Google Ads）。点击「接受全部」即表示同意。\n\n## 一、我们收集的信息\n\n- 账号信息：注册时提供的昵称与邮箱；\n- 内容数据：你发布的文章、评论与互动记录；\n- 访问数据：页面路径、来源、浏览器类型等聚合统计信息。\n\n## 二、Cookie 的使用\n\n- **必要 Cookie**：维持登录态与安全，不可关闭；\n- **统计与广告 Cookie**：仅在你点击「接受全部」后加载。\n\n## 三、第三方服务\n\n- Google Analytics：流量统计（IP 匿名化）；\n- 广告联盟：广告投放与效果衡量。\n\n## 四、你的权利\n\n可随时清除浏览器中的 `cookie_consent` 记录重新选择，或联系我们导出/删除账号数据。",
            ],
            'terms' => [
                'title' => '服务条款',
                'content' => "# 服务条款\n\n欢迎使用本站。注册即表示同意以下条款：\n\n1. 遵守所在地区法律法规，不发布违法违规内容；\n2. 尊重原创，转载需授权并注明来源；\n3. 不得恶意刷量、灌水或攻击站点服务；\n4. 站点有权对违规内容进行处理，直至封禁账号。",
            ],
            'about' => [
                'title' => '关于我们',
                'content' => "# 关于我们\n\n本站是基于 CMS + Forum 模块化架构的内容社区，支持主题覆盖与插件扩展。\n\n- 浏览文章：首页与文章列表；\n- 参与创作：联系管理员授予作者角色；\n- 问题反馈：通过评论区或邮件联系我们。",
            ],
        ];

        foreach ($pages as $slug => $page) {
            SitePage::query()->firstOrCreate(['slug' => $slug], [
                'title' => $page['title'],
                'content' => $page['content'],
                'enabled' => true,
                'sort' => 0,
            ]);
        }

        $this->command?->info('默认单页已就绪：privacy / terms / about');
    }
}
