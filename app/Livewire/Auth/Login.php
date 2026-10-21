<?php

namespace App\Livewire\Auth;

use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\RateLimiter;
use Livewire\Component;

class Login extends Component
{
    public string $email = '';

    public string $password = '';

    public bool $remember = false;

    protected array $rules = [
        'email' => 'required|email',
        'password' => 'required|string',
    ];

    protected array $messages = [
        'email.required' => 'Vui lòng nhập email.',
        'email.email' => 'Email không hợp lệ.',
        'password.required' => 'Vui lòng nhập mật khẩu.',
    ];

    public function login()
    {
        $this->validate();
        $key = 'login:'.hash('sha256', mb_strtolower($this->email).'|'.request()->ip());
        if (RateLimiter::tooManyAttempts($key, 5)) {
            $this->addError('email', 'Đăng nhập sai quá nhiều lần. Thử lại sau '.RateLimiter::availableIn($key).' giây.');

            return;
        }

        if (Auth::attempt(['email' => $this->email, 'password' => $this->password], $this->remember)) {
            $user = Auth::user();
            $store = $user->store;

            if (! $user->status || ! $store || ! $store->status || ($store->expired_at && $store->expired_at->isPast())) {
                Auth::logout();
                $this->addError('email', 'Tài khoản hoặc cửa hàng bị khóa hoặc hết hạn.');

                return;
            }

            session()->regenerate();
            RateLimiter::clear($key);

            return redirect()->intended(route('dashboard'));
        }

        RateLimiter::hit($key, 60);
        $this->addError('email', 'Email hoặc mật khẩu không đúng.');
    }

    public function render()
    {
        return view('livewire.auth.login')->layout('layouts.guest', ['title' => 'Đăng nhập']);
    }
}
