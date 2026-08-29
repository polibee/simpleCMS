<?php

namespace Modules\Tests;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

/**
 * 模块测试基类。
 *
 * 策略：测试库结构（Laravel + MKSINE + 模块迁移）只跑一次（静态标志），
 * 每个测试手动 truncate 业务表隔离数据 —— 避免 RefreshDatabase 外层事务
 * 与 WalletService 内层 DB::transaction 的嵌套 SAVEPOINT 冲突（Laravel 13 + MySQL 8.4）。
 */
abstract class ModuleTestCase extends TestCase
{
    /** 测试库结构是否已初始化（进程级静态）。 */
    protected static bool $structureReady = false;

    /**
     * 需要在测试库中激活的模块 id 列表（子类可覆盖追加）。
     *
     * @var list<string>
     */
    protected array $activeModules = ['user'];

    /**
     * 每个测试需要清空的表（保留 migrations 等结构表）。
     *
     * @var list<string>
     */
    protected array $truncateTables = [
        'quest_checkins',
        'quests',
        'wallet_ledger',
        'wallets',
        'economy_currency_configs',
        'user_profiles',
        'users',
        'comments',
        'post_tag',
        'tags',
        'cms_post_meta',
        'category_post',
        'categories',
        'posts',
        'ads',
        'invite_codes',
        'invite_orders',
        'shop_orders',
        'shop_products',
        'settings',
        'sessions',
        'performance_stats',
    ];

    protected function setUp(): void
    {
        parent::setUp();

        $this->ensureStructure();

        $this->truncateTables();

        // 预置模块激活状态（mks_plugins 保留不清空，这里写入 user/economy 激活）
        foreach ($this->activeModules as $moduleId) {
            $this->activateModule($moduleId);
        }

        // 引导插件系统：加载模块路由/视图/短码
        $manager = app(\Miran\Mksine\Core\Plugins\PluginManager::class);
        $manager->discover(clearCache: true);
        $manager->initialize();
        $manager->bootPlugins();

        foreach ($this->activeModules as $moduleId) {
            $viewsPath = base_path("modules/{$moduleId}/resources/views");
            if (is_dir($viewsPath)) {
                view()->addNamespace($moduleId, $viewsPath);
            }
        }
    }

    /**
     * 初始化测试库结构：Laravel + MKSINE + 模块迁移，进程内只跑一次。
     */
    protected function ensureStructure(): void
    {
        if (static::$structureReady) {
            return;
        }

        $this->artisan('migrate:fresh', ['--force' => true, '--seed' => false]);

        foreach ($this->allModuleIds() as $moduleId) {
            $migrationsPath = base_path("modules/{$moduleId}/database/migrations");
            if (is_dir($migrationsPath)) {
                $this->artisan('migrate', [
                    '--path' => "modules/{$moduleId}/database/migrations",
                    '--force' => true,
                ]);
            }
        }

        static::$structureReady = true;
    }

    /**
     * 清空业务表（隔离数据），保留结构表。
     */
    protected function truncateTables(): void
    {
        DB::statement('SET FOREIGN_KEY_CHECKS=0');

        foreach ($this->truncateTables as $table) {
            if (Schema::hasTable($table)) {
                DB::table($table)->truncate();
            }
        }

        DB::statement('SET FOREIGN_KEY_CHECKS=1');
    }

    protected function activateModule(string $moduleId): void
    {
        $now = now();

        DB::table('mks_plugins')->updateOrInsert(
            ['plugin_id' => $moduleId],
            [
                'status' => 'active',
                'installed_at' => $now,
                'activated_at' => $now,
                'boot_failed' => 0,
                'created_at' => $now,
                'updated_at' => $now,
            ]
        );
    }

    /**
     * 获取所有模块 id（modules/ 下的目录，含 plugin.php 的）。
     *
     * @return list<string>
     */
    private function allModuleIds(): array
    {
        $ids = [];

        foreach (glob(base_path('modules/*/plugin.php')) ?: [] as $manifest) {
            $ids[] = basename(dirname($manifest));
        }

        return $ids;
    }
}
