<?php

namespace App\Services\Payin\Providers;

use App\Models\User;
use App\Models\Report;
use App\Services\Payin\Contracts\PayinProviderInterface;
use App\Services\Payin\PayinService;

class AirpayAllProvider implements PayinProviderInterface
{
    public function __construct(private PayinService $payinService) {}

    public function generateUpiQr(array $payload, User $user, Report $report): array
    {
        // QR API URL (same you used)
        $url = "https://ebookspay.co.in/dashboard/api/generateQR";

        $midCreds = $payload['active_mid_credentials'] ?? [];
        $req = [
            'orderid'     => $payload['orderid'],
            'amount'      => $payload['amount'],
            'buyer_email' => $payload['email'],
            'buyer_phone' => $payload['phone'],
            'mid'         => json_encode($midCreds, JSON_UNESCAPED_SLASHES),
        ];

        $res = $this->payinService->commonCurl($url, $req, ["Content-Type: application/json"]);

        $decoded = $res['response'] ?? null; // your backend returns response->array
        if (!is_array($decoded) || ($decoded['status'] ?? '') !== 'success') {
            return [
                'http_code' => 500,
                'body' => [
                    'status' => 'failed',
                    'message' => 'QR API did not return success',
                    'raw' => $res
                ]
            ];
        }

        $data = $decoded['data'] ?? [];

        // update report
        $report->update([
            'apitxnid' => $data['ap_transactionid'] ?? null,
        ]);

        return [
            'http_code' => 200,
            'body' => [
                'status_code' => $decoded['status_code'] ?? null,
                'status'      => $decoded['status'] ?? null,
                'data'        => [
                    'qrcode_string' => $data['qrcode_string'] ?? null,
                    'orderid'       => $payload['orderid'],
                    'txnid'         => $report->txnid,
                ],
            ]
        ];
    }
}