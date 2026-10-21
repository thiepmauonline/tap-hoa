<?php

namespace App\Livewire\Orders;

use App\Models\Order;
use App\Services\OrderCancellationService;
use Livewire\Component;
use Livewire\WithPagination;

class Index extends Component
{
    use WithPagination;

    protected string $paginationTheme = 'bootstrap';

    public string $search = '';

    public string $paymentMethod = '';

    public string $status = '';

    public string $cancellationReason = '';

    public ?int $viewOrderId = null;

    public ?Order $selectedOrder = null;

    public function updatingSearch(): void
    {
        $this->resetPage();
    }

    public function updatingPaymentMethod(): void
    {
        $this->resetPage();
    }

    public function updatingStatus(): void
    {
        $this->resetPage();
    }

    public function showDetail(int $id)
    {
        $this->reset('cancellationReason');
        $this->resetValidation();
        $this->selectedOrder = Order::where('store_id', auth()->user()->store_id)
            ->with(['items.product', 'customer', 'user', 'cancelledBy'])
            ->findOrFail($id);
        $this->viewOrderId = $id;
    }

    public function closeDetail()
    {
        $this->viewOrderId = null;
        $this->selectedOrder = null;
        $this->reset('cancellationReason');
        $this->resetValidation();
    }

    public function cancelOrder(int $id, OrderCancellationService $service): void
    {
        $this->selectedOrder = $service->cancel($id, auth()->user(), $this->cancellationReason);
        $this->viewOrderId = $this->selectedOrder->id;
        $this->reset('cancellationReason');
        $this->resetValidation();
        session()->flash('success', 'Đã hủy hóa đơn, hoàn kho, hoàn điểm đã dùng và thu hồi điểm đã tích.');
    }

    public function render()
    {
        $storeId = auth()->user()->store_id;

        $query = Order::where('store_id', $storeId)
            ->with(['customer', 'user']);

        if (! empty($this->search)) {
            $query->where(function ($q) {
                $q->where('order_code', 'like', '%'.$this->search.'%')
                    ->orWhereHas('customer', function ($cq) {
                        $cq->where('name', 'like', '%'.$this->search.'%')
                            ->orWhere('phone', 'like', '%'.$this->search.'%');
                    });
            });
        }

        if (! empty($this->paymentMethod)) {
            $query->where('payment_method', $this->paymentMethod);
        }

        if (in_array($this->status, ['completed', 'cancelled'], true)) {
            $query->where('status', $this->status);
        }

        $orders = $query->orderBy('order_date', 'desc')->paginate(10);

        return view('livewire.orders.index', [
            'orders' => $orders,
        ])->layout('layouts.app', ['headerTitle' => 'Hóa đơn']);
    }
}
