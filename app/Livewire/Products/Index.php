<?php

namespace App\Livewire\Products;

use Livewire\Component;
use Livewire\WithPagination;
use App\Models\Product;
use App\Models\Category;
use App\Models\Supplier;
use App\Models\Inventory;
use App\Models\OrderItem;
use App\Models\PurchaseOrderItem;
use App\Models\InventoryMovement;
use App\Services\InventoryService;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class Index extends Component
{
    use WithPagination;

    protected string $paginationTheme = 'bootstrap';

    public string $search = '';
    public string $selectedCategory = '';

    // Form fields
    public bool $isModalOpen = false;
    public ?int $productId = null;
    public string $name = '';
    public ?int $category_id = null;
    public ?int $supplier_id = null;
    public string $barcode = '';
    public string $unit = 'gói';
    public $cost_price = 0;
    public $sale_price = 0;
    public int $min_stock = 5;
    public int $initial_stock = 0;

    protected function rules(): array
    {
        return [
            'name' => 'required|string|max:255',
            'category_id' => ['nullable', 'integer', Rule::exists('categories', 'id')->where('store_id', auth()->user()->store_id)],
            'supplier_id' => ['nullable', 'integer', Rule::exists('suppliers', 'id')->where('store_id', auth()->user()->store_id)],
            'barcode' => ['nullable', 'string', 'max:100', Rule::unique('products', 'barcode')->where('store_id', auth()->user()->store_id)->ignore($this->productId)],
            'unit' => 'required|string|max:50',
            'cost_price' => 'required|numeric|min:0',
            'sale_price' => 'required|numeric|min:0',
            'min_stock' => 'required|integer|min:0',
            'initial_stock' => 'nullable|integer|min:0',
        ];
    }

    public function openModal()
    {
        $this->resetForm();
        $this->isModalOpen = true;
    }

    public function closeModal()
    {
        $this->isModalOpen = false;
        $this->resetForm();
    }

    public function resetForm()
    {
        $this->productId = null;
        $this->name = '';
        $this->category_id = null;
        $this->supplier_id = null;
        $this->barcode = '';
        $this->unit = 'gói';
        $this->cost_price = 0;
        $this->sale_price = 0;
        $this->min_stock = 5;
        $this->initial_stock = 0;
        $this->resetValidation();
    }

    public function edit(int $id)
    {
        $product = Product::where('store_id', auth()->user()->store_id)->findOrFail($id);

        $this->productId = $product->id;
        $this->name = $product->name;
        $this->category_id = $product->category_id;
        $this->supplier_id = $product->supplier_id;
        $this->barcode = $product->barcode ?? '';
        $this->unit = $product->unit;
        $this->cost_price = $product->cost_price;
        $this->sale_price = $product->sale_price;
        $this->min_stock = $product->min_stock;
        $this->initial_stock = $product->inventory->quantity ?? 0;

        $this->isModalOpen = true;
    }

    public function save()
    {
        $this->validate();
        $storeId = auth()->user()->store_id;

        if ($this->productId) {
            $product = Product::where('store_id', $storeId)->findOrFail($this->productId);
            $product->update([
                'name' => $this->name,
                'category_id' => $this->category_id ?: null,
                'supplier_id' => $this->supplier_id ?: null,
                'barcode' => $this->barcode ?: null,
                'unit' => $this->unit,
                'cost_price' => $this->cost_price,
                'sale_price' => $this->sale_price,
                'min_stock' => $this->min_stock,
            ]);

            // Cập nhật tồn kho
            $inventory = Inventory::firstOrCreate(
                ['store_id' => $storeId, 'product_id' => $product->id],
                ['quantity' => 0]
            );
            $change = $this->initial_stock - (int) $inventory->quantity;
            if ($change !== 0) {
                DB::transaction(fn () => app(InventoryService::class)->adjust(
                    (int) $storeId,
                    (int) $product->id,
                    $change,
                    'adjustment',
                    auth()->id(),
                    note: 'Điều chỉnh tồn kho từ màn hình sản phẩm',
                ));
            }

            session()->flash('success', 'Cập nhật sản phẩm thành công!');
        } else {
            $product = Product::create([
                'store_id' => $storeId,
                'name' => $this->name,
                'category_id' => $this->category_id ?: null,
                'supplier_id' => $this->supplier_id ?: null,
                'barcode' => $this->barcode ?: null,
                'unit' => $this->unit,
                'cost_price' => $this->cost_price,
                'sale_price' => $this->sale_price,
                'min_stock' => $this->min_stock,
                'status' => 1,
            ]);

            // Tạo tồn kho ban đầu
            DB::transaction(fn () => app(InventoryService::class)->adjust(
                (int) $storeId,
                (int) $product->id,
                $this->initial_stock,
                'initial',
                auth()->id(),
                note: 'Tồn kho ban đầu khi tạo sản phẩm',
            ));

            session()->flash('success', 'Thêm sản phẩm mới thành công!');
        }

        $this->closeModal();
    }

    public function delete(int $id)
    {
        $product = Product::where('store_id', auth()->user()->store_id)->findOrFail($id);

        if (OrderItem::where('product_id', $id)->exists() || PurchaseOrderItem::where('product_id', $id)->exists()) {
            $product->update(['status' => 0]);
            session()->flash('success', 'Sản phẩm đã có lịch sử giao dịch nên được chuyển sang ngừng kinh doanh thay vì xóa.');
            return;
        }

        InventoryMovement::where('store_id', auth()->user()->store_id)->where('product_id', $id)->delete();
        Inventory::where('store_id', auth()->user()->store_id)->where('product_id', $id)->delete();
        $product->delete();

        session()->flash('success', 'Đã xóa sản phẩm khỏi hệ thống!');
    }

    public function render()
    {
        $storeId = auth()->user()->store_id;

        $query = Product::where('store_id', $storeId)
            ->with(['category', 'supplier', 'inventory']);

        if (!empty($this->search)) {
            $query->where(function ($q) {
                $q->where('name', 'like', '%' . $this->search . '%')
                  ->orWhere('barcode', 'like', '%' . $this->search . '%');
            });
        }

        if (!empty($this->selectedCategory)) {
            $query->where('category_id', $this->selectedCategory);
        }

        $products = $query->orderBy('id', 'desc')->paginate(10);
        $categories = Category::where('store_id', $storeId)->get();
        $suppliers = Supplier::where('store_id', $storeId)->get();

        return view('livewire.products.index', [
            'products' => $products,
            'categories' => $categories,
            'suppliers' => $suppliers,
        ])->layout('layouts.app', ['headerTitle' => 'Quản lý Sản phẩm']);
    }
}
