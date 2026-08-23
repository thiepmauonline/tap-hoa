<?php

namespace App\Livewire\Orders;

use Livewire\Component;
use Livewire\WithPagination;
use App\Models\Order;

class Index extends Component
{
    use WithPagination;

    protected string $paginationTheme = 'bootstrap';

    public ?int $viewOrderId = null;
    public ?Order $selectedOrder = null;

    public function showDetail(int $id)
    {
        $this->selectedOrder = Order::where('store_id', auth()->user()->store_id)
            ->with(['items.product', 'customer', 'user'])
            ->findOrFail($id);
        $this->viewOrderId = $id;
    }

    public function closeDetail()
    {
        $this->viewOrderId = null;
        $this->selectedOrder = null;
    }

    public function render()
    {
        $storeId = auth()->user()->store_id;

        $orders = Order::where('store_id', $storeId)
            ->with(['customer', 'user'])
            ->orderBy('order_date', 'desc')
            ->paginate(10);

        return view('livewire.orders.index', [
            'orders' => $orders,
        ])->layout('layouts.app', ['headerTitle' => 'Lịch sử Đơn bán hàng POS']);
    }
}
