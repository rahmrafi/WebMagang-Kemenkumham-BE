<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class AuthController extends Controller
{
    private const CACHE_PREFIX = 'admin_api_token:';

    /**
     * POST /api/admin/login
     * Autentikasi admin via username dan password, mengembalikan bearer token internal.
     */
    public function login(Request $request): JsonResponse
    {
        $credentials = $request->validate([
            'username' => ['required', 'string'],
            'password' => ['required', 'string'],
        ]);

        if (! Auth::attempt($credentials)) {
            throw ValidationException::withMessages([
                'username' => ['Username atau password salah.'],
            ]);
        }

        $user = Auth::user();

        // Pastikan hanya user dengan role admin yang boleh login ke panel ini
        if (!$user->is_admin) {
            Auth::logout();
            return response()->json([
                'success' => false,
                'message' => 'Akun ini tidak memiliki akses admin.',
            ], 403);
        }

        $token = Str::random(64);
        Cache::put(self::CACHE_PREFIX . $token, $user->id, now()->addHours(8));

        return response()->json([
            'success' => true,
            'data' => [
                'token' => $token,
                'token_type' => 'Bearer',
                'user' => [
                    'id' => $user->id,
                    'name' => $user->name,
                    'email' => $user->email,
                ],
            ],
        ]);
    }

    /**
     * POST /api/admin/logout
     */
    public function logout(Request $request): JsonResponse
    {
        $token = $request->attributes->get('admin_token', $request->bearerToken());

        if (is_string($token) && $token !== '') {
            Cache::forget(self::CACHE_PREFIX . $token);
        }

        return response()->json(['success' => true, 'message' => 'Berhasil logout.']);
    }
}
