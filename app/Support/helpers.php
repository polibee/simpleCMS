<?php

use Miran\Mksine\Core\Plugins\PluginManager;

if (! function_exists('module_enabled')) {
    /**
     * 检查某模块（MKSINE 插件）是否已安装且启用。
     * 数据库未就绪（如迁移期）时视为未启用。
     */
    function module_enabled(string $moduleId): bool
    {
        try {
            $manager = app(PluginManager::class);
            if (! $manager->isInitialized()) {
                $manager->initialize();
            }

            return $manager->getRegistry()->isActive($moduleId);
        } catch (\Throwable) {
            return false;
        }
    }
}

if (! function_exists('module_installed')) {
    /**
     * 检查某模块是否已安装（不要求启用）。
     */
    function module_installed(string $moduleId): bool
    {
        try {
            $manager = app(PluginManager::class);
            if (! $manager->isInitialized()) {
                $manager->initialize();
            }

            return $manager->getRegistry()->isInstalled($moduleId);
        } catch (\Throwable) {
            return false;
        }
    }
}
