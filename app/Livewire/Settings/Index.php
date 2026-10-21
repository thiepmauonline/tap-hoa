<?php

namespace App\Livewire\Settings;

use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Livewire\Component;

class Index extends Component
{
    public string $storeName = '';

    public string $storePhone = '';

    public string $storeAddress = '';

    public string $currentPassword = '';

    public string $newPassword = '';

    public string $newPasswordConfirmation = '';

    public function mount(): void
    {
        $store = auth()->user()->store;
        $this->storeName = $store->name;
        $this->storePhone = $store->phone ?? '';
        $this->storeAddress = $store->address ?? '';
    }

    public function saveStore(): void
    {
        abort_unless(auth()->user()->isOwner(), 403);
        $this->storeName = trim($this->storeName);
        $this->validate([
            'storeName' => 'required|string|max:255',
            'storePhone' => 'nullable|string|max:20',
            'storeAddress' => 'nullable|string|max:255',
        ], [
            'storeName.required' => 'Vui lòng nhập tên cửa hàng.',
            'storeName.max' => 'Tên cửa hàng tối đa 255 ký tự.',
            'storePhone.max' => 'Số điện thoại tối đa 20 ký tự.',
            'storeAddress.max' => 'Địa chỉ tối đa 255 ký tự.',
        ]);

        auth()->user()->store->update([
            'name' => $this->storeName,
            'phone' => trim($this->storePhone) ?: null,
            'address' => trim($this->storeAddress) ?: null,
        ]);
        session()->flash('settingsSuccess', 'Đã lưu thông tin cửa hàng.');
    }

    public function changePassword(): void
    {
        $this->validate([
            'currentPassword' => 'required|string|current_password',
            'newPassword' => 'required|string|min:8|max:255|different:currentPassword',
            'newPasswordConfirmation' => 'required|same:newPassword',
        ], [
            'currentPassword.required' => 'Vui lòng nhập mật khẩu hiện tại.',
            'currentPassword.current_password' => 'Mật khẩu hiện tại không đúng.',
            'newPassword.required' => 'Vui lòng nhập mật khẩu mới.',
            'newPassword.min' => 'Mật khẩu mới cần ít nhất 8 ký tự.',
            'newPassword.max' => 'Mật khẩu mới tối đa 255 ký tự.',
            'newPassword.different' => 'Mật khẩu mới phải khác mật khẩu hiện tại.',
            'newPasswordConfirmation.required' => 'Vui lòng xác nhận mật khẩu mới.',
            'newPasswordConfirmation.same' => 'Mật khẩu xác nhận không khớp.',
        ]);

        auth()->user()->update([
            'password' => Hash::make($this->newPassword),
            'remember_token' => Str::random(60),
        ]);
        session()->regenerate();
        $this->reset('currentPassword', 'newPassword', 'newPasswordConfirmation');
        $this->resetValidation();
        session()->flash('settingsSuccess', 'Đã đổi mật khẩu.');
    }

    public function render()
    {
        return view('livewire.settings.index')->layout('layouts.app', ['headerTitle' => 'Cài đặt']);
    }
}
