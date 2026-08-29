<?php

namespace App\Support;

use Miran\Mksine\Core\Plugins\PluginManager;
use ZipArchive;

/**
 * 插件打包器：把已发现的模块目录压缩为可分发的 zip（供插件中心下载）。
 *
 * 排除目录：vendor/node_modules/.git/dist/build 与常见本地产物，
 * 只打包插件自身源码（composer 依赖由使用者按 composer.json 安装）。
 */
final class PluginPackager
{
    /** @var list<string> */
    private const EXCLUDED_DIRS = [
        'node_modules', '.git', '.github', 'tests', 'storage',
        'dist', 'build', 'coverage', '.idea', '.vscode',
    ];

    /**
     * 打包指定插件，返回 [zip 绝对路径, 文件名]；失败抛 RuntimeException。
     *
     * @return array{0: string, 1: string}
     */
    public static function package(string $pluginId): array
    {
        if (! class_exists(ZipArchive::class)) {
            throw new \RuntimeException('PHP 缺少 zip 扩展（php_zip），无法打包下载。');
        }

        $manager = app(PluginManager::class);
        $manager->discover();

        $manifest = $manager->getManifest($pluginId);

        if (! $manifest) {
            throw new \RuntimeException("插件 [{$pluginId}] 不存在或未被发现。");
        }

        $source = rtrim($manifest->basePath(), '/\\');

        if (! is_dir($source)) {
            throw new \RuntimeException("插件目录不存在：{$source}");
        }

        $version = $manifest->version() ?: '1.0.0';
        $filename = "{$pluginId}-{$version}.zip";
        $dir = storage_path('app/plugin-builds');

        if (! is_dir($dir) && ! mkdir($dir, 0755, true) && ! is_dir($dir)) {
            throw new \RuntimeException("无法创建打包输出目录：{$dir}");
        }

        $zipPath = $dir.DIRECTORY_SEPARATOR.$filename;
        // 同名旧包直接覆盖
        if (is_file($zipPath) && ! unlink($zipPath)) {
            throw new \RuntimeException('旧打包文件无法覆盖：'.$zipPath);
        }

        $files = self::collectFiles($source);

        $zip = new ZipArchive();
        $opened = $zip->open($zipPath, ZipArchive::CREATE | ZipArchive::OVERWRITE);

        if ($opened !== true) {
            throw new \RuntimeException("打开 zip 失败（code {$opened}）：{$zipPath}");
        }

        try {
            foreach ($files as $file) {
                $relative = ltrim(str_replace([$source, '\\'], ['', '/'], $file), '/');
                $zip->addFile($file, $pluginId.'/'.$relative);
            }

            // 附带打包清单说明，便于二次分发
            $meta = "Plugin: {$pluginId}\n"
                .'Name: '.$manifest->name()."\n"
                .'Version: '.$version."\n"
                .'Author: '.$manifest->author()."\n"
                .'Packaged at: '.now()->toDateTimeString()."\n";
            $zip->addFromString("{$pluginId}/PLUGIN_PACKAGE.txt", $meta);
        } finally {
            $zip->close();
        }

        return [$zipPath, $filename];
    }

    /**
     * 已发现插件的概要信息（插件中心列表用）。
     *
     * @return list<array{id: string, name: string, version: string, status: string}>
     */
    public static function discoverable(): array
    {
        $manager = app(PluginManager::class);

        // discover() 返回 [plugin_id => PluginManifest] 关联数组
        return collect($manager->discover())
            ->map(function ($_manifest, $pluginId) use ($manager) {
                try {
                    $id = (string) $pluginId;
                    $manifest = $manager->getManifest($id);

                    return [
                        'id' => $id,
                        'name' => $manifest?->name() ?? $id,
                        'version' => $manifest?->version() ?? '-',
                        'status' => method_exists($manager, 'getStatus') ? (string) $manager->getStatus($id) : '-',
                    ];
                } catch (\Throwable) {
                    return null;
                }
            })
            ->filter()
            ->values()
            ->all();
    }

    /** @return list<string> 递归收集源码文件（排除目录/文件后） */
    private static function collectFiles(string $source): array
    {
        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveCallbackFilterIterator(
                new \RecursiveDirectoryIterator($source, \FilesystemIterator::SKIP_DOTS),
                function (\SplFileInfo $current): bool {
                    if ($current->isDir()) {
                        return ! in_array($current->getFilename(), self::EXCLUDED_DIRS, true);
                    }

                    return ! preg_match('/\.(log|lock|cache)$|\.DS_Store/', $current->getFilename());
                },
            ),
            \RecursiveIteratorIterator::LEAVES_ONLY,
        );

        $files = [];
        foreach ($iterator as $file) {
            /** @var \SplFileInfo $file */
            if ($file->isFile()) {
                $files[] = $file->getPathname();
            }
        }
        sort($files);

        return $files;
    }
}
