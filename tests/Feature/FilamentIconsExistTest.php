<?php

namespace Tests\Feature;

use PHPUnit\Framework\TestCase as BaseTestCase;

/**
 * 防回归：全项目 Filament 资源/页面使用的 heroicon 必须真实存在。
 * 背景：不存在的图标名（如 currency-bitcoin / columns-3）会让整个后台侧边栏
 * 渲染抛 SvgNotFound，导致所有 admin 页面 500。
 */
class FilamentIconsExistTest extends BaseTestCase
{
    public function test_all_used_heroicons_exist_in_blade_heroicons(): void
    {
        $iconDir = dirname(__DIR__, 2).'/vendor/blade-ui-kit/blade-heroicons/resources/svg';
        $this->assertDirectoryExists($iconDir, 'blade-heroicons 未安装');

        // 扫描 app/ 与 modules/ 下所有 PHP 文件中的 heroicon 引用
        $icons = [];
        $dirs = [dirname(__DIR__, 2).'/app', dirname(__DIR__, 2).'/modules'];

        foreach ($dirs as $dir) {
            $it = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($dir));
            foreach ($it as $file) {
                if ($file->getExtension() !== 'php') {
                    continue;
                }
                preg_match_all('/heroicon-[oc]-[a-z0-9\-]+/', (string) file_get_contents($file->getPathname()), $m);
                foreach ($m[0] as $icon) {
                    $icons[$icon] = true;
                }
            }
        }

        $this->assertNotEmpty($icons, '未扫描到任何 heroicon 引用，检查扫描路径');

        $missing = [];
        foreach (array_keys($icons) as $icon) {
            $name = substr($icon, strlen('heroicon-')); // o-xxx / c-xxx
            if (! is_file($iconDir.'/'.$name.'.svg')) {
                $missing[] = $icon;
            }
        }

        $this->assertSame([], $missing, '以下 heroicon 在图标集中不存在（会导致后台 500）: '.implode(', ', $missing));
    }
}
