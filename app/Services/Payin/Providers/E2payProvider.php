<?php

namespace App\Services\Payin\Providers;

use App\Models\User;
use App\Models\Report;
use App\Services\Payin\Contracts\PayinProviderInterface;
use App\Services\Payin\PayinService;

class E2payProvider implements PayinProviderInterface
{
    public function __construct(private PayinService $payinService) {}

    public function generateUpiQr(array $payload, User $user, Report $report): array
    {
        $url = "https://marketingllp.in/api/payin/initiate";
        $token = "k9njUwyaPf4RXyNPmaQVF6SWPIDwz5nO";
        


        $req = [
            'memberId'     => 'MT12996511',
            'orderid'      => $payload['orderid'],
            'name'         => $payload['name'] ?? 'User',
            'amount'       => $payload['amount'],
            'email'        => $payload['email'],
            'mobile'       => $payload['phone'],
            'callback_url' => 'https://marketingllp.in/api/payin',
        ];
        
        $headers = [
            "Token: $token",
            "Content-Type: application/json"
        ];
// dump($req);
// dump($headers);
        // ✅ API Call
        $res = $this->payinService->commonCurl($url, $req, $headers);
// dd($res);
        // ✅ Decode response safely
        $decoded = $res ?? null;

        if (is_string($decoded)) {
            $decoded = json_decode($decoded, true);
        }

 // ✅ Error handling
        // if (!is_array($decoded) || ($decoded['status'] ?? '') !== 'success') {
        //     return [
        //         'http_code' => 500,
        //         'body' => [
        //             'status'  => 'failed',
        //             'message' => $decoded['message'] ?? 'Xpaisa API failed',
        //             'raw'     => $res
        //         ]
        //     ];
        // }
        
        if (
            !is_array($decoded) ||
            ($decoded['statusCode'] ?? 0) != 200 ||
            ($decoded['data']['status'] ?? 0) != 200 ||
            strtoupper($decoded['data']['status_msg'] ?? '') !== 'SUCCESS'
        ) {
            return [
                'http_code' => 500,
                'body' => [
                    'status'  => 'failed',
                    'message' => $decoded['message'] ?? 'E2Pay API failed',
                    'raw'     => $res
                ]
            ];
        }
        
        $data = $decoded['data'] ?? [];

        // ✅ Save API response
        $report->update([
            'apitxnid' => $data['trxID'] ?? null, // FIXED (correct key)
            'option4'  => 'E2PAY',
        ]);
        
        return [
            'http_code' => 200,
            'body' => [
                'status' => $data['status_msg'] ?? 'SUCCESS',
                'data'   => [
                    'payment_url' => $data['qr'] ?? null,
                    'orderid'     => $payload['orderid'] ?? null,
                    'txnid'       => $report->txnid,
                ],
            ]
        ];

        // $data = $decoded['data'] ?? [];

        // // ✅ Save API response
        // $report->update([
        //     'apitxnid' => $data['client_transction_id'] ?? null,
        //     'option4'  => 'E2PAY',
        // ]);

        // // ✅ Final response
        // return [
        //     'http_code' => 200,
        //     'body' => [
        //         'status' => $decoded['status_msg'],
        //         'data'   => [
        //             'payment_url' => $data['qrString'] ?? null, // ✅ FIXED
        //             'orderid'     => $payload['orderid'] ?? null,
        //             'txnid'       => $report->txnid,
        //         ],
        //     ]
        // ];
    }
}