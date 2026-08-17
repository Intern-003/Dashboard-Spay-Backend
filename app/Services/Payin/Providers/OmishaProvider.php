<?php

namespace App\Services\Payin\Providers;

use App\Models\User;
use App\Models\Report;
use App\Services\Payin\Contracts\PayinProviderInterface;
use App\Services\Payin\PayinService;

class OmishaProvider implements PayinProviderInterface
{
    public function __construct(private PayinService $payinService) {}

    public function generateUpiQr(array $payload, User $user, Report $report): array
    {
        $url = "https://omishajewels.com/Backend/api/generateQR";

        $desc = $this->payinService->getCredentialDescription($user);
        // dd($desc);

        $req = [
            'orderid'     => $payload['orderid'],
            'amount'      => $payload['amount'],
            'buyer_email' => $payload['email'],
            'buyer_phone' => $payload['phone'],
            'mid'         => json_encode($desc, JSON_UNESCAPED_SLASHES),
        ];

        $res = $this->payinService->commonCurl($url, $req, ["Content-Type: application/json"]);

        $decoded = $res['response'] ?? null;
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

        $report->update([
            'apitxnid' => $data['ap_transactionid'] ?? null,
            'option4'  => $desc['merchant_id'] ?? null,
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