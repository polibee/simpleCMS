<?php

declare(strict_types=1);

namespace Modules\CMS\Filament\Pages\Settings;

use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Miran\Mksine\Filament\Pages\Settings\MksSettingsPage;
use Miran\Mksine\Models\Post;
use Modules\CMS\Support\CmsPermalink;

/**
 * CMS 固定链接设置页（WordPress 式链接结构）。
 * 后台 → 设置集群 → CMS 固定链接。
 */
class CmsPermalinkSettingsPage extends MksSettingsPage
{
    protected string $view = 'mksine::filament.pages.mks-settings-page';

    public static function canAccess(): bool
    {
        $user = auth()->user();

        // Shield 生成的页面权限可能尚未同步给角色；super_admin 直接放行
        if ($user && method_exists($user, 'hasRole') && $user->hasRole('super_admin')) {
            return true;
        }

        return parent::canAccess();
    }

    public static function getNavigationLabel(): string
    {
        return __('CMS 固定链接');
    }

    public function getTitle(): string|\Illuminate\Contracts\Support\Htmlable
    {
        return __('CMS 固定链接');
    }

    protected function settingsSchema(): array
    {
        return [
            Section::make('文章固定链接')
                ->description('类似 WordPress 的固定链接设置：保存后前台文章地址立即按新结构生效，旧默认链接自动 301 跳转。')
                ->schema([
                    TextInput::make(CmsPermalink::POSTS_URL_KEY)
                        ->label(__('文章列表前缀'))
                        ->placeholder(CmsPermalink::DEFAULT_POSTS_URL)
                        ->maxLength(100)
                        ->rules([
                            'required',
                            'regex:/^\/[a-z0-9\-\/]*$/',
                            function (string $attribute, mixed $value, \Closure $fail): void {
                                if (! CmsPermalink::isValidPostsUrl((string) $value)) {
                                    $fail('列表前缀仅允许小写字母、数字、连字符与斜杠，且不能包含占位符。');
                                }
                            },
                        ])
                        ->helperText('默认 '.CmsPermalink::DEFAULT_POSTS_URL.'；仅小写字母/数字/连字符/斜杠'),
                    TextInput::make(CmsPermalink::SINGLE_POST_URL_KEY)
                        ->label(__('文章详情模式'))
                        ->placeholder(CmsPermalink::DEFAULT_SINGLE_POST_URL)
                        ->maxLength(100)
                        ->rules([
                            'required',
                            function (string $attribute, mixed $value, \Closure $fail): void {
                                if (! CmsPermalink::isValidSinglePattern((string) $value)) {
                                    $fail('模式需以 / 开头，仅含小写字母、数字、连字符、斜杠，且必须包含 {slug} 或 {id} 占位符。示例：/post/{slug}');
                                }
                            },
                        ])
                        ->helperText('支持占位符 {slug}（推荐，SEO 友好）或 {id}。示例：'.CmsPermalink::DEFAULT_SINGLE_POST_URL.'、/post/{slug}、/blog/{slug}.html'),
                ])
                ->columns(1),
        ];
    }

    public function saveData(): void
    {
        parent::saveData();

        // permalink 影响路由注册（底座 saveData 只对底座 keys 清路由缓存）
        \Illuminate\Support\Facades\Artisan::call('route:clear');
        \Illuminate\Support\Facades\Artisan::call('config:clear');

        \Filament\Notifications\Notification::make()
            ->title(__('固定链接已更新，前台地址已按新结构生效'))
            ->success()
            ->send();
    }
}
