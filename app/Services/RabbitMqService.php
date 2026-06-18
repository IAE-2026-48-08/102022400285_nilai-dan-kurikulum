<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class RabbitMqService
{
    public function publishEvent($routingKey, array $payload)
    {
        // 1. Coba ambil token dari session
        $token = session('api_token');

        // 2. Jika token kosong, coba lakukan autentikasi ulang (get new token)
        if (!$token) {
            $response = Http::post('https://iae-sso.virtualfri.id/api/v1/auth/token', [
                'api_key' => 'KEY-MHS-310' // GANTI dengan API Key milik kelompokmu!
            ]);

            if ($response->successful()) {
                $token = $response->json()['token'];
                session(['api_token' => $token]); // Simpan agar bisa dipakai lagi nanti
            } else {
                Log::error("RabbitMQ Service: Gagal mendapatkan token otomatis. " . $response->body());
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