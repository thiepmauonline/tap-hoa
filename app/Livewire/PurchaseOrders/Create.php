<?php

namespace App\Livewire\PurchaseOrders;

use App\Models\Product;
use App\Models\PurchaseOrder;
use App\Models\PurchaseOrderItem;
use App\Models\Supplier;
use App\Services\InventoryService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Livewire\Component;

class Create extends Component
{
    public ?int $supplier_id = null;
    public string $note = '';
    public array $items = [];
    public ?int $selectedProductToAdd = null;

    public function addProduct(): void
    {
        if (! $this->selectedProductToAdd) {
            return;
        }

        $product = Product::where('store_id', auth()->user()->store_id)
            ->where('status', 1)
            ->findOrFail($this->selectedProductToAdd);

        foreach ($this->items as $index => $item) {
            if ((int) $item['product_id'] === (int) $product->id) {
                $this->items[$index]['qty']++;
                $this->items[$index]['subtotal'] = $this->items[$index]['qty'] * $this->items[$index]['cost_price'];
                $this->selectedProductToAdd = null;
                return;
            }
        }

        $this->items[] = [
            'product_id' => $product->id,
            'name' => $product->name,
            'unit' => $product->unit,
            'qty' => 10,
            'cost_price' => (float) $product->cost_price,
            'subtotal' => 10 * (float) $product->cost_price,
        ];
        $this->selectedProductToAdd = null;
    }

    public function updateItem(int $index, int $qty, float $costPrice): void
    {
        if (! isset($this->items[$index])) {
            return;
        }

        $this->items[$index]['qty'] = max(1, $qty);
        $this->items[$index]['cost_price'] = max(0, $costPrice);
        $this->items[$index]['subtotal'] = $this->items[$index]['qty'] * $this->items[$index]['cost_price'];
    }

    public function removeItem(int $index): void
    {
        unset($this->items[$index]);
        $this->items = array_values($this->items);
    }

    public function save()
    {
        if (empty($this->items)) {
            session()->flash('error', 'Chưa có sản phẩm nào trong phiếu nhập!');
            return;
        }

        $this->validate([
            'supplier_id' => 'nullable|integer',
            'note' => 'nullable|string|max:1000',
        ]);

        $storeId = (int) auth()->user()->store_id;
        if ($this->supplier_id && ! Supplier::where('store_id', $storeId)->whereKey($this->supplier_id)->exists()) {
            throw ValidationException::withMessages(['supplier_id' => 'Nhà cung cấp không hợp lệ.']);
        }

        DB::transaction(function () use ($storeId) {
            $productIds = collect($this->items)->pluck('product_id')->map(fn ($id) => (int) $id)->unique();
            $products = Product::where('store_id', $storeId)->whereIn('id', $productIds)->get()->keyBy('id');
            $preparedItems = [];

            foreach ($this->items as $item) {
                $product = $products->get((int) ($item['product_id'] ?? 0));
                $quantity = filter_var($item['qty'] ?? null, FILTER_VALIDATE_INT);
                $costPrice = filter_var($item['cost_price'] ?? null, FILTER_VALIDATE_FLOAT);

                if (! $product || $quantity === false || $quantity < 1 || $costPrice === false || $costPrice < 0) {
                    throw ValidationException::withMessages(['items' => 'Danh sách sản phẩm nhập có dữ liệu không hợp lệ.']);
                }

                $preparedItems[] = [
                    'product' => $product,
                    'quantity' => $quantity,
                    'cost_price' => round($costPrice, 2),
                    'subtotal' => round($quantity * $costPrice, 2),
                ];
            }

            $totalAmount = array_sum(array_column($preparedItems, 'subtotal'));
            $purchaseOrder = PurchaseOrder::create([
                'store_id' => $storeId,
                'po_code' => 'PN-'.now()->format('Ymd').'-'.Str::upper(Str::random(6)),
                'supplier_id' => $this->supplier_id ?: null,
                'user_id' => auth()->id(),
                'purchase_date' => now(),
                'total_amount' => $totalAmount,
                'paid_amount' => $totalAmount,
                'payment_status' => 'paid',
                'status' => 'completed',
                'note' => $this->note,
            ]);

            foreach ($preparedItems as $item) {
                PurchaseOrderItem::create([
                    'purchase_order_id' => $purchaseOrder->id,
                    'product_id' => $item['product']->id,
                    'quantity' => $item['quantity'],
                    'price' => $item['cost_price'],
                    'subtotal' => $item['subtotal'],
                ]);

                app(InventoryService::class)->adjust(
                    $storeId,
                    (int) $item['product']->id,
                    $item['quantity'],
                    'purchase',
                    auth()->id(),
                    'purchase_order',
                    $purchaseOrder->id,
                    'Nhập hàng theo phiếu '.$purchaseOrder->po_code,
                );

                Product::where('store_id', $storeId)
                    ->whereKey($item['product']->id)
                    ->update(['cost_price' => $item['cost_price']]);
            }
        });

        session()->flash('success', 'Đã nhập kho thành công!');
        return redirect()->route('purchase-orders.index');
    }

    public function render()
    {
        $storeId = auth()->user()->store_id;

        return view('livewire.purchase-orders.create', [
            'suppliers' => Supplier::where('store_id', $storeId)->orderBy('name')->get(),
            'products' => Product::where('store_id', $storeId)->where('status', 1)->orderBy('name')->get(),
            'totalAmount' => array_sum(array_column($this->items, 'subtotal')),
        ])->layout('layouts.app', ['headerTitle' => 'Tạo Phiếu Nhập Hàng Mới']);
    }
}
