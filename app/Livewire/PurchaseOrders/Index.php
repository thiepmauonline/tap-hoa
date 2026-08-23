<?php

namespace App\Livewire\PurchaseOrders;

use Livewire\Component;
use Livewire\WithPagination;
use App\Models\PurchaseOrder;

class Index extends Component
{
    use WithPagination;

    protected string $paginationTheme = 'bootstrap';

    public function render()
    {
        $storeId = auth()->user()->store_id;

        $orders = PurchaseOrder::where('store_id', $storeId)
            ->with(['supplier', 'user', 'items.product'])
            ->orderBy('purchase_date', 'desc')
            ->paginate(10);

        return view('livewire.purchase-orders.index', [
            'orders' => $orders,
        ])->layout('layouts.app', ['headerTitle' => 'Quản lý Nhập hàng Kho']);
    }
}
