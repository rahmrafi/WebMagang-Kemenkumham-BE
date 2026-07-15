<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class EnsureDesktopOrTablet
{
    /**
     * Pola User-Agent yang HANYA cocok dengan smartphone (bukan tablet / desktop).
     *
     * Logika deteksi:
     *  - Android + Mobile  → Android phone (bukan Android tablet)
     *  - iPhone            → Apple iPhone
     *  - Windows Phone     → Windows Phone lama
     *  - BlackBerry / BB10 → Perangkat Blackberry
     *  - Opera Mini        → Browser mobile ringan
     *  - IEMobile          → IE di Windows Phone
     *
     * Yang TIDAK diblokir (diizinkan):
     *  - Android tanpa "Mobile" keyword → Android tablet
     *  - iPad                            → Apple tablet
     *  - Desktop (Chrome, Firefox, Edge, Safari desktop, dsb.)
     */
    private const MOBILE_PATTERNS = [
        '/Android.+Mobile/i',  // Android phone (bukan Android tablet)
        '/iPhone/i',
        '/Windows Phone/i',
        '/BlackBerry/i',
        '/BB10/i',
        '/Opera Mini/i',
        '/IEMobile/i',
    ];

    public function handle(Request $request, Closure $next): mixed
    {
        $userAgent = (string) $request->header('User-Agent', '');

        if ($this->isMobilePhone($userAgent)) {
            return response()->json([
                'success' => false,
                'message' => 'Akses panel admin hanya diizinkan melalui desktop, laptop, atau tablet. '
                           . 'Silakan gunakan perangkat yang sesuai.',
                'error'   => 'MOBILE_ACCESS_DENIED',
            ], 403);
        }

        return $next($request);
    }

    /**
     * Periksa apakah User-Agent berasal dari smartphone.
     */
    private function isMobilePhone(string $userAgent): bool
    {
        if (empty($userAgent)) {
            return false; // Tidak ada UA → biarkan lewat (mungkin API client/Postman)
        }

        foreach (self::MOBILE_PATTERNS as $pattern) {
            if (preg_match($pattern, $userAgent)) {
                return true;
            }
        }

        return false;
    }
}
