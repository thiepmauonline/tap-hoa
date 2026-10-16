<?php

namespace App\Livewire\Pos;

use App\Models\Customer;
use App\Models\CustomerPointTransaction;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Services\InventoryService;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Locked;
use Livewire\Component;

class Cashier extends Component
{
    private const POINT_VALUE = 1000;

    public string $search = '';

    public string $barcodeInput = '';

    public ?int $selectedCustomerId = null;

    public bool $usePoints = false;

    public $pointsToRedeem = 0;

    public array $cart = [];

    public $discount = 0;

    public $paidAmount = 0;

    public bool $paidAmountManuallyEntered = false;

    public string $paymentMethod = 'cash';

    public string $note = '';

    public bool $showSuccessModal = false;

    public ?Order $lastCompletedOrder = null;

    #[Locked]
    public string $checkoutCode;

    public function mount(): void
    {
        $this->cart = [];
        $this->checkoutCode = 'HD-'.now()->format('Ymd').'-'.Str::upper(Str::random(12));
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

    public function updateQuantity(int $productId, mixed $qty): void
    {
        $this->resetValidation();
        Validator::make(['cart' => $qty], ['cart' => 'required|integer|min:0|max:2147483647'], [
            'cart.required' => 'Vui lòng nhập số lượng sản phẩm.',
            'cart.integer' => 'Số lượng sản phẩm phải là số nguyên.',
            'cart.min' => 'Số lượng sản phẩm không được âm.',
        ])->validate();
        $qty = (int) $qty;
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
        $this->checkoutCode = 'HD-'.now()->format('Ymd').'-'.Str::upper(Str::random(12));
        $this->resetValidation();
    }

    public function calculateTotals(): void
    {
        $subtotal = array_sum(array_column($this->cart, 'subtotal'));
        $pointsDiscount = $this->currentPointsDiscount($subtotal);
        if (! $this->paidAmountManuallyEntered) {
            $this->paidAmount = max(0, $subtotal - $this->previewDiscount() - $pointsDiscount);
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
        if (! $this->usePoints || ! $this->selectedCustomerId || ! is_numeric($this->pointsToRedeem) || $this->pointsToRedeem < 1) {
            return 0;
        }

        $customerPoints = (int) Customer::where('store_id', auth()->user()->store_id)
            ->whereKey($this->selectedCustomerId)
            ->value('point');
        $maxPointsByOrder = (int) floor(max(0, $subtotal - $this->previewDiscount()) / self::POINT_VALUE);

        return min((int) $this->pointsToRedeem, $customerPoints, $maxPointsByOrder) * self::POINT_VALUE;
    }

    private function previewDiscount(): float
    {
        return is_numeric($this->discount) ? max(0, round((float) $this->discount, 2)) : 0;
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
            'discount' => 'required|numeric|min:0|max:9999999999999.99',
            'paidAmount' => 'required|numeric|min:0|max:9999999999999.99',
            'paymentMethod' => 'required|in:cash,transfer',
            'note' => 'nullable|string|max:1000',
        ]);
        $this->discount = round((float) $this->discount, 2);
        $this->paidAmount = round((float) $this->paidAmount, 2);

        $storeId = (int) auth()->user()->store_id;
        try {
            $completedOrder = DB::transaction(function () use ($storeId) {
                $existing = Order::where('store_id', $storeId)->where('user_id', auth()->id())
                    ->where('order_code', $this->checkoutCode)->first();
                if ($existing) {
                    return $existing->load(['items.product', 'customer', 'user']);
                }
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
                    if (! $product || $quantity === false || $quantity < 1 || $quantity > 2147483647) {
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
                if ($subtotal > 9999999999999.99) {
                    throw ValidationException::withMessages(['cart' => 'Giá trị hóa đơn vượt quá giới hạn cho phép.']);
                }
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
                $total = round($subtotal - $this->discount - $pointsDiscount, 2);
                if ($this->paidAmount < $total) {
                    throw ValidationException::withMessages(['paidAmount' => 'Số tiền khách thanh toán chưa đủ.']);
                }
                $pointsEarned = $customer ? (int) floor($total / 10000) : 0;

                $order = Order::create([
                    'store_id' => $storeId,
                    'order_code' => $this->checkoutCode,
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
                    'change_amount' => round($this->paidAmount - $total, 2),
                    'payment_method' => $this->paymentMethod,
                    'status' => 'completed',
                    'note' => $this->note,
                ]);

                usort($items, fn ($a, $b) => $a['product']->id <=> $b['product']->id);
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
            }, 3);
        } catch (UniqueConstraintViolationException $exception) {
            $completedOrder = Order::where('store_id', $storeId)->where('user_id', auth()->id())
                ->where('order_code', $this->checkoutCode)->with(['items.product', 'customer', 'user'])->first();
            if (! $completedOrder) {
                throw $exception;
            }
        }

        if ($completedOrder->status !== 'completed') {
            throw ValidationException::withMessages(['cart' => 'Hóa đơn của yêu cầu này đã bị hủy. Vui lòng tải lại quầy để tạo hóa đơn mới.']);
        }
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
            (int) floor(max(0, $subtotal - $this->previewDiscount()) / self::POINT_VALUE),
        );
        $totalAmount = max(0, $subtotal - $this->previewDiscount() - $pointsDiscountAmount);

        return view('livewire.pos.cashier', [
            'products' => $productsQuery->take(20)->get(),
            'customers' => Customer::where('store_id', $storeId)->orderBy('name')->get(),
            'selectedCustomer' => $selectedCustomer,
            'pointsDiscountAmount' => $pointsDiscountAmount,
            'maxRedeemablePoints' => $maxRedeemablePoints,
            'pointValue' => self::POINT_VALUE,
            'subtotal' => $subtotal,
            'totalAmount' => $totalAmount,
            'changeAmount' => max(0, (is_numeric($this->paidAmount) ? (float) $this->paidAmount : 0) - $totalAmount),
        ])->layout('layouts.pos');
    }
}
