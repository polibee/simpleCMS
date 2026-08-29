<?php

namespace App\Http\Middleware;

use Illuminate\Http\Request;
use Inertia\Middleware;

class HandleInertiaRequests extends Middleware
{
    /**
     * The root template that is loaded on the first page visit.
     */
    protected $rootView = 'app';

    /**
     * Determine the current asset version.
     */
    public function version(Request $request): ?string
    {
        return parent::version($request);
    }

    /**
     * Define the props that are shared by default.
     */
    public function share(Request $request): array
    {
        $user = $request->user();

        return [
            ...parent::share($request),
            // 一次性提示消息（控制器 redirect()->with(...)）
            'flash' => fn () => [
                'success' => $request->session()->get('success'),
                'error' => $request->session()->get('error'),
            ],
            'auth' => [
                'user' => fn () => $user ? [
                    'id' => $user->getAuthIdentifier(),
                    'name' => $user->name,
                    'email' => $user->email,
                ] : null,
                // 是否具备作者撰写权限（author/super_admin 角色）；前台据此显隐创作入口
                'can_author' => fn () => \App\Support\Author::isAuthor($user),
            ],
            // 站点设置白名单（前台渲染用）
            'site' => [
                'name' => config('app.name', 'CMSForum'),
            ],
            // 钱包/签到状态（Quest+Economy 模块启用且用户登录时才有值；未启用降级为 null）
            'wallet' => fn () => $this->walletProps($user),
            // 页眉/页脚导航（底座菜单系统；后台"菜单"可配置，未分配时为 null 由前端回退）
            'menus' => fn () => $this->menuProps(),
            // 页脚多列（后台"外观→页脚导航列"；每列 = 标题 + 绑定菜单树）
            'footerColumns' => fn () => $this->footerColumnProps(),
            // CMS 固定链接信息（前台 URL 生成用）
            'cms' => fn () => [
                'listUrl' => module_enabled('cms')
                    ? \Modules\CMS\Support\CmsPermalink::postsUrl()
                    : '/articles',
            ],
            // SEO 站点设置（前台 Head meta 用）
            'seo' => fn () => \App\Support\SiteSettings::seo(),
            // Cookie 授权横幅 / 隐私政策生效日期
            'privacy' => fn () => [
                'cookieBanner' => \App\Support\SiteSettings::get('cookie_banner_enabled') === '1',
                'effectiveDate' => \App\Support\SiteSettings::get('privacy_policy_updated_at', '2026-01-01'),
            ],
            // 广告位（Ads 插件）：按位置聚合；表不存在时服务内部安全降级为空
            'ads' => fn () => \Modules\Ads\Services\AdService::mapForFrontend(),
        ];
    }

    private function walletProps(?object $user): ?array
    {
        if (! $user || ! module_enabled('economy') || ! module_enabled('quest')) {
            return null;
        }

        try {
            return [
                'checked_today' => app(\Modules\Quest\Services\CheckinService::class)->hasCheckedInToday($user),
                'gold' => app(\Modules\Economy\Services\WalletService::class)->balance($user, 'gold'),
            ];
        } catch (\Throwable) {
            return null; // 表未就绪等场景静默降级
        }
    }

    /**
     * 页脚多列（通用模板）：启用的列 + 各列绑定菜单树；表未就绪时降级空集。
     */
    private function footerColumnProps(): array
    {
        try {
            if (! \Illuminate\Support\Facades\Schema::hasTable('site_footer_columns')) {
                return [];
            }

            $menus = app(\Miran\Mksine\Services\MenuService::class);

            return \App\Models\SiteFooterColumn::query()
                ->where('enabled', true)
                ->orderBy('sort')
                ->get()
                ->map(fn ($col) => [
                    'id' => $col->id,
                    'title' => $col->title,
                    'items' => $col->menu_id ? ($menus->getMenuById((int) $col->menu_id)['items'] ?? []) : [],
                ])
                ->filter(fn ($col) => $col['items'] !== [])
                ->values()
                ->all();
        } catch (\Throwable) {
            return [];
        }
    }

    /**
     * 页眉/页脚菜单树（底座菜单系统；表未就绪时降级为 null，前端回退默认导航）。
     */
    private function menuProps(): array
    {
        try {
            $menus = app(\Miran\Mksine\Services\MenuService::class);

            return [
                'header' => $menus->forLocation('header'),
                'footer' => $menus->forLocation('footer'),
            ];
        } catch (\Throwable) {
            return ['header' => null, 'footer' => null];
        }
    }
}
