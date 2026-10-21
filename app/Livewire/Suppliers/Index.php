<?php

namespace App\Livewire\Suppliers;

use App\Models\Supplier;
use Livewire\Component;

class Index extends Component
{
    public string $name = '';

    public string $phone = '';

    public string $email = '';

    public string $address = '';

    public string $note = '';

    public ?int $supplierId = null;

    public bool $isModalOpen = false;

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
        $this->supplierId = null;
        $this->name = '';
        $this->phone = '';
        $this->email = '';
        $this->address = '';
        $this->note = '';
        $this->resetValidation();
    }

    public function edit(int $id)
    {
        $sup = Supplier::where('store_id', auth()->user()->store_id)->findOrFail($id);
        $this->supplierId = $sup->id;
        $this->name = $sup->name;
        $this->phone = $sup->phone ?? '';
        $this->email = $sup->email ?? '';
        $this->address = $sup->address ?? '';
        $this->note = $sup->note ?? '';
        $this->isModalOpen = true;
    }

    public function save()
    {
        $this->name = trim($this->name);
        $this->validate([
            'name' => 'required|string|max:255',
            'phone' => 'nullable|string|max:20',
            'email' => 'nullable|email|max:255',
            'address' => 'nullable|string|max:255',
            'note' => 'nullable|string|max:2000',
        ]);
        $storeId = auth()->user()->store_id;

        if ($this->supplierId) {
            Supplier::where('store_id', $storeId)->where('id', $this->supplierId)->update([
                'name' => $this->name,
                'phone' => $this->phone,
                'email' => $this->email,
                'address' => $this->address,
                'note' => $this->note,
            ]);
            session()->flash('success', 'Đã cập nhật nhà cung cấp.');
        } else {
            Supplier::create([
                'store_id' => $storeId,
                'name' => $this->name,
                'phone' => $this->phone,
                'email' => $this->email,
                'address' => $this->address,
                'note' => $this->note,
            ]);
            session()->flash('success', 'Đã thêm nhà cung cấp.');
        }

        $this->closeModal();
    }

    public function delete(int $id)
    {
        $supplier = Supplier::where('store_id', auth()->user()->store_id)->findOrFail($id);
        if ($supplier->products()->exists() || $supplier->purchaseOrders()->exists()) {
            session()->flash('error', 'Không thể xóa nhà cung cấp đã có sản phẩm hoặc phiếu nhập.');

            return;
        }
        $supplier->delete();
        session()->flash('success', 'Đã xóa nhà cung cấp.');
    }

    public function render()
    {
        $suppliers = Supplier::where('store_id', auth()->user()->store_id)->get();

        return view('livewire.suppliers.index', [
            'suppliers' => $suppliers,
        ])->layout('layouts.app', ['headerTitle' => 'Nhà cung cấp']);
    }
}
