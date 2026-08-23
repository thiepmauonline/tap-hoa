<?php

namespace App\Livewire\Pos;

use Livewire\Component;
use App\Models\Product;
use App\Models\Customer;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Inventory;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class Cashier extends Component
{
    public string $search = '';
    public string $barcodeInput = '';
    public ?int $selectedCustomerId = null;

    // Giỏ hàng (Cart)
    public array $cart = []; // [product_id => ['id', 'name', 'unit', 'price', 'cost_price', 'qty', 'subtotal']]
    public float $discount = 0;
    public float $paidAmount = 0;
    public string $paymentMethod = 'cash';
    public string $note = '';

    // Modal thành công / in hóa đơn
    public bool $showSuccessModal = false;
    public ?Order $lastCompletedOrder = null;

    public function mount()
    {
        $this->cart = [];
    }

    public function scanBarcode()
    {
        if (empty($this->barcodeInput)) return;

        $storeId = auth()->user()->store_id;
        $product = Product::where('store_id', $storeId)
            ->where('barcode', trim($this->barcodeInput))
            ->first();

        if ($product) {
            $this->addToCart($product->id);
            $this->barcodeInput = '';
        } else {
            session()->flash('error', 'Không tìm thấy sản phẩm có mã vạch: ' . $this->barcodeInput);
        }
    }

    public function addToCart(int $productId)
    {
        $storeId = auth()->user()->store_id;
        $product = Product::where('store_id', $storeId)->with('inventory')->findOrFail($productId);

        $stock = $product->inventory->quantity ?? 0;

        if (isset($this->cart[$productId])) {
            if ($this->cart[$productId]['qty'] + 1 > $stock) {
                session()->flash('error', 'Sản phẩm "' . $product->name . '" không đủ tồn kho!');
                return;
            }
            $this->cart[$productId]['qty']++;
            $this->cart[$productId]['subtotal'] = $this->cart[$productId]['qty'] * $this->cart[$productId]['price'];
        } else {
            if ($stock < 1) {
                session()->flash('error', 'Sản phẩm "' . $product->name . '" đã hết hàng trong kho!');
                return;
            }

            $this->cart[$productId] = [
                'id' => $product->id,
                'name' => $product->name,
                'unit' => $product->unit,
                'price' => (float) $product->sale_price,
                'cost_price' => (float) $product->cost_price,
                'qty' => 1,
                'subtotal' => (float) $product->sale_price,
            ];
        }

        $this->calculateTotals();
    }

    public function updateQuantity(int $productId, int $qty)
    {
        if (!isset($this->cart[$productId])) return;

        if ($qty <= 0) {
            $this->removeFromCart($productId);
            return;
        }

        $storeId = auth()->user()->store_id;
        $product = Product::where('store_id', $storeId)->with('inventory')->find($productId);
        $stock = $product->inventory->quantity ?? 0;

        if ($qty > $stock) {
            session()->flash('error', 'Số lượng vượt quá tồn kho hiện tại (' . $stock . ')');
            return;
        }

        $this->cart[$productId]['qty'] = $qty;
        $this->cart[$productId]['subtotal'] = $qty * $this->cart[$productId]['price'];
        $this->calculateTotals();
    }

    public function removeFromCart(int $productId)
    {
        unset($this->cart[$productId]);
        $this->calculateTotals();
    }

    public function clearCart()
    {
        $this->cart = [];
        $this->discount = 0;
        $this->paidAmount = 0;
        $this->selectedCustomerId = null;
        $this->note = '';
    }

    public function calculateTotals()
    {
        $subtotal = array_sum(array_column($this->cart, 'subtotal'));
        if ($this->paidAmount == 0) {
            $this->paidAmount = max(0, $subtotal - $this->discount);
        }
    }

    public function checkout()
    {
        if (empty($this->cart)) {
            session()->flash('error', 'Giỏ hàng đang trống!');
            return;
        }

        $subtotal = array_sum(array_column($this->cart, 'subtotal'));
        $total = max(0, $subtotal - $this->discount);

        if ($this->paidAmount < $total) {
            session()->flash('error', 'Số tiền khách đưa chưa đủ!');
            return;
        }

        $storeId = auth()->user()->store_id;

        DB::transaction(function () use ($storeId, $subtotal, $total) {
            // 1. Tạo đơn hàng
            $orderCode = 'HD-' . date('Ymd') . '-' . rand(1000, 9999);

            $order = Order::create([
                'store_id' => $storeId,
                'order_code' => $orderCode,
                'customer_id' => $this->selectedCustomerId ?: null,
                'user_id' => auth()->id(),
                'order_date' => Carbon::now(),
                'subtotal_amount' => $subtotal,
                'discount_amount' => $this->discount,
                'total_amount' => $total,
                'paid_amount' => $this->paidAmount,
                'change_amount' => $this->paidAmount - $total,
                'payment_method' => $this->paymentMethod,
                'status' => 'completed',
                'note' => $this->note,
            ]);

            // 2. Tạo chi tiết đơn & trừ kho
            foreach ($this->cart as $item) {
                OrderItem::create([
                    'order_id' => $order->id,
                    'product_id' => $item['id'],
                    'quantity' => $item['qty'],
                    'cost_price' => $item['cost_price'],
                    'price' => $item['price'],
                    'subtotal' => $item['subtotal'],
                ]);

                // Trừ tồn kho
                Inventory::where('store_id', $storeId)
                    ->where('product_id', $item['id'])
                    ->decrement('quantity', $item['qty']);
            }

            // 3. Tích điểm cho khách hàng (nếu có)
            if ($this->selectedCustomerId) {
                $points = (int) floor($total / 10000); // 10,000 đ = 1 điểm
                Customer::where('id', $this->selectedCustomerId)->increment('point', $points);
            }

            $this->lastCompletedOrder = $order->load(['items.product', 'customer']);
        });

        $this->showSuccessModal = true;
        $this->clearCart();
    }

    public function closeSuccessModal()
    {
        $this->showSuccessModal = false;
        $this->lastCompletedOrder = null;
    }

    public function render()
    {
        $storeId = auth()->user()->store_id;

        $productsQuery = Product::where('store_id', $storeId)
            ->where('status', 1)
            ->with(['inventory', 'category']);

        if (!empty($this->search)) {
            $productsQuery->where(function ($q) {
                $q->where('name', 'like', '%' . $this->search . '%')
                  ->orWhere('barcode', 'like', '%' . $this->search . '%');
            });
        }

        $products = $productsQuery->take(20)->get();
        $customers = Customer::where('store_id', $storeId)->get();

        $subtotal = array_sum(array_column($this->cart, 'subtotal'));
        $totalAmount = max(0, $subtotal - $this->discount);
        $changeAmount = max(0, $this->paidAmount - $totalAmount);

        return view('livewire.pos.cashier', [
            'products' => $products,
            'customers' => $customers,
            'subtotal' => $subtotal,
            'totalAmount' => $totalAmount,
            'changeAmount' => $changeAmount,
        ])->layout('layouts.pos');
    }
}
