<?php

namespace App\Livewire\Users;

use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Livewire\Component;

class Index extends Component
{
    public string $name = '';
    public string $email = '';
    public string $phone = '';
    public string $password = '';
    public string $role = 'staff';
    public ?int $userId = null;
    public bool $isModalOpen = false;

    public function openModal(): void
    {
        $this->resetForm();
        $this->isModalOpen = true;
    }

    public function edit(int $id): void
    {
        $user = User::where('store_id', auth()->user()->store_id)->findOrFail($id);
        $this->userId = $user->id;
        $this->name = $user->name;
        $this->email = $user->email;
        $this->phone = $user->phone ?? '';
        $this->password = '';
        $this->role = $user->role;
        $this->isModalOpen = true;
    }

    public function closeModal(): void
    {
        $this->isModalOpen = false;
        $this->resetForm();
    }

    public function resetForm(): void
    {
        $this->reset(['userId', 'name', 'email', 'phone', 'password']);
        $this->role = 'staff';
        $this->resetValidation();
    }

    public function save(): void
    {
        $this->validate([
            'name' => 'required|string|max:255',
            'email' => ['required', 'email', Rule::unique('users', 'email')->ignore($this->userId)],
            'phone' => 'nullable|string|max:20',
            'password' => [$this->userId ? 'nullable' : 'required', 'string', 'min:8'],
            'role' => 'required|in:manager,staff',
        ]);

        $storeId = auth()->user()->store_id;
        $data = [
            'name' => $this->name,
            'email' => $this->email,
            'phone' => $this->phone ?: null,
            'role' => $this->role,
        ];
        if ($this->password !== '') {
            $data['password'] = Hash::make($this->password);
        }

        if ($this->userId) {
            $user = User::where('store_id', $storeId)->findOrFail($this->userId);
            if ($user->isOwner()) {
                throw ValidationException::withMessages(['role' => 'Không thể thay đổi vai trò chủ cửa hàng tại đây.']);
            }
            $user->update($data);
            session()->flash('success', 'Đã cập nhật thông tin nhân viên.');
        } else {
            User::create($data + ['store_id' => $storeId, 'status' => 1]);
            session()->flash('success', 'Đã tạo tài khoản nhân viên.');
        }

        $this->closeModal();
    }

    public function toggleStatus(int $id): void
    {
        $user = User::where('store_id', auth()->user()->store_id)->findOrFail($id);
        abort_if($user->id === auth()->id() || $user->isOwner(), 422, 'Không thể khóa tài khoản chủ cửa hàng.');
        $user->update(['status' => $user->status ? 0 : 1]);
        session()->flash('success', $user->status ? 'Đã mở khóa tài khoản.' : 'Đã khóa tài khoản.');
    }

    public function render()
    {
        return view('livewire.users.index', [
            'users' => User::where('store_id', auth()->user()->store_id)->orderByRaw("role = 'owner' desc")->orderBy('name')->get(),
        ])->layout('layouts.app', ['headerTitle' => 'Quản lý Nhân viên Cửa hàng']);
    }
}
