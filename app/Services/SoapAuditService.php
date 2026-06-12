<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;

class SoapAuditService
{
    public function sendAuditLog($teamId, $activityName, array $logData, $token)
    {
        $url = "https://iae-sso.virtualfri.id/soap/v1/audit";
        $jsonData = json_encode($logData);

        // XML Envelope kaku sesuai spesifikasi dokumen tugas besar
        $xmlBody = '<?xml version="1.0" encoding="UTF-8"?>
<soap:Envelope xmlns:soap="http://schemas.xmlsoap.org/soap/envelope/" xmlns:iae="http://iae.central/audit">
    <soap:Body>
        <iae:AuditRequest>
            <iae:TeamID>' . $teamId . '</iae:TeamID>
            <iae:ActivityName>' . $activityName . '</iae:ActivityName>
            <iae:LogContent><![CDATA[' . $jsonData . ']]></iae:LogContent>
        </iae:AuditRequest>
    </soap:Body>
</soap:Envelope>';

        $response = Http::withHeaders([
            'Content-Type' => 'text/xml',
        ])->withToken($token)->send('POST', $url, [
            'body' => $xmlBody
        ]);

        // Tangkap nomor resi menggunakan regex
        preg_match('/<iae:ReceiptNumber>(.*?)<\/iae:ReceiptNumber>/', $response->body(), $matches);
        return $matches[1] ?? 'IAE-LOCAL-' . uniqid(); 
    }
}
