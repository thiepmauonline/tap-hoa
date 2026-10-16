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
        'email.required' => 'Vui lòng nhập địa chỉ email.',
        'email.email' => 'Email không đúng định dạng.',
        'password.required' => 'Vui lòng nhập mật khẩu.',
    ];

    public function login()
    {
        $this->validate();
        $key = 'login:'.hash('sha256', mb_strtolower($this->email).'|'.request()->ip());
        if (RateLimiter::tooManyAttempts($key, 5)) {
            $this->addError('email', 'Bạn đã đăng nhập sai quá nhiều lần. Vui lòng thử lại sau '.RateLimiter::availableIn($key).' giây.');

            return;
        }

        if (Auth::attempt(['email' => $this->email, 'password' => $this->password], $this->remember)) {
            $user = Auth::user();
            $store = $user->store;

            if (! $user->status || ! $store || ! $store->status || ($store->expired_at && $store->expired_at->isPast())) {
                Auth::logout();
                $this->addError('email', 'Tài khoản hoặc cửa hàng đã bị khóa hay hết hạn sử dụng.');

                return;
            }

            session()->regenerate();
            RateLimiter::clear($key);

            return redirect()->intended(route('dashboard'));
        }

        RateLimiter::hit($key, 60);
        $this->addError('email', 'Thông tin email hoặc mật khẩu không chính xác.');
    }

    public function render()
    {
        return view('livewire.auth.login')->layout('layouts.guest', ['title' => 'Đăng nhập']);
    }
}
