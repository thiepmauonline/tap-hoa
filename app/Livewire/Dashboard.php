<?php

namespace App\Livewire;

use Livewire\Component;
use App\Models\Order;
use App\Models\Product;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class Dashboard extends Component
{
    public function render()
    {
        $storeId = auth()->user()->store_id;

        // KPI Thống kê hôm nay
        $todayRevenue = Order::where('store_id', $storeId)
            ->whereDate('order_date', Carbon::today())
            ->where('status', 'completed')
            ->sum('total_amount');

        $todayOrdersCount = Order::where('store_id', $storeId)
            ->whereDate('order_date', Carbon::today())
            ->where('status', 'completed')
            ->count();

        $totalProductsCount = Product::where('store_id', $storeId)
            ->where('status', 1)
            ->count();

        // Sản phẩm sắp hết kho (tồn <= min_stock)
        $lowStockProducts = Product::where('store_id', $storeId)
            ->where('status', 1)
            ->whereHas('inventory', function ($q) {
                $q->whereColumn('inventories.quantity', '<=', 'products.min_stock');
            })
            ->with(['inventory', 'category'])
            ->get();

        $lowStockCount = $lowStockProducts->count();

        // 5 Đơn hàng gần đây
        $recentOrders = Order::where('store_id', $storeId)
            ->with(['customer', 'user'])
            ->orderBy('order_date', 'desc')
            ->take(5)
            ->get();

        // Dữ liệu Biểu đồ Doanh thu 7 ngày gần nhất (Chart.js)
        $revenueByDate = Order::where('store_id', $storeId)
            ->where('status', 'completed')
            ->whereBetween('order_date', [Carbon::today()->subDays(6)->startOfDay(), Carbon::today()->endOfDay()])
            ->selectRaw('DATE(order_date) as sale_date, SUM(total_amount) as revenue')
            ->groupBy(DB::raw('DATE(order_date)'))
            ->pluck('revenue', 'sale_date');

        $chartLabels = [];
        $chartValues = [];
        for ($i = 6; $i >= 0; $i--) {
            $date = Carbon::today()->subDays($i);
            $chartLabels[] = $date->format('d/m');
            $chartValues[] = (float) ($revenueByDate[$date->toDateString()] ?? 0);
        }

        return view('livewire.dashboard', [
            'todayRevenue' => $todayRevenue,
            'todayOrdersCount' => $todayOrdersCount,
            'totalProductsCount' => $totalProductsCount,
            'lowStockCount' => $lowStockCount,
            'lowStockProducts' => $lowStockProducts,
            'recentOrders' => $recentOrders,
            'chartLabels' => $chartLabels,
            'chartValues' => $chartValues,
        ])->layout('layouts.app', ['headerTitle' => 'Bảng điều khiển & Thống kê']);
    }
}
