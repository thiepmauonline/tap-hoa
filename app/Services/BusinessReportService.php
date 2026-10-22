<?php

namespace App\Services;

use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use Carbon\Carbon;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class BusinessReportService
{
    private function ordersWithCost(int $storeId, CarbonInterface $from, CarbonInterface $to): Builder
    {
        $costs = OrderItem::query()
            ->selectRaw('order_id, SUM(cost_price * quantity) as cost')
            ->groupBy('order_id');

        return Order::query()
            ->leftJoinSub($costs, 'item_costs', 'item_costs.order_id', '=', 'orders.id')
            ->where('orders.store_id', $storeId)
            ->where('orders.status', 'completed')
            ->whereBetween('orders.order_date', [$from, $to]);
    }

    public function summary(int $storeId, CarbonInterface $from, CarbonInterface $to): object
    {
        return $this->ordersWithCost($storeId, $from, $to)
            ->selectRaw('COUNT(*) as order_count,
                COALESCE(SUM(orders.total_amount), 0) as revenue,
                COALESCE(AVG(orders.total_amount), 0) as average_order,
                COALESCE(SUM(item_costs.cost), 0) as cost,
                COALESCE(SUM(orders.total_amount - COALESCE(item_costs.cost, 0)), 0) as gross_profit')
            ->first();
    }

    public function dailyRevenue(int $storeId, CarbonInterface $from, CarbonInterface $to): Collection
    {
        return $this->ordersWithCost($storeId, $from, $to)
            ->selectRaw('DATE(orders.order_date) as sale_date, COUNT(*) as order_count,
                SUM(orders.total_amount) as revenue, SUM(orders.discount_amount) as discount,
                SUM(COALESCE(item_costs.cost, 0)) as cost,
                SUM(orders.total_amount - COALESCE(item_costs.cost, 0)) as gross_profit')
            ->groupBy(DB::raw('DATE(orders.order_date)'))
            ->orderBy('sale_date')->get();
    }

    public function topProducts(int $storeId, CarbonInterface $from, CarbonInterface $to, string $sort = 'quantity', int $limit = 10): Collection
    {
        return OrderItem::query()
            ->join('orders', 'orders.id', '=', 'order_items.order_id')
            ->leftJoin('products', 'products.id', '=', 'order_items.product_id')
            ->where('orders.store_id', $storeId)
            ->where('orders.status', 'completed')
            ->whereBetween('orders.order_date', [$from, $to])
            ->groupBy('order_items.product_id', 'products.name', 'products.unit')
            ->selectRaw('order_items.product_id, products.name, products.unit,
                SUM(order_items.quantity) as quantity_sold,
                SUM(CASE WHEN orders.subtotal_amount > 0
                    THEN 1.0 * order_items.subtotal * orders.total_amount / orders.subtotal_amount ELSE 0 END) as revenue')
            ->orderByDesc($sort === 'revenue' ? 'revenue' : 'quantity_sold')->orderBy('order_items.product_id')->limit($limit)->get();
    }

    public function slowStock(int $storeId, int $days, int $limit = 20): Collection
    {
        $lastSoldDates = OrderItem::query()
            ->join('orders', 'orders.id', '=', 'order_items.order_id')
            ->where('orders.store_id', $storeId)->where('orders.status', 'completed')
            ->where('orders.order_date', '<=', now())
            ->groupBy('order_items.product_id')
            ->selectRaw('order_items.product_id, MAX(orders.order_date) as last_sold_at')
            ->pluck('last_sold_at', 'product_id');
        $cutoff = now()->subDays($days);

        return Product::where('store_id', $storeId)->where('status', 1)
            ->whereHas('inventory', fn ($query) => $query->where('quantity', '>', 0))
            ->with(['inventory', 'category', 'supplier'])->get()
            ->map(function (Product $product) use ($lastSoldDates) {
                $lastSold = $lastSoldDates->get($product->id);
                $product->last_sold_at = $lastSold ? Carbon::parse($lastSold) : null;

                return $product;
            })
            ->filter(function (Product $product) use ($cutoff) {
                $since = $product->last_sold_at ?? $product->inventory->last_imported_at ?? $product->created_at;

                return $since->lte($cutoff);
            })
            ->sortBy(fn (Product $product) => ($product->last_sold_at ?? $product->inventory->last_imported_at ?? $product->created_at)->timestamp)
            ->take($limit)->values();
    }
}
