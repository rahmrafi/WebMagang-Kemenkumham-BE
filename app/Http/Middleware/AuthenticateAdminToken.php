<?php

namespace App\Http\Middleware;

use App\Models\User;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;

class AuthenticateAdminToken
{
    private const CACHE_PREFIX = 'admin_api_token:';

    public function handle(Request $request, Closure $next): mixed
    {
        $token = $request->bearerToken();

        if (! is_string($token) || $token === '') {
            return response()->json(['success' => false, 'message' => 'Token admin tidak ditemukan.'], 401);
        }

        $userId = Cache::get(self::CACHE_PREFIX . $token);

        if ($userId === null) {
            return response()->json(['success' => false, 'message' => 'Token admin tidak valid atau sudah kedaluwarsa.'], 401);
        }

        $user = User::query()->find($userId);

        if (! $user || ! $user->is_admin) {
            Cache::forget(self::CACHE_PREFIX . $token);

            return response()->json(['success' => false, 'message' => 'Akses admin tidak valid.'], 401);
        }

        Auth::setUser($user);
        $request->setUserResolver(fn () => $user);
        $request->attributes->set('admin_token', $token);

        return $next($request);
    }
}