<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class CheckIaeKey
{
    public function handle(Request $request, Closure $next)
    {
        $nim = env('SSO_NIM', '102022400285');
        $ssoPassword = env('SSO_PASSWORD', 'KEY-MHS-310');
        $fallbackKey = 'KEY-MHS-310';
        
        $providedKey = $request->header('X-IAE-KEY');
        
        if ($providedKey !== $nim && $providedKey !== $ssoPassword && $providedKey !== $fallbackKey) {
            return response()->json([
                'status' => 'error',
                'message' => 'Unauthorized. Invalid X-IAE-KEY.',
                'errors' => null
            ], 401)->header('Content-Type', 'application/json; charset=utf-8');
        }

        return $next($request);
    }
}