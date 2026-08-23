<?php

namespace App\Livewire\Customers;

use Livewire\Component;
use App\Models\Customer;

class Index extends Component
{
    public string $name = '';
    public string $phone = '';
    public string $address = '';
    public int $point = 0;
    public ?int $customerId = null;
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
        $this->customerId = null;
        $this->name = '';
        $this->phone = '';
        $this->address = '';
        $this->point = 0;
    }

    public function edit(int $id)
    {
        $c = Customer::where('store_id', auth()->user()->store_id)->findOrFail($id);
        $this->customerId = $c->id;
        $this->name = $c->name;
        $this->phone = $c->phone ?? '';
        $this->address = $c->address ?? '';
        $this->point = $c->point;
        $this->isModalOpen = true;
    }

    public function save()
    {
        $this->validate(['name' => 'required|string|max:255']);
        $storeId = auth()->user()->store_id;

        if ($this->customerId) {
            Customer::where('store_id', $storeId)->where('id', $this->customerId)->update([
                'name' => $this->name,
                'phone' => $this->phone,
                'address' => $this->address,
                'point' => $this->point,
            ]);
            session()->flash('success', 'Đã cập nhật thông tin khách hàng!');
        } else {
            Customer::create([
                'store_id' => $storeId,
                'name' => $this->name,
                'phone' => $this->phone,
                'address' => $this->address,
                'point' => $this->point,
            ]);
            session()->flash('success', 'Thêm khách hàng mới thành công!');
        }

        $this->closeModal();
    }

    public function delete(int $id)
    {
        Customer::where('store_id', auth()->user()->store_id)->where('id', $id)->delete();
        session()->flash('success', 'Đã xóa khách hàng!');
    }

    public function render()
    {
        $customers = Customer::where('store_id', auth()->user()->store_id)
            ->withCount('orders')
            ->get();

        return view('livewire.customers.index', [
            'customers' => $customers,
        ])->layout('layouts.app', ['headerTitle' => 'Quản lý Khách hàng']);
    }
}
