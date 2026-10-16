<?php

namespace App\Livewire\Reports;

use App\Models\OrderItem;
use App\Models\Product;
use App\Services\BusinessReportService;
use Carbon\Carbon;
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

    public function render(BusinessReportService $reports)
    {
        $this->validate([
            'dateFrom' => 'required|date',
            'dateTo' => 'required|date|after_or_equal:dateFrom',
            'slowStockDays' => 'required|integer|min:7|max:3650',
        ]);

        $storeId = (int) auth()->user()->store_id;
        $from = Carbon::parse($this->dateFrom)->startOfDay();
        $to = Carbon::parse($this->dateTo)->endOfDay();

        $summary = $reports->summary($storeId, $from, $to);
        $dailyRevenue = $reports->dailyRevenue($storeId, $from, $to);
        $topProducts = $reports->topProducts($storeId, $from, $to);

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
            'summary', 'dailyRevenue', 'topProducts', 'slowStockProducts'
        ))->layout('layouts.app', ['headerTitle' => 'Báo cáo Kinh doanh']);
    }
}
