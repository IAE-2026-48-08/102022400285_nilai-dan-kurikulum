<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class RabbitMqService
{
    public function publishEvent($routingKey, array $payload, $token)
    {
        $url = 'https://iae-sso.virtualfri.id/api/v1/messages/publish';

        // Kirim pesan ke REST Proxy RabbitMQ yang disediakan dosen menggunakan Token M2M
        $response = Http::withToken($token)
            ->post($url, [
                'exchange' => 'iae.central.exchange',
                'routing_key' => $routingKey,
                'message' => $payload // Menggunakan key 'message' sesuai format REST Proxy RabbitMQ
            ]);

        if ($response->successful()) {
            Log::info("RabbitMQ Proxy: Pesan berhasil dipublikasikan ke papan pengumuman!");
            return true;
        } else {
            Log::error("RabbitMQ Proxy Gagal: " . $response->body());
            return false;
        }
    }
}