<?php

namespace App\Services\Payin\Providers;

use App\Models\User;
use App\Models\Report;
use App\Services\Payin\Contracts\PayinProviderInterface;
use App\Services\Payin\PayinService;



class AirpayProvider implements PayinProviderInterface
{
    public function __construct(private PayinService $payinService) {}

    private function getMidConfig()
    {
        $mids = [
            [
                "url" => "https://ebookspay.co.in/dashboard/api/generateQR",
                "mid" => [
                    "merchant_id" => "352568",
                    "username" => "UB2uYkYSYM",
                    "password" => "Yst9qn9A",
                    "client_id" => "179754",
                    "client_secret" => "1d290bf8c8d70aa6d05f68cebfd3e9f1",
                    "secretKey" => "e5619176a4122465e38232fbb5be074c",
                    "secret" => "1d290bf8c8d70aa6d05f68cebfd3e9f1"
                ]
            ],
            [
                "url" => "https://omishajewels.com/Backend/api/generateQR",
                "mid" => [
                    "merchant_id" => "353405",
                    "username" => "zY4KPwTjP4",
                    "password" => "dqQE4f8z",
                    "client_id" => "454969",
                    "client_secret" => "4fbb61f1f5a95a242b14f4e44218dcc5",
                    "secretKey" => "67d5c956c204bb6719bff713904d5bd7",
                    "secret" => "4fbb61f1f5a95a242b14f4e44218dcc5"
                ]
            ]
        ];

        // Redis counter (best) OR cache fallback
        // $index = cache()->increment('airpay_mid_counter') % count($mids);
        $index = (cache()->increment('airpay_mid_counter') - 1) % count($mids);

        return $mids[$index];
    }

    public function generateUpiQr(array $payload, User $user, Report $report): array
    {
        $config = $this->getMidConfig();

        $req = [
            'orderid'     => $payload['orderid'],
            'amount'      => $payload['amount'],
            'buyer_email' => $payload['email'],
            'buyer_phone' => $payload['phone'],
            'mid'         => $config['mid'],
        ];

        $res = $this->payinService->commonCurl(
            $config['url'],
            $req,
            ["Content-Type: application/json"]
        );
// dd($res);
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
            'option4'  => $config['mid']['merchant_id'] ?? null,
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