<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;

class AuthController extends Controller
{
    /**
     * POST /api/admin/login
     * Autentikasi admin via username dan password, mengembalikan Bearer token.
     */
    public function login(Request $request): JsonResponse
    {
        $credentials = $request->validate([
            'username' => ['required', 'string', 'min:3', 'max:50', 'regex:/^[a-zA-Z0-9_]+$/'],
            'password' => ['required', 'string', 'min:8', 'max:100'],
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

        // Hapus token lama (satu sesi aktif per user)
        $user->tokens()->delete();

        // Buat token baru
        $token = $user->createToken('admin-panel')->plainTextToken;

        return response()->json([
            'success' => true,
            'data' => [
                'token' => $token,
                'user'  => [
                    'id'    => $user->id,
                    'name'  => $user->name,
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
        // Hapus token yang sedang dipakai
        $request->user()->currentAccessToken()->delete();

        return response()->json(['success' => true, 'message' => 'Berhasil logout.']);
    }

    /**
     * GET /api/admin/me
     */
    public function me(Request $request): JsonResponse
    {
        $user = $request->user();

        return response()->json([
            'success' => true,
            'data' => [
                'user' => [
                    'id' => $user->id,
                    'name' => $user->name,
                    'email' => $user->email,
                ],
            ],
        ]);
    }
}
