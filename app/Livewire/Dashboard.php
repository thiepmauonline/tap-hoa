<?php

namespace App\Livewire;

use Livewire\Component;
use App\Models\Order;
use App\Models\Product;
use Carbon\Carbon;

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
        $chartLabels = [];
        $chartValues = [];

        for ($i = 6; $i >= 0; $i--) {
            $date = Carbon::today()->subDays($i);
            $chartLabels[] = $date->format('d/m');

            $dailyRevenue = Order::where('store_id', $storeId)
                ->whereDate('order_date', $date)
                ->where('status', 'completed')
                ->sum('total_amount');

            $chartValues[] = (float) $dailyRevenue;
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
