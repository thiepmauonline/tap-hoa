<?php

namespace App\Livewire\Suppliers;

use Livewire\Component;
use App\Models\Supplier;

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
        $this->validate(['name' => 'required|string|max:255']);
        $storeId = auth()->user()->store_id;

        if ($this->supplierId) {
            Supplier::where('store_id', $storeId)->where('id', $this->supplierId)->update([
                'name' => $this->name,
                'phone' => $this->phone,
                'email' => $this->email,
                'address' => $this->address,
                'note' => $this->note,
            ]);
            session()->flash('success', 'Cập nhật nhà cung cấp thành công!');
        } else {
            Supplier::create([
                'store_id' => $storeId,
                'name' => $this->name,
                'phone' => $this->phone,
                'email' => $this->email,
                'address' => $this->address,
                'note' => $this->note,
            ]);
            session()->flash('success', 'Thêm nhà cung cấp mới thành công!');
        }

        $this->closeModal();
    }

    public function delete(int $id)
    {
        Supplier::where('store_id', auth()->user()->store_id)->where('id', $id)->delete();
        session()->flash('success', 'Đã xóa nhà cung cấp!');
    }

    public function render()
    {
        $suppliers = Supplier::where('store_id', auth()->user()->store_id)->get();

        return view('livewire.suppliers.index', [
            'suppliers' => $suppliers,
        ])->layout('layouts.app', ['headerTitle' => 'Quản lý Nhà cung cấp']);
    }
}
