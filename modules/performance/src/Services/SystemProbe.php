<?php

declare(strict_types=1);

namespace Modules\Performance\Services;

use Composer\InstalledVersions;

/**
 * 系统环境探测：Redis / OPcache / Octane / Horizon 安装与运行状态。
 * 结果按进程内静态缓存（Redis 探测含真实连接测试，避免重复握手）。
 */
final class SystemProbe
{
    private static ?array $redis = null;

    /** @return array{available: bool, extension: string, version: string, used_memory_human: string, keys: int, hits: int, misses: int, hit_rate: float|null, host: string, port: int, error: string} */
    public static function redis(): array
    {
        if (self::$redis !== null) {
            return self::$redis;
        }

        $base = [
            'available' => false, 'extension' => '', 'version' => '', 'used_memory_human' => '',
            'keys' => 0, 'hits' => 0, 'misses' => 0, 'hit_rate' => null,
            'host' => config('database.redis.default.host', '127.0.0.1'),
            'port' => (int) config('database.redis.default.port', 6379),
            'error' => '',
        ];

        // 1) phpredis 扩展
        if (extension_loaded('redis')) {
            try {
                $r = new \Redis();
                $auth = config('database.redis.default.password');
                $r->connect($base['host'], $base['port'], 1.0);
                if ($auth) {
                    $r->auth($auth);
                }
                $info = $r->info();

                $hits = (int) ($info['keyspace_hits'] ?? 0);
                $misses = (int) ($info['keyspace_misses'] ?? 0);
                $db = (int) preg_replace('/^db\d+$/', '$1', config('database.redis.default.database', '0'));
                $keys = (int) ($info['keys'.$db] ?? $info['db0']['keys'] ?? 0);

                return self::$redis = [
                    ...$base,
                    'available' => true,
                    'extension' => 'phpredis',
                    'version' => (string) ($info['redis_version'] ?? ''),
                    'used_memory_human' => (string) ($info['used_memory_human'] ?? ''),
                    'keys' => $keys,
                    'hits' => $hits,
                    'misses' => $misses,
                    'hit_rate' => ($hits + $misses) > 0 ? round($hits / ($hits + $misses) * 100, 1) : null,
                ];
            } catch (\Throwable $e) {
                return self::$redis = [...$base, 'error' => $e->getMessage()];
            }
        }

        // 2) predis（纯 PHP 客户端）
        if (class_exists(\Predis\Client::class)) {
            try {
                $client = new \Predis\Client([
                    'host' => $base['host'],
                    'port' => $base['port'],
                    'password' => config('database.redis.default.password'),
                ], ['timeout' => 1]);
                $client->connect();
                $info = $client->info();
                $hits = (int) ($info['Stats']['keyspace_hits'] ?? 0);
                $misses = (int) ($info['Stats']['keyspace_misses'] ?? 0);

                return self::$redis = [
                    ...$base,
                    'available' => true,
                    'extension' => 'predis',
                    'version' => (string) ($info['Server']['redis_version'] ?? ''),
                    'used_memory_human' => (string) ($info['Memory']['used_memory_human'] ?? ''),
                    'keys' => (int) ($info['Keyspace']['db0']['keys'] ?? 0),
                    'hits' => $hits,
                    'misses' => $misses,
                    'hit_rate' => ($hits + $misses) > 0 ? round($hits / ($hits + $misses) * 100, 1) : null,
                ];
            } catch (\Throwable $e) {
                return self::$redis = [...$base, 'error' => $e->getMessage()];
            }
        }

        return self::$redis = [...$base, 'error' => '未安装 phpredis 扩展或 predis 包'];
    }

    /** Redis 是否真实可连接（自动切换缓存驱动用）。 */
    public static function redisAvailable(): bool
    {
        return self::redis()['available'];
    }

    /** @return array{enabled: bool, memory_used_mb: float, memory_total_mb: float, hit_rate: float|null, cached_scripts: int, restarts: int} */
    public static function opcache(): array
    {
        $base = ['enabled' => false, 'memory_used_mb' => 0, 'memory_total_mb' => 0, 'hit_rate' => null, 'cached_scripts' => 0, 'restarts' => 0];

        if (! function_exists('opcache_get_status')) {
            return $base;
        }

        $status = @opcache_get_status(false);
        if ($status === false || ! isset($status['memory_usage'])) {
            return $base; // opcache.enable=0
        }

        $mem = $status['memory_usage'];
        $total = (float) ($mem['total_memory'] ?? 0) / 1048576;
        $free = (float) ($mem['free_memory'] ?? 0) / 1048576;
        $used = round($total - $free, 2);

        return [
            'enabled' => true,
            'memory_used_mb' => $used,
            'memory_total_mb' => round($total, 2),
            'hit_rate' => isset($status['opcache_statistics']['opcache_hit_rate'])
                ? round((float) $status['opcache_statistics']['opcache_hit_rate'], 1) : null,
            'cached_scripts' => (int) ($status['opcache_statistics']['num_cached_scripts'] ?? 0),
            'restarts' => (int) (($status['opcache_statistics']['oom_restarts'] ?? 0)
                + ($status['opcache_statistics']['hash_restarts'] ?? 0)
                + ($status['opcache_statistics']['manual_restarts'] ?? 0)),
        ];
    }

    public static function opcacheReset(): bool
    {
        return function_exists('opcache_reset') && @opcache_reset();
    }

    /** @return array{installed: bool, version: string} */
    public static function octane(): array
    {
        return [
            'installed' => class_exists(\Laravel\Octane\Octane::class),
            'version' => self::packageVersion('laravel/octane'),
        ];
    }

    /** @return array{installed: bool, version: string} */
    public static function horizon(): array
    {
        return [
            'installed' => class_exists(\Laravel\Horizon\Horizon::class),
            'version' => self::packageVersion('laravel/horizon'),
        ];
    }

    /** @return array{installed: bool, version: string} */
    public static function responsecache(): array
    {
        return [
            'installed' => class_exists(\Spatie\ResponseCache\ResponseCacheServiceProvider::class),
            'version' => self::packageVersion('spatie/laravel-responsecache'),
        ];
    }

    private static function packageVersion(string $package): string
    {
        try {
            return class_exists(InstalledVersions::class) && InstalledVersions::isInstalled($package)
                ? (string) InstalledVersions::getPrettyVersion($package) : '';
        } catch (\Throwable) {
            return '';
        }
    }
}
