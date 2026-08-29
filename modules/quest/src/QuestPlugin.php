<?php

declare(strict_types=1);

namespace Modules\Quest;

use Illuminate\Support\Facades\Event;
use Miran\Mksine\Core\Events\Posts\PostCreated;
use Miran\Mksine\Core\Hooks\Hooks;
use Miran\Mksine\Core\Plugins\Contracts\PluginInterface;
use Miran\Mksine\Core\Shortcodes\ShortcodeCatalogEntry;
use Modules\Quest\Listeners\RewardArticlePublish;
use Modules\Quest\Models\Quest;
use Modules\Quest\Services\CheckinService;
use Modules\Quest\Shortcodes\CheckinStatusShortcode;

class QuestPlugin implements PluginInterface
{
    /**
     * Get the unique plugin identifier.
     */
    public function id(): string
    {
        return 'quest';
    }

    /**
     * Called when plugin is first installed.
     */
    public function install(): void
    {
        if (! \Illuminate\Support\Facades\Schema::hasTable('quests')) {
            return;
        }

        // 默认任务规则（后台可改奖励数额/货币）
        Quest::query()->firstOrCreate(
            ['key' => 'daily_checkin'],
            ['name' => '每日签到', 'reward_currency' => 'gold', 'reward_amount' => 5, 'daily_limit' => 1],
        );
        Quest::query()->firstOrCreate(
            ['key' => 'publish_article'],
            ['name' => '发布文章', 'reward_currency' => 'gold', 'reward_amount' => 20, 'daily_limit' => 10],
        );
        Quest::query()->firstOrCreate(
            ['key' => 'publish_topic'],
            ['name' => '发帖', 'reward_currency' => 'gold', 'reward_amount' => 10, 'daily_limit' => 10],
        );
    }

    /**
     * Called when plugin is activated.
     */
    public function activate(): void
    {
        // 依赖自校验：quest 依赖 user + economy
        foreach (['user', 'economy'] as $required) {
            if (! module_enabled($required)) {
                throw new \RuntimeException("模块 quest 依赖 {$required}，请先安装并启用。");
            }
        }
    }

    /**
     * Called when plugin is deactivated.
     */
    public function deactivate(): void
    {
        // Note: Data should NOT be deleted here
    }

    /**
     * Called when plugin is uninstalled.
     */
    public function uninstall(bool $deleteData = false): void
    {
        if ($deleteData) {
            // Delete tables, files, etc.
        }
    }

    /**
     * Called on every request when plugin is active.
     */
    public function boot(): void
    {
        app()->singleton(CheckinService::class);

        // 发布内容奖励：监听底座 post.created（运行时注册，每请求生效）
        Event::listen(PostCreated::class, RewardArticlePublish::class);

        // 签到状态短码
        Hooks::addShortcode('checkin_status', CheckinStatusShortcode::class, priority: 10, catalog: new ShortcodeCatalogEntry(
            tag: 'checkin_status',
            label: '签到状态',
            description: '显示今日签到按钮/已签到标记',
            example: '[checkin_status]',
        ));

        // 模块视图命名空间
        $viewsPath = $this->viewsPath();
        if ($viewsPath && is_dir($viewsPath)) {
            view()->addNamespace('quest', $viewsPath);
        }
    }

    /**
     * Get the plugin's migrations path.
     */
    public function migrationsPath(): ?string
    {
        return __DIR__.'/../database/migrations';
    }

    /**
     * Get the plugin's config path.
     */
    public function configPath(): ?string
    {
        return __DIR__.'/../config';
    }

    /**
     * Get the plugin's views path.
     */
    public function viewsPath(): ?string
    {
        return __DIR__.'/../resources/views';
    }

    /**
     * Get the plugin's web routes path.
     */
    public function webRoutesPath(): ?string
    {
        return __DIR__.'/../routes/web.php';
    }

    /**
     * Get the plugin's API routes path.
     */
    public function apiRoutesPath(): ?string
    {
        return null;
    }

    /**
     * Get the plugin's translations path.
     */
    public function translationsPath(): ?string
    {
        return __DIR__.'/../resources/lang';
    }

    /**
     * Get the plugin's Filament resources path.
     */
    public function filamentResourcesPath(): ?string
    {
        return is_dir($p = __DIR__.'/Filament/Resources') ? $p : null;
    }

    /**
     * Get the plugin's Filament pages path.
     */
    public function filamentPagesPath(): ?string
    {
        return is_dir($p = __DIR__.'/Filament/Pages') ? $p : null;
    }

    /**
     * Get the plugin's Filament widgets path.
     */
    public function filamentWidgetsPath(): ?string
    {
        return is_dir($p = __DIR__.'/Filament/Widgets') ? $p : null;
    }

    /**
     * Get the plugin's namespace.
     */
    public function namespace(): ?string
    {
        return 'Modules\\Quest';
    }
}
