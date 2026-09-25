<?php
namespace App\Http\Middleware;
use App\Models\User;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;
class AuthenticateApiToken {
    public function handle(Request $request, Closure $next): Response {
        $token = $request->bearerToken();
        $user = $token ? User::where('api_token', hash('sha256', $token))->with('store')->first() : null;
        if (! $user || ! $user->status || ! $user->store || ! $user->store->status || ($user->store->expired_at && $user->store->expired_at->isPast())) {
            return response()->json(['message' => 'Unauthenticated.'], 401);
        }
        Auth::setUser($user);
        return $next($request);
    }
}
