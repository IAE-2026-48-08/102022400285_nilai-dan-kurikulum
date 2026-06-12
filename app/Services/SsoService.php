<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;

class SsoService
{
    protected $baseUrl;

    public function __construct()
    {
        $this->baseUrl = config('services.iae_sso.url');
    }

    public function loginM2M(string $apiKey)
    {
        $response = Http::post($this->baseUrl . '/api/v1/auth/token', [
            'api_key' => $apiKey
        ]);

        return $response->json();
    }

    public function loginUser(string $email, string $password)
    {
        $response = Http::post($this->baseUrl . '/api/v1/auth/token', [
            'email' => $email,
            'password' => $password
        ]);

        return $response->json();
    }

    // Keep loginWarga as a backward compatibility helper
    public function loginWarga(string $email = null)
    {
        return $this->loginM2M(env('SSO_PASSWORD', 'KEY-MHS-310'));
    }
}
