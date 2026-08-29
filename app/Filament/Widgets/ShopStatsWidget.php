<?php

declare(strict_types=1);

namespace App\Filament\Widgets;

use Filament\Widgets\Widget;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * 商城统计 Widget（后台 Dashboard）：14 天销售额/订单量 + TOP5 商品。
 */
class ShopStatsWidget extends Widget
{
    protected string $view = 'filament.widgets.shop-stats';

    protected int|string|array $columnSpan = 'full';

    protected static ?int $sort = 3;

    public function daily(): array
    {
        $from = Carbon::today()->subDays(13)->toDateString();

        try {
            $pair = DB::table('shop_orders')
                ->where('status', 'paid')
                ->where('paid_at', '>=', $from)
                ->selectRaw('DATE(paid_at) as d, COUNT(*) as orders, SUM(amount) as revenue')
                ->groupBy('d')
                ->get()
                ->keyBy('d');
        } catch (\Throwable) {
            $pair = collect();
        }

        $out = [];
        for ($i = 13; $i >= 0; $i--) {
            $date = Carbon::today()->subDays($i)->toDateString();
            $row = $pair->get($date);
            $out[] = [
                'date' => Carbon::parse($date)->format('m-d'),
                'revenue' => round((float) ($row->revenue ?? 0), 2),
                'orders' => (int) ($row->orders ?? 0),
            ];
        }

        return $out;
    }

    public function summary(): array
    {
        $from = Carbon::today()->subDays(13)->toDateString();

        try {
            $revenue = (float) DB::table('shop_orders')
                ->where('status', 'paid')->where('paid_at', '>=', $from)->sum('amount');
            $orders = DB::table('shop_orders')
                ->where('status', 'paid')->where('paid_at', '>=', $from)->count();
            $pending = DB::table('shop_orders')->where('status', 'pending')->count();
            $products = DB::table('shop_products')->where('status', 'on_sale')->count();
        } catch (\Throwable) {
            return ['revenue14' => 0, 'orders14' => 0, 'pending' => 0, 'products' => 0];
        }

        return [
            'revenue14' => round($revenue, 2),
            'orders14' => $orders,
            'pending' => $pending,
            'products' => $products,
        ];
    }

    public function topProducts(): array
    {
        try {
            return DB::table('shop_products')
                ->orderByDesc('sold')->limit(5)
                ->get(['name', 'sold', 'price', 'stock'])->all();
        } catch (\Throwable) {
            return [];
        }
    }
}
