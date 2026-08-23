<?php

namespace App\Livewire\Users;

use Livewire\Component;
use App\Models\User;
use Illuminate\Support\Facades\Hash;

class Index extends Component
{
    public string $name = '';
    public string $email = '';
    public string $phone = '';
    public string $password = '';
    public string $role = 'staff';
    public ?int $userId = null;
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
        $this->userId = null;
        $this->name = '';
        $this->email = '';
        $this->phone = '';
        $this->password = '';
        $this->role = 'staff';
    }

    public function save()
    {
        $storeId = auth()->user()->store_id;

        if ($this->userId) {
            $data = [
                'name' => $this->name,
                'email' => $this->email,
                'phone' => $this->phone,
                'role' => $this->role,
            ];
            if (!empty($this->password)) {
                $data['password'] = Hash::make($this->password);
            }
            User::where('store_id', $storeId)->where('id', $this->userId)->update($data);
            session()->flash('success', 'Đã cập nhật thông tin nhân viên!');
        } else {
            $this->validate([
                'name' => 'required|string|max:255',
                'email' => 'required|email|unique:users,email',
                'password' => 'required|string|min:6',
            ]);

            User::create([
                'store_id' => $storeId,
                'name' => $this->name,
                'email' => $this->email,
                'phone' => $this->phone,
                'password' => Hash::make($this->password),
                'role' => $this->role,
                'status' => 1,
            ]);
            session()->flash('success', 'Tạo tài khoản nhân viên thành công!');
        }

        $this->closeModal();
    }

    public function render()
    {
        $users = User::where('store_id', auth()->user()->store_id)->get();

        return view('livewire.users.index', [
            'users' => $users,
        ])->layout('layouts.app', ['headerTitle' => 'Quản lý Nhân viên Cửa hàng']);
    }
}
