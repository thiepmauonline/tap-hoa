<?php

namespace App\Livewire\Reports;

use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Livewire\Component;

class Business extends Component
{
    public string $dateFrom;
    public string $dateTo;
    public int $slowStockDays = 60;

    public function mount(): void
    {
        $this->dateFrom = now()->subDays(29)->toDateString();
        $this->dateTo = now()->toDateString();
    }

    public function render()
    {
        $this->validate([
            'dateFrom' => 'required|date',
            'dateTo' => 'required|date|after_or_equal:dateFrom',
            'slowStockDays' => 'required|integer|min:7|max:3650',
        ]);

        $storeId = (int) auth()->user()->store_id;
        $from = Carbon::parse($this->dateFrom)->startOfDay();
        $to = Carbon::parse($this->dateTo)->endOfDay();

        $orders = Order::where('store_id', $storeId)
            ->where('status', 'completed')
            ->whereBetween('order_date', [$from, $to]);
        $summary = (clone $orders)->selectRaw('COUNT(*) as order_count, COALESCE(SUM(total_amount), 0) as revenue, COALESCE(AVG(total_amount), 0) as average_order')->first();

        $costAndGross = OrderItem::query()
            ->join('orders', 'orders.id', '=', 'order_items.order_id')
            ->where('orders.store_id', $storeId)
            ->where('orders.status', 'completed')
            ->whereBetween('orders.order_date', [$from, $to])
            ->selectRaw('COALESCE(SUM(order_items.cost_price * order_items.quantity), 0) as cost, COALESCE(SUM(order_items.subtotal - order_items.cost_price * order_items.quantity), 0) as gross_profit')
            ->first();

        $dailyRevenue = (clone $orders)
            ->selectRaw('DATE(order_date) as sale_date, SUM(total_amount) as revenue')
            ->groupBy(DB::raw('DATE(order_date)'))
            ->orderBy('sale_date')
            ->get();

        $topProducts = OrderItem::query()
            ->join('orders', 'orders.id', '=', 'order_items.order_id')
            ->leftJoin('products', 'products.id', '=', 'order_items.product_id')
            ->where('orders.store_id', $storeId)
            ->where('orders.status', 'completed')
            ->whereBetween('orders.order_date', [$from, $to])
            ->groupBy('order_items.product_id', 'products.name', 'products.unit')
            ->selectRaw('order_items.product_id, products.name, products.unit, SUM(order_items.quantity) as quantity_sold, SUM(order_items.subtotal) as revenue')
            ->orderByDesc('quantity_sold')
            ->limit(10)
            ->get();

        $lastSoldDates = OrderItem::query()
            ->join('orders', 'orders.id', '=', 'order_items.order_id')
            ->where('orders.store_id', $storeId)
            ->where('orders.status', 'completed')
            ->groupBy('order_items.product_id')
            ->selectRaw('order_items.product_id, MAX(orders.order_date) as last_sold_at')
            ->get()
            ->pluck('last_sold_at', 'product_id');

        $cutoff = now()->subDays($this->slowStockDays);
        $slowStockProducts = Product::where('store_id', $storeId)
            ->where('status', 1)
            ->whereHas('inventory', fn ($query) => $query->where('quantity', '>', 0))
            ->with(['inventory', 'category'])
            ->get()
            ->map(function (Product $product) use ($lastSoldDates) {
                $lastSold = $lastSoldDates->get($product->id);
                $product->last_sold_at = $lastSold ? Carbon::parse($lastSold) : null;
                return $product;
            })
            ->filter(fn (Product $product) => ! $product->last_sold_at || $product->last_sold_at->lt($cutoff))
            ->sortBy(fn (Product $product) => $product->last_sold_at?->timestamp ?? 0)
            ->take(20);

        return view('livewire.reports.business', compact(
            'summary', 'costAndGross', 'dailyRevenue', 'topProducts', 'slowStockProducts'
        ))->layout('layouts.app', ['headerTitle' => 'Báo cáo Kinh doanh']);
    }
}
