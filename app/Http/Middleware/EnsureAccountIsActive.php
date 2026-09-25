<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureAccountIsActive
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();
        $store = $user?->store;

        if (! $user || ! $user->status || ! $store || ! $store->status || ($store->expired_at && $store->expired_at->isPast())) {
            auth()->logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();

            return redirect()->route('login')->with('error', 'Tài khoản hoặc cửa hàng đã bị khóa hay hết hạn sử dụng.');
        }

        return $next($request);
    }
}
