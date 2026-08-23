<?php

namespace App\Livewire\PurchaseOrders;

use Livewire\Component;
use App\Models\Supplier;
use App\Models\Product;
use App\Models\PurchaseOrder;
use App\Models\PurchaseOrderItem;
use App\Models\Inventory;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class Create extends Component
{
    public ?int $supplier_id = null;
    public string $note = '';
    public array $items = []; // [ ['product_id', 'name', 'unit', 'qty', 'cost_price', 'subtotal'] ]
    public ?int $selectedProductToAdd = null;

    public function addProduct()
    {
        if (!$this->selectedProductToAdd) return;

        $storeId = auth()->user()->store_id;
        $product = Product::where('store_id', $storeId)->findOrFail($this->selectedProductToAdd);

        // Check if already in items list
        foreach ($this->items as $idx => $item) {
            if ($item['product_id'] == $product->id) {
                $this->items[$idx]['qty']++;
                $this->items[$idx]['subtotal'] = $this->items[$idx]['qty'] * $this->items[$idx]['cost_price'];
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

    public function updateItem(int $index, int $qty, float $costPrice)
    {
        if (!isset($this->items[$index])) return;

        $this->items[$index]['qty'] = max(1, $qty);
        $this->items[$index]['cost_price'] = max(0, $costPrice);
        $this->items[$index]['subtotal'] = $this->items[$index]['qty'] * $this->items[$index]['cost_price'];
    }

    public function removeItem(int $index)
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

        $storeId = auth()->user()->store_id;
        $totalAmount = array_sum(array_column($this->items, 'subtotal'));

        DB::transaction(function () use ($storeId, $totalAmount) {
            $poCode = 'PN-' . date('Ymd') . '-' . rand(1000, 9999);

            $po = PurchaseOrder::create([
                'store_id' => $storeId,
                'po_code' => $poCode,
                'supplier_id' => $this->supplier_id ?: null,
                'user_id' => auth()->id(),
                'purchase_date' => Carbon::now(),
                'total_amount' => $totalAmount,
                'paid_amount' => $totalAmount,
                'payment_status' => 'paid',
                'status' => 'completed',
                'note' => $this->note,
            ]);

            foreach ($this->items as $item) {
                PurchaseOrderItem::create([
                    'purchase_order_id' => $po->id,
                    'product_id' => $item['product_id'],
                    'quantity' => $item['qty'],
                    'price' => $item['cost_price'],
                    'subtotal' => $item['subtotal'],
                ]);

                // Cộng dồn tồn kho
                $inv = Inventory::firstOrCreate(
                    ['store_id' => $storeId, 'product_id' => $item['product_id']],
                    ['quantity' => 0]
                );

                $inv->increment('quantity', $item['qty']);
                $inv->update(['last_imported_at' => now()]);

                // Cập nhật lại giá vốn của sản phẩm
                Product::where('id', $item['product_id'])->update(['cost_price' => $item['cost_price']]);
            }
        });

        session()->flash('success', 'Đã nhập kho thành công!');
        return redirect()->route('purchase-orders.index');
    }

    public function render()
    {
        $storeId = auth()->user()->store_id;
        $suppliers = Supplier::where('store_id', $storeId)->get();
        $products = Product::where('store_id', $storeId)->where('status', 1)->get();

        $totalAmount = array_sum(array_column($this->items, 'subtotal'));

        return view('livewire.purchase-orders.create', [
            'suppliers' => $suppliers,
            'products' => $products,
            'totalAmount' => $totalAmount,
        ])->layout('layouts.app', ['headerTitle' => 'Tạo Phiếu Nhập Hàng Mới']);
    }
}
