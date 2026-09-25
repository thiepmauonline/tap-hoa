<?php

namespace App\Livewire\Inventories;

use Livewire\Component;
use Livewire\WithPagination;
use App\Models\Inventory;
use App\Models\Product;
use App\Models\InventoryMovement;
use App\Services\InventoryService;
use Illuminate\Support\Facades\DB;

class Index extends Component
{
    use WithPagination;

    protected string $paginationTheme = 'bootstrap';

    public string $search = '';
    public bool $onlyLowStock = false;

    public bool $isModalOpen = false;
    public ?int $selectedInventoryId = null;
    public string $productName = '';
    public int $quantity = 0;
    public string $adjustmentReason = '';

    public function editQuantity(int $inventoryId)
    {
        $inv = Inventory::where('store_id', auth()->user()->store_id)->with('product')->findOrFail($inventoryId);
        $this->selectedInventoryId = $inv->id;
        $this->productName = $inv->product->name ?? 'Sản phẩm';
        $this->quantity = $inv->quantity;
        $this->adjustmentReason = '';
        $this->isModalOpen = true;
    }

    public function updateQuantity()
    {
        $this->validate([
            'quantity' => 'required|integer|min:0',
            'adjustmentReason' => 'required|string|min:3|max:255',
        ]);

        $storeId = (int) auth()->user()->store_id;

        DB::transaction(function () use ($storeId) {
            $inventory = Inventory::where('store_id', $storeId)
                ->where('id', $this->selectedInventoryId)
                ->lockForUpdate()
                ->firstOrFail();

            $change = $this->quantity - (int) $inventory->quantity;
            if ($change !== 0) {
                app(InventoryService::class)->adjust(
                    $storeId,
                    (int) $inventory->product_id,
                    $change,
                    'adjustment',
                    auth()->id(),
                    note: $this->adjustmentReason,
                );
            }
        });

        session()->flash('success', 'Đã cập nhật số lượng tồn kho!');
        $this->isModalOpen = false;
    }

    public function render()
    {
        $storeId = auth()->user()->store_id;

        $query = Inventory::where('store_id', $storeId)->with('product.category');

        if ($this->onlyLowStock) {
            $query->whereHas('product', function ($q) {
                $q->whereColumn('inventories.quantity', '<=', 'products.min_stock');
            });
        }

        if (!empty($this->search)) {
            $query->whereHas('product', function ($q) {
                $q->where('name', 'like', '%' . $this->search . '%')
                  ->orWhere('barcode', 'like', '%' . $this->search . '%');
            });
        }

        $inventories = $query->paginate(10);

        return view('livewire.inventories.index', [
            'inventories' => $inventories,
            'movements' => InventoryMovement::where('store_id', $storeId)
                ->with(['product:id,name,unit', 'user:id,name'])
                ->latest()
                ->limit(20)
                ->get(),
        ])->layout('layouts.app', ['headerTitle' => 'Quản lý Tồn kho & Cảnh báo']);
    }
}
