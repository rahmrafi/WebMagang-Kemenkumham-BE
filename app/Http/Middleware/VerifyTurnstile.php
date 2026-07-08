<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use Illuminate\Support\Facades\Http;

class VerifyTurnstile
{
    public function handle(Request $request, Closure $next)
    {
        $token = (string) $request->input('cf_turnstile_token', '');
        $secret = config('services.turnstile.secret');

        if ($secret === null || $secret === '') {
            return $next($request);
        }

        if ($token === '') {
            return response()->json(['success' => false, 'message' => 'Token Turnstile wajib diisi.'], 422);
        }

        $response = Http::asForm()->post('https://challenges.cloudflare.com/turnstile/v0/siteverify', [
            'secret' => $secret,
            'response' => $token,
            'remoteip' => $request->ip(),
        ]);

        if (! $response->json('success')) {
            return response()->json(['success' => false, 'message' => 'Verifikasi Turnstile gagal.'], 422);
        }

        return $next($request);
    }
}