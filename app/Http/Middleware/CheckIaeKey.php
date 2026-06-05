<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class CheckIaeKey
{
    public function handle(Request $request, Closure $next)
    {
        // Menggunakan NIM Muhammad Manhal Syarifudin
        $nimMahasiswa = '102022400285'; 
        
        if ($request->header('X-IAE-KEY') !== $nimMahasiswa) {
            return response()->json([
                'status' => 'error',
                'message' => 'Unauthorized. Invalid X-IAE-KEY.',
                'errors' => null
            ], 401)->header('Content-Type', 'application/json; charset=utf-8');
        }

        return $next($request);
    }
}
