<?php

namespace App\Livewire\Pos;

use App\Models\Customer;
use App\Models\CustomerPointTransaction;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Services\InventoryService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Livewire\Component;

class Cashier extends Component
{
    private const POINT_VALUE = 1000;

    public string $search = '';
    public string $barcodeInput = '';
    public ?int $selectedCustomerId = null;
    public bool $usePoints = false;
    public int $pointsToRedeem = 0;
    public array $cart = [];
    public float $discount = 0;
    public float $paidAmount = 0;
    public bool $paidAmountManuallyEntered = false;
    public string $paymentMethod = 'cash';
    public string $note = '';
    public bool $showSuccessModal = false;
    public ?Order $lastCompletedOrder = null;

    public function mount(): void
    {
        $this->cart = [];
    }

    public function scanBarcode(): void
    {
        if (! trim($this->barcodeInput)) {
            return;
        }

        $product = Product::where('store_id', auth()->user()->store_id)
            ->where('status', 1)
            ->where('barcode', trim($this->barcodeInput))
            ->first();

        if (! $product) {
            session()->flash('error', 'Không tìm thấy sản phẩm có mã vạch: '.$this->barcodeInput);
            return;
        }

        $this->addToCart($product->id);
        $this->barcodeInput = '';
    }

    public function addToCart(int $productId): void
    {
        $this->resetValidation();
        $product = Product::where('store_id', auth()->user()->store_id)
            ->where('status', 1)
            ->with('inventory')
            ->findOrFail($productId);
        $stock = (int) ($product->inventory->quantity ?? 0);
        $nextQuantity = isset($this->cart[$productId]) ? (int) $this->cart[$productId]['qty'] + 1 : 1;

        if ($nextQuantity > $stock) {
            session()->flash('error', 'Sản phẩm "'.$product->name.'" không đủ tồn kho.');
            return;
        }

        $this->cart[$productId] = [
            'id' => $product->id,
            'name' => $product->name,
            'unit' => $product->unit,
            'price' => (float) $product->sale_price,
            'cost_price' => (float) $product->cost_price,
            'qty' => $nextQuantity,
            'subtotal' => $nextQuantity * (float) $product->sale_price,
        ];
        $this->calculateTotals();
    }

    public function updateQuantity(int $productId, int $qty): void
    {
        $this->resetValidation();
        if (! isset($this->cart[$productId])) {
            return;
        }
        if ($qty <= 0) {
            $this->removeFromCart($productId);
            return;
        }

        $product = Product::where('store_id', auth()->user()->store_id)->with('inventory')->find($productId);
        if (! $product) {
            $this->removeFromCart($productId);
            return;
        }

        $stock = (int) ($product->inventory->quantity ?? 0);
        if ($qty > $stock) {
            session()->flash('error', 'Số lượng vượt quá tồn kho hiện tại ('.$stock.').');
            return;
        }

        $this->cart[$productId]['qty'] = $qty;
        $this->cart[$productId]['subtotal'] = $qty * $this->cart[$productId]['price'];
        $this->calculateTotals();
    }

    public function removeFromCart(int $productId): void
    {
        unset($this->cart[$productId]);
        $this->calculateTotals();
    }

    public function clearCart(): void
    {
        $this->cart = [];
        $this->discount = 0;
        $this->paidAmount = 0;
        $this->paidAmountManuallyEntered = false;
        $this->selectedCustomerId = null;
        $this->usePoints = false;
        $this->pointsToRedeem = 0;
        $this->note = '';
        $this->resetValidation();
    }

    public function calculateTotals(): void
    {
        $subtotal = array_sum(array_column($this->cart, 'subtotal'));
        $pointsDiscount = $this->currentPointsDiscount($subtotal);
        if (! $this->paidAmountManuallyEntered) {
            $this->paidAmount = max(0, $subtotal - $this->discount - $pointsDiscount);
        }
    }

    public function updatedSelectedCustomerId(): void
    {
        $this->usePoints = false;
        $this->pointsToRedeem = 0;
        $this->resetValidation();
        $this->calculateTotals();
    }

    public function updatedUsePoints(): void
    {
        if (! $this->usePoints) {
            $this->pointsToRedeem = 0;
        }
        $this->resetValidation(['usePoints', 'pointsToRedeem']);
        $this->calculateTotals();
    }

    public function updatedPointsToRedeem(): void
    {
        $this->resetValidation('pointsToRedeem');
        $this->calculateTotals();
    }

    private function currentPointsDiscount(float $subtotal): float
    {
        if (! $this->usePoints || ! $this->selectedCustomerId || $this->pointsToRedeem < 1) {
            return 0;
        }

        $customerPoints = (int) Customer::where('store_id', auth()->user()->store_id)
            ->whereKey($this->selectedCustomerId)
            ->value('point');
        $maxPointsByOrder = (int) floor(max(0, $subtotal - $this->discount) / self::POINT_VALUE);

        return min($this->pointsToRedeem, $customerPoints, $maxPointsByOrder) * self::POINT_VALUE;
    }

    public function updatedPaidAmount(): void
    {
        $this->paidAmountManuallyEntered = true;
        $this->resetValidation('paidAmount');
    }

    public function updatedDiscount(): void
    {
        $this->resetValidation('discount');
        $this->calculateTotals();
    }

    public function checkout(): void
    {
        if (empty($this->cart)) {
            session()->flash('error', 'Giỏ hàng đang trống!');
            return;
        }

        $this->validate([
            'selectedCustomerId' => 'nullable|integer',
            'usePoints' => 'boolean',
            'pointsToRedeem' => 'required|integer|min:0',
            'discount' => 'required|numeric|min:0',
            'paidAmount' => 'required|numeric|min:0',
            'paymentMethod' => 'required|in:cash,transfer,qr',
            'note' => 'nullable|string|max:1000',
        ]);

        $storeId = (int) auth()->user()->store_id;
        $completedOrder = DB::transaction(function () use ($storeId) {
            $productIds = collect($this->cart)->pluck('id')->map(fn ($id) => (int) $id)->unique();
            $products = Product::where('store_id', $storeId)
                ->where('status', 1)
                ->whereIn('id', $productIds)
                ->get()
                ->keyBy('id');
            $items = [];

            foreach ($this->cart as $cartItem) {
                $product = $products->get((int) ($cartItem['id'] ?? 0));
                $quantity = filter_var($cartItem['qty'] ?? null, FILTER_VALIDATE_INT);
                if (! $product || $quantity === false || $quantity < 1) {
                    throw ValidationException::withMessages(['cart' => 'Giỏ hàng có sản phẩm không hợp lệ.']);
                }
                $items[] = [
                    'product' => $product,
                    'quantity' => $quantity,
                    'price' => (float) $product->sale_price,
                    'cost_price' => (float) $product->cost_price,
                    'subtotal' => round($quantity * (float) $product->sale_price, 2),
                ];
            }

            $subtotal = array_sum(array_column($items, 'subtotal'));
            if ($this->discount > $subtotal) {
                throw ValidationException::withMessages(['discount' => 'Giảm giá không được vượt quá tổng tiền hàng.']);
            }

            $customer = null;
            if ($this->selectedCustomerId) {
                $customer = Customer::where('store_id', $storeId)
                    ->whereKey($this->selectedCustomerId)
                    ->lockForUpdate()
                    ->first();
                if (! $customer) {
                    throw ValidationException::withMessages(['selectedCustomerId' => 'Khách hàng không hợp lệ.']);
                }
            }

            if ($this->usePoints && ! $customer) {
                throw ValidationException::withMessages(['usePoints' => 'Vui lòng chọn khách hàng trước khi dùng điểm.']);
            }

            $pointsRedeemed = $this->usePoints ? $this->pointsToRedeem : 0;
            $maxPointsByOrder = (int) floor(max(0, $subtotal - $this->discount) / self::POINT_VALUE);
            if ($pointsRedeemed > (int) ($customer?->point ?? 0)) {
                throw ValidationException::withMessages(['pointsToRedeem' => 'Khách hàng không đủ điểm để sử dụng.']);
            }
            if ($pointsRedeemed > $maxPointsByOrder) {
                throw ValidationException::withMessages(['pointsToRedeem' => 'Số điểm dùng vượt quá giá trị còn lại của đơn hàng.']);
            }

            $pointsDiscount = $pointsRedeemed * self::POINT_VALUE;
            $total = $subtotal - $this->discount - $pointsDiscount;
            if ($this->paidAmount < $total) {
                throw ValidationException::withMessages(['paidAmount' => 'Số tiền khách thanh toán chưa đủ.']);
            }
            $pointsEarned = $customer ? (int) floor($total / 10000) : 0;

            $order = Order::create([
                'store_id' => $storeId,
                'order_code' => 'HD-'.now()->format('Ymd').'-'.Str::upper(Str::random(6)),
                'customer_id' => $customer?->id,
                'user_id' => auth()->id(),
                'order_date' => now(),
                'subtotal_amount' => $subtotal,
                'discount_amount' => $this->discount + $pointsDiscount,
                'manual_discount_amount' => $this->discount,
                'points_redeemed' => $pointsRedeemed,
                'points_discount_amount' => $pointsDiscount,
                'points_earned' => $pointsEarned,
                'total_amount' => $total,
                'paid_amount' => $this->paidAmount,
                'change_amount' => $this->paidAmount - $total,
                'payment_method' => $this->paymentMethod,
                'status' => 'completed',
                'note' => $this->note,
            ]);

            foreach ($items as $item) {
                OrderItem::create([
                    'order_id' => $order->id,
                    'product_id' => $item['product']->id,
                    'quantity' => $item['quantity'],
                    'cost_price' => $item['cost_price'],
                    'price' => $item['price'],
                    'subtotal' => $item['subtotal'],
                ]);

                app(InventoryService::class)->adjust(
                    $storeId,
                    (int) $item['product']->id,
                    -$item['quantity'],
                    'sale',
                    auth()->id(),
                    'order',
                    $order->id,
                    'Xuất bán theo hóa đơn '.$order->order_code,
                );
            }

            if ($customer) {
                if ($pointsRedeemed > 0) {
                    $before = (int) $customer->point;
                    $customer->decrement('point', $pointsRedeemed);
                    CustomerPointTransaction::create([
                        'store_id' => $storeId,
                        'customer_id' => $customer->id,
                        'order_id' => $order->id,
                        'user_id' => auth()->id(),
                        'type' => 'redeem',
                        'points_change' => -$pointsRedeemed,
                        'points_before' => $before,
                        'points_after' => $before - $pointsRedeemed,
                        'note' => 'Dùng điểm tại hóa đơn '.$order->order_code,
                    ]);
                    $customer->refresh();
                }

                if ($pointsEarned > 0) {
                    $before = (int) $customer->point;
                    $customer->increment('point', $pointsEarned);
                    CustomerPointTransaction::create([
                        'store_id' => $storeId,
                        'customer_id' => $customer->id,
                        'order_id' => $order->id,
                        'user_id' => auth()->id(),
                        'type' => 'earn',
                        'points_change' => $pointsEarned,
                        'points_before' => $before,
                        'points_after' => $before + $pointsEarned,
                        'note' => 'Tích điểm từ hóa đơn '.$order->order_code,
                    ]);
                }
            }

            return $order->load(['items.product', 'customer', 'user']);
        });

        $this->lastCompletedOrder = $completedOrder;
        $this->showSuccessModal = true;
        $this->clearCart();
    }

    public function closeSuccessModal(): void
    {
        $this->showSuccessModal = false;
        $this->lastCompletedOrder = null;
    }

    public function render()
    {
        $storeId = auth()->user()->store_id;
        $productsQuery = Product::where('store_id', $storeId)->where('status', 1)->with(['inventory', 'category']);
        if ($this->search !== '') {
            $productsQuery->where(fn ($query) => $query
                ->where('name', 'like', '%'.$this->search.'%')
                ->orWhere('barcode', 'like', '%'.$this->search.'%'));
        }

        $subtotal = array_sum(array_column($this->cart, 'subtotal'));
        $selectedCustomer = $this->selectedCustomerId
            ? Customer::where('store_id', $storeId)->find($this->selectedCustomerId)
            : null;
        $pointsDiscountAmount = $this->currentPointsDiscount($subtotal);
        $maxRedeemablePoints = min(
            (int) ($selectedCustomer?->point ?? 0),
            (int) floor(max(0, $subtotal - $this->discount) / self::POINT_VALUE),
        );
        $totalAmount = max(0, $subtotal - $this->discount - $pointsDiscountAmount);

        return view('livewire.pos.cashier', [
            'products' => $productsQuery->take(20)->get(),
            'customers' => Customer::where('store_id', $storeId)->orderBy('name')->get(),
            'selectedCustomer' => $selectedCustomer,
            'pointsDiscountAmount' => $pointsDiscountAmount,
            'maxRedeemablePoints' => $maxRedeemablePoints,
            'pointValue' => self::POINT_VALUE,
            'subtotal' => $subtotal,
            'totalAmount' => $totalAmount,
            'changeAmount' => max(0, $this->paidAmount - $totalAmount),
        ])->layout('layouts.pos');
    }
}
