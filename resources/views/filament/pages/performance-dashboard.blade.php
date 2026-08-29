<x-filament-panels::page>
    @php
        $stats = $this->stats();
        $today = $this->today();
        $redis = $this->redis();
        $opcache = $this->opcache();
        $octane = $this->octane();
        $horizon = $this->horizon();
        $maxBar = max(1, ...array_map(fn ($s) => max($s['hits'], $s['misses']), $stats));
        $cacheOn = $this->pageCacheEnabled();
    @endphp

    {{-- 概览卡 --}}
    <div class="grid grid-cols-1 gap-4 lg:grid-cols-4">
        <div class="rounded-xl border border-gray-200 bg-white p-5 shadow-sm dark:border-white/10 dark:bg-gray-900">
            <div class="flex items-center justify-between">
                <h3 class="text-sm font-semibold text-gray-700 dark:text-gray-300">页面缓存</h3>
                <span class="rounded-full px-2 py-0.5 text-xs font-medium {{ $cacheOn ? 'bg-emerald-100 text-emerald-700 dark:bg-emerald-900/40 dark:text-emerald-300' : 'bg-gray-100 text-gray-500 dark:bg-gray-800' }}">
                    {{ $cacheOn ? '运行中' : '已关闭' }}
                </span>
            </div>
            <p class="mt-3 text-2xl font-bold text-gray-900 dark:text-white">{{ $today['rate'] === null ? '—' : $today['rate'].'%' }}</p>
            <p class="text-xs text-gray-500 dark:text-gray-400">今日命中率 · 引擎 {{ $this->cacheStore() }}</p>
            <div class="mt-3 flex flex-wrap gap-2">
                <button type="button" wire:click="clearPageCache"
                        class="rounded-md border px-2.5 py-1 text-xs text-gray-600 hover:bg-gray-50 dark:text-gray-300 dark:hover:bg-white/5">清除页面缓存</button>
                <button type="button" wire:click="flushApplicationCache"
                        class="rounded-md border px-2.5 py-1 text-xs text-gray-600 hover:bg-gray-50 dark:text-gray-300 dark:hover:bg-white/5">清空应用缓存</button>
            </div>
        </div>

        <div class="rounded-xl border border-gray-200 bg-white p-5 shadow-sm dark:border-white/10 dark:bg-gray-900">
            <div class="flex items-center justify-between">
                <h3 class="text-sm font-semibold text-gray-700 dark:text-gray-300">Redis</h3>
                <span class="rounded-full px-2 py-0.5 text-xs font-medium {{ $redis['available'] ? 'bg-emerald-100 text-emerald-700 dark:bg-emerald-900/40 dark:text-emerald-300' : 'bg-red-100 text-red-700 dark:bg-red-900/40 dark:text-red-300' }}">
                    {{ $redis['available'] ? '已连接' : '不可用' }}
                </span>
            </div>
            @if ($redis['available'])
                <p class="mt-3 text-2xl font-bold text-gray-900 dark:text-white">{{ $redis['hit_rate'] === null ? '—' : $redis['hit_rate'].'%' }}</p>
                <p class="text-xs text-gray-500 dark:text-gray-400">
                    v{{ $redis['version'] }} · {{ $redis['used_memory_human'] }} · {{ $redis['keys'] }} 键
                </p>
            @else
                <p class="mt-3 text-sm text-gray-500 dark:text-gray-400">{{ $redis['error'] ?: '未检测到 Redis 服务' }}</p>
            @endif
        </div>

        <div class="rounded-xl border border-gray-200 bg-white p-5 shadow-sm dark:border-white/10 dark:bg-gray-900">
            <div class="flex items-center justify-between">
                <h3 class="text-sm font-semibold text-gray-700 dark:text-gray-300">OPcache</h3>
                <span class="rounded-full px-2 py-0.5 text-xs font-medium {{ $opcache['enabled'] ? 'bg-emerald-100 text-emerald-700 dark:bg-emerald-900/40 dark:text-emerald-300' : 'bg-gray-100 text-gray-500 dark:bg-gray-800' }}">
                    {{ $opcache['enabled'] ? '已启用' : '未启用' }}
                </span>
            </div>
            @if ($opcache['enabled'])
                <p class="mt-3 text-2xl font-bold text-gray-900 dark:text-white">{{ $opcache['hit_rate'] ?? '—' }}%</p>
                <p class="text-xs text-gray-500 dark:text-gray-400">
                    内存 {{ $opcache['memory_used_mb'] }} / {{ $opcache['memory_total_mb'] }} MB · {{ $opcache['cached_scripts'] }} 脚本
                </p>
                <div class="mt-3">
                    <button type="button" wire:click="resetOpcache"
                            class="rounded-md border px-2.5 py-1 text-xs text-gray-600 hover:bg-gray-50 dark:text-gray-300 dark:hover:bg-white/5">重置 OPcache</button>
                </div>
            @else
                <p class="mt-3 text-sm text-gray-500 dark:text-gray-400">在 php.ini 开启 opcache.enable=1 后生效</p>
            @endif
        </div>

        <div class="rounded-xl border border-gray-200 bg-white p-5 shadow-sm dark:border-white/10 dark:bg-gray-900">
            <h3 class="text-sm font-semibold text-gray-700 dark:text-gray-300">队列与常驻</h3>
            <ul class="mt-3 space-y-1.5 text-xs text-gray-600 dark:text-gray-400">
                <li>队列驱动：<span class="font-medium text-gray-900 dark:text-white">{{ $this->queueConnection() }}</span></li>
                <li>
                    Horizon {{ $horizon['installed'] ? 'v'.$horizon['version'] : '未安装' }}
                    @if($horizon['installed']) · <a href="/horizon" class="text-primary-600 hover:underline">进入仪表盘</a>@endif
                </li>
                <li>Octane {{ $octane['installed'] ? 'v'.$octane['version'] : '未安装' }}</li>
                <li>页面缓存引擎：内置（WP Super Cache 式整页缓存）</li>
            </ul>
        </div>
    </div>

    {{-- 14 天命中图表 --}}
    <div class="mt-4 rounded-xl border border-gray-200 bg-white p-5 shadow-sm dark:border-white/10 dark:bg-gray-900">
        <div class="flex items-center justify-between">
            <h3 class="text-sm font-semibold text-gray-700 dark:text-gray-300">页面缓存命中 / 未命中（最近 14 天）</h3>
            <p class="text-xs text-gray-400">命中=直接返回缓存页 · 未命中=动态渲染（记录耗时）</p>
        </div>

        <div class="mt-5 flex items-end gap-1.5" style="height: 140px">
            @foreach ($stats as $s)
                @php $max = max($s['hits'], $s['misses'], 1); @endphp
                <div class="flex flex-1 flex-col items-center justify-end gap-0.5" title="{{ $s['date'] }}：命中 {{ $s['hits'] }} / 未命中 {{ $s['misses'] }}{{ $s['avg_ms'] !== null ? ' · 平均 '.$s['avg_ms'].'ms' : '' }}">
                    <div class="flex w-full items-end justify-center gap-0.5" style="height: 110px">
                        <div class="w-2.5 rounded-t bg-emerald-500" style="height: {{ (int) ($s['hits'] / $maxBar * 100) }}%; min-height: {{ $s['hits'] > 0 ? 3 : 0 }}px"></div>
                        <div class="w-2.5 rounded-t bg-zinc-300 dark:bg-zinc-700" style="height: {{ (int) ($s['misses'] / $maxBar * 100) }}%; min-height: {{ $s['misses'] > 0 ? 3 : 0 }}px"></div>
                    </div>
                    <span class="text-[9px] text-gray-400">{{ $s['date'] }}</span>
                </div>
            @endforeach
        </div>

        <div class="mt-3 flex gap-4 text-xs text-gray-500 dark:text-gray-400">
            <span class="flex items-center gap-1"><span class="inline-block h-2.5 w-2.5 rounded-sm bg-emerald-500"></span>命中（缓存）</span>
            <span class="flex items-center gap-1"><span class="inline-block h-2.5 w-2.5 rounded-sm bg-zinc-300 dark:bg-zinc-700"></span>未命中（动态渲染）</span>
        </div>
    </div>

    <div class="mt-4 rounded-xl border border-gray-200 bg-white p-5 text-sm text-gray-500 shadow-sm dark:border-white/10 dark:bg-gray-900 dark:text-gray-400">
        缓存策略与 WP Super Cache 一致：仅缓存<b class="text-gray-700 dark:text-gray-300">游客</b>的 GET 200 页面，后台/登录/创作中心/邀请码等动态路径自动排除；
        登录用户永远走实时渲染。开关与 TTL 在「设置集群 → 站点设置 → 性能优化」配置；调试可在任意 URL 后加 <code>?nocache=1</code> 绕过缓存。
    </div>
</x-filament-panels::page>
