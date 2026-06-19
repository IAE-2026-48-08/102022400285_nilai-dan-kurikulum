<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class RabbitMqService
{
    public function publishEvent($routingKey, array $payload, $token = null)
    {
        // 1. Coba ambil token dari parameter atau session
        $token = $token ?: session('api_token');

        // 2. Jika token kosong, coba lakukan autentikasi ulang (get new token)
        if (!$token) {
            $ssoService = new SsoService();
            $ssoResponse = $ssoService->loginM2M(
                env('SSO_PASSWORD', 'KEY-MHS-310'),
                env('SSO_NIM', '102022400285')
            );

            if (isset($ssoResponse['token'])) {
                $token = $ssoResponse['token'];
                session(['api_token' => $token]); // Simpan agar bisa dipakai lagi nanti
            } else {
                Log::error("RabbitMQ Service: Gagal mendapatkan token otomatis. Response: " . json_encode($ssoResponse));
                return false;
            }
        }

        // 3. Kirim pesan ke API Gateway menggunakan token yang sudah didapat
        $response = Http::withToken($token)
            ->post('https://iae-sso.virtualfri.id/api/v1/messages/publish', [
                'exchange' => 'iae.central.exchange',
                'routing_key' => $routingKey,
                'payload' => $payload
            ]);

        // 4. Logging hasil
        if ($response->successful()) {
            Log::info("RabbitMQ (via API): Pesan berhasil terkirim ke exchange 'iae.central.exchange'!");
            return true;
        } else {
            Log::error("RabbitMQ (via API) Gagal: " . $response->body());
            return false;
        }
    }
}