<?php

namespace App\Livewire\Auth;

use Livewire\Component;
use App\Models\Store;
use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;

class Register extends Component
{
    public string $store_name = '';
    public string $name = '';
    public string $email = '';
    public string $phone = '';
    public string $password = '';
    public string $password_confirmation = '';

    protected array $rules = [
        'store_name' => 'required|string|max:255',
        'name' => 'required|string|max:255',
        'email' => 'required|email|unique:users,email',
        'phone' => 'nullable|string|max:20',
        'password' => 'required|string|min:6|confirmed',
    ];

    protected array $messages = [
        'store_name.required' => 'Vui lòng nhập tên cửa hàng.',
        'name.required' => 'Vui lòng nhập họ tên chủ cửa hàng.',
        'email.required' => 'Vui lòng nhập email.',
        'email.unique' => 'Email này đã được sử dụng.',
        'password.required' => 'Vui lòng nhập mật khẩu.',
        'password.min' => 'Mật khẩu phải tối thiểu 6 ký tự.',
        'password.confirmed' => 'Mật khẩu xác nhận không khớp.',
    ];

    public function register()
    {
        $this->validate();

        // 1. Tạo cửa hàng mới
        $store = Store::create([
            'name' => $this->store_name,
            'code' => Str::slug($this->store_name) . '-' . rand(100, 999),
            'phone' => $this->phone,
            'status' => 1,
            'expired_at' => now()->addYear(),
        ]);

        // 2. Tạo tài khoản chủ cửa hàng
        $user = User::create([
            'store_id' => $store->id,
            'name' => $this->name,
            'email' => $this->email,
            'phone' => $this->phone,
            'password' => Hash::make($this->password),
            'role' => 'owner',
            'status' => 1,
        ]);

        Auth::login($user);

        session()->flash('success', 'Đăng ký cửa hàng mới thành công!');
        return redirect()->route('dashboard');
    }

    public function render()
    {
        return view('livewire.auth.register')->layout('layouts.guest', ['title' => 'Đăng ký Cửa hàng']);
    }
}
