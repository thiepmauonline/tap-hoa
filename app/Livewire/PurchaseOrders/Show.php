<?php

namespace App\Livewire\PurchaseOrders;

use App\Models\PurchaseOrder;
use Livewire\Component;

class Show extends Component
{
    public PurchaseOrder $purchaseOrder;

    public function mount(PurchaseOrder $purchaseOrder): void
    {
        abort_unless(
            (int) $purchaseOrder->store_id === (int) auth()->user()->store_id,
            404
        );

        $this->purchaseOrder = $purchaseOrder->load([
            'supplier',
            'user',
            'items.product',
        ]);
    }

    public function render()
    {
        return view('livewire.purchase-orders.show')
            ->layout('layouts.app', ['headerTitle' => 'Chi tiết Phiếu Nhập Hàng']);
    }
}
