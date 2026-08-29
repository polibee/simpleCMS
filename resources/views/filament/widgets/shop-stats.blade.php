<div class="space-y-4">
    @php $summary = $this->summary(); $daily = $this->daily(); $maxRevenue = max(0.01, ...array_column($daily, 'revenue')); @endphp

    <div class="grid grid-cols-1 gap-4 lg:grid-cols-4">
        @foreach ([
            ['近 14 天销售额', '$'.number_format($summary['revenue14'], 2)],
            ['近 14 天订单', $summary['orders14']],
            ['待支付订单', $summary['pending']],
            ['在售商品', $summary['products']],
        ] as [$label, $value])
            <div class="rounded-xl border border-gray-200 bg-white p-4 shadow-sm dark:border-white/10 dark:bg-gray-900">
                <p class="text-xs text-gray-500 dark:text-gray-400">{{ $label }}</p>
                <p class="mt-1 text-xl font-bold text-gray-900 dark:text-white">{{ $value }}</p>
            </div>
        @endforeach
    </div>

    <div class="rounded-xl border border-gray-200 bg-white p-5 shadow-sm dark:border-white/10 dark:bg-gray-900">
        <h3 class="text-sm font-semibold text-gray-700 dark:text-gray-300">近 14 天销售额 / 订单量（已支付）</h3>
        <div class="mt-4 flex items-end gap-1.5" style="height: 110px">
            @foreach ($daily as $d)
                @php $pct = (int) ($d['revenue'] / $maxRevenue * 100); @endphp
                <div class="flex flex-1 flex-col items-center justify-end gap-0.5"
                     title="{{ $d['date'] }}：${{ number_format($d['revenue'], 2) }} / {{ $d['orders'] }} 单">
                    <div class="flex w-full items-end justify-center" style="height: 80px">
                        <div class="w-3 rounded-t bg-primary-500" style="height: {{ $pct }}%; min-height: {{ $d['revenue'] > 0 ? 3 : 0 }}px"></div>
                    </div>
                    <span class="text-[9px] text-gray-400">{{ $d['date'] }}</span>
                </div>
            @endforeach
        </div>
    </div>

    @if ($this->topProducts())
        <div class="rounded-xl border border-gray-200 bg-white p-5 shadow-sm dark:border-white/10 dark:bg-gray-900">
            <h3 class="text-sm font-semibold text-gray-700 dark:text-gray-300">商品销量排行</h3>
            @foreach ($this->topProducts() as $p)
                <div class="mt-2 flex items-center justify-between text-sm">
                    <span class="text-gray-700 dark:text-gray-300">{{ $p->name }}</span>
                    <span class="text-xs text-gray-400">销量 {{ $p->sold }} · 库存 {{ $p->stock }}</span>
                </div>
            @endforeach
        </div>
    @endif
</div>
