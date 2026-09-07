<?php

declare(strict_types=1);

namespace Modules\Performance\Services;

use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * 页面缓存命中统计（按天聚合；中间件每次缓存命中的页面请求调用）。
 */
final class StatsService
{
    /** @return array{hit: bool, ms: int} */
    public static function record(bool $hit, int $ms): void
    {
        try {
            $today = Carbon::today()->toDateString();

            // 唯一键冲突即跳过 → 已有行不会被清零
            DB::table('performance_stats')->insertOrIgnore([
                'date' => $today,
                'hits' => 0,
                'misses' => 0,
                'total_ms' => 0,
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            DB::table('performance_stats')
                ->where('date', $today)
                ->incrementEach($hit
                    ? ['hits' => 1]
                    : ['misses' => 1, 'total_ms' => max(0, $ms)]);
        } catch (\Throwable) {
            // 统计失败不影响页面渲染
        }
    }

    /** 最近 N 天统计（缺失日期补零）。 */
    public static function last(int $days = 14): array
    {
        // 一次性取整行。不要写成两次 pluck('hits'|'misses','date') 再 merge：
        // 同键会被后者覆盖，hits 会被 misses 吃掉（历史上确实这样写过，
        // 随后又补了一次查询绕过，留下的死代码已在此移除）。
        try {
            $pair = DB::table('performance_stats')
                ->where('date', '>=', Carbon::today()->subDays($days - 1)->toDateString())
                ->get(['date', 'hits', 'misses', 'total_ms'])
                ->keyBy('date');
        } catch (\Throwable) {
            $pair = collect();
        }

        $out = [];
        for ($i = $days - 1; $i >= 0; $i--) {
            $date = Carbon::today()->subDays($i)->toDateString();
            $row = $pair->get($date);
            $hits = (int) ($row->hits ?? 0);
            $misses = (int) ($row->misses ?? 0);
            $out[] = [
                'date' => Carbon::parse($date)->format('m-d'),
                'hits' => $hits,
                'misses' => $misses,
                'rate' => ($hits + $misses) > 0 ? round($hits / ($hits + $misses) * 100, 1) : null,
                'avg_ms' => $misses > 0 ? (int) round(($row->total_ms ?? 0) / $misses) : null,
            ];
        }

        return $out;
    }

    public static function today(): array
    {
        return self::last(1)[0];
    }
}
