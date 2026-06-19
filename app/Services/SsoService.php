<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;

class SsoService
{
    protected $baseUrl;
    protected $defaultNim;

    public function __construct()
    {
        $this->baseUrl = config('services.iae_sso.url');
        $this->defaultNim = config('services.iae_sso.nim', '102022400285');
    }

    public function loginM2M(string $apiKey, ?string $nim = null)
    {
        $nim = $nim ?: $this->defaultNim;

        $response = Http::post($this->baseUrl . '/api/v1/auth/token', [
            'api_key' => $apiKey,
            'nim' => $nim
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
