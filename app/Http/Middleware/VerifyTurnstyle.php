<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class VerifyTurnstyle
{
    public function handle(Request $request, Closure $next): Response
    {
        if (! config('services.turnstile.secret')) {
            return $next($request);
        }

        return $next($request);
    }
}
