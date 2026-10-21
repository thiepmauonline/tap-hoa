<?php

namespace App\Livewire\Auth;

use App\Models\Store;
use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Livewire\Component;

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
        'email' => 'required|email|max:255|unique:users,email',
        'phone' => 'nullable|string|max:20',
        'password' => 'required|string|min:8|max:255|confirmed',
    ];

    protected array $messages = [
        'store_name.required' => 'Vui lòng nhập tên cửa hàng.',
        'name.required' => 'Vui lòng nhập họ tên chủ cửa hàng.',
        'email.required' => 'Vui lòng nhập email.',
        'email.unique' => 'Email đã được sử dụng.',
        'password.required' => 'Vui lòng nhập mật khẩu.',
        'password.min' => 'Mật khẩu cần ít nhất 8 ký tự.',
        'password.confirmed' => 'Mật khẩu xác nhận không khớp.',
    ];

    public function register()
    {
        $this->validate();

        $user = DB::transaction(function () {
            $store = Store::create([
                'name' => $this->store_name,
                'code' => Str::uuid()->toString(),
                'phone' => $this->phone,
                'status' => 1,
                'expired_at' => now()->addYear(),
            ]);

            // 2. Tạo tài khoản chủ cửa hàng
            return User::create([
                'store_id' => $store->id,
                'name' => $this->name,
                'email' => $this->email,
                'phone' => $this->phone,
                'password' => Hash::make($this->password),
                'role' => 'owner',
                'status' => 1,
            ]);
        });

        Auth::login($user);
        session()->regenerate();
        $this->reset('password', 'password_confirmation');

        session()->flash('success', 'Đã đăng ký cửa hàng.');

        return redirect()->route('dashboard');
    }

    public function render()
    {
        return view('livewire.auth.register')->layout('layouts.guest', ['title' => 'Đăng ký cửa hàng']);
    }
}
