<?php

namespace App\Livewire\Customers;

use App\Models\Customer;
use Livewire\Component;

class Index extends Component
{
    public string $name = '';

    public string $phone = '';

    public string $address = '';

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
        $this->resetValidation();
    }

    public function edit(int $id)
    {
        $c = Customer::where('store_id', auth()->user()->store_id)->findOrFail($id);
        $this->customerId = $c->id;
        $this->name = $c->name;
        $this->phone = $c->phone ?? '';
        $this->address = $c->address ?? '';
        $this->isModalOpen = true;
    }

    public function save()
    {
        $this->name = trim($this->name);
        $this->validate([
            'name' => 'required|string|max:255',
            'phone' => 'nullable|string|max:20',
            'address' => 'nullable|string|max:255',
        ]);
        $storeId = auth()->user()->store_id;

        if ($this->customerId) {
            Customer::where('store_id', $storeId)->where('id', $this->customerId)->update([
                'name' => $this->name,
                'phone' => $this->phone,
                'address' => $this->address,
            ]);
            session()->flash('success', 'Đã cập nhật khách hàng.');
        } else {
            Customer::create([
                'store_id' => $storeId,
                'name' => $this->name,
                'phone' => $this->phone,
                'address' => $this->address,
                'point' => 0,
            ]);
            session()->flash('success', 'Đã thêm khách hàng.');
        }

        $this->closeModal();
    }

    public function delete(int $id)
    {
        $customer = Customer::where('store_id', auth()->user()->store_id)->findOrFail($id);
        if ($customer->orders()->exists()) {
            session()->flash('error', 'Không thể xóa khách hàng đã có lịch sử mua hàng.');

            return;
        }
        $customer->delete();
        session()->flash('success', 'Đã xóa khách hàng.');
    }

    public function render()
    {
        $customers = Customer::where('store_id', auth()->user()->store_id)
            ->withCount('orders')
            ->get();

        return view('livewire.customers.index', [
            'customers' => $customers,
        ])->layout('layouts.app', ['headerTitle' => 'Khách hàng']);
    }
}
