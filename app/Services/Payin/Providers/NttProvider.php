<?php

namespace App\Services\Payin\Providers;

use App\Models\Report;
use App\Models\User;
use App\Services\Payin\Contracts\PayinProviderInterface;
use App\Services\Payin\PayinService;

class NttProvider implements PayinProviderInterface
{
    public function __construct(
        private PayinService $payinService
    ) {}

    public function generateUpiQr(
        array $payload,
        User $user,
        Report $report
    ): array {
        


        $url = 'https://dashboardbbps.spay.live/backend/api/ntt/payin';

        // dd($url);

        // $req = [
        //     'amount' => $payload['amount'],
        //     'email' => $payload['email'],
        //     'mobile' => $payload['phone'],
        //     'name' => $user->name ?? 'Customer',
        // ];
        
        $req = [
            'amount' => $payload['amount'],
            'email' => $payload['email'],
            'mobile' => $payload['phone'],
            'name' => $user->name ?? 'Customer',

            // IMPORTANT
            'orderid' => $report->mytxnid,
            'txnid' => $report->txnid,
        ];

        $decoded = $this->payinService->commonCurl(
            $url,
            $req,
            ['Content-Type: application/json']
        );
        
        if (
            ! is_array($decoded) ||
            ! ($decoded['status'] ?? false)
        ) {

            return [
                'http_code' => 500,
                'body' => [
                    'status' => 'failed',
                    'message' => 'NTT API did not return success',
                    'raw' => $decoded,
                ],
            ];
        }

        $report->update([
            'apitxnid' => $decoded['atomTokenId'] ?? null,
            'refno' => $decoded['txn_id'] ?? null,
            'option4' => $decoded['merchId'] ?? null,
        ]);

        return [
            'http_code' => 200,
            'body' => [
                'status' => 'success',
                'data' => [
                    'txn_id' => $decoded['txn_id'] ?? null,
                    'atomTokenId' => $decoded['atomTokenId'] ?? null,
                    'merchId' => $decoded['merchId'] ?? null,
                    'orderid' => $payload['orderid'] ?? null,
                    'txnid' => $report->txnid,
                ],
            ],
        ];
    }
}

// {
//     public function __construct(
//         private PayinService $payinService
//     ) {}

//     public function generateUpiQr(
//         array $payload,
//         User $user,
//         Report $report
//     ): array {

//         $url = 'https://dashboardbbps.spay.live/backend/api/ntt/payin';

//         $req = [
//             'amount' => $payload['amount'],
//             'email' => $payload['email'],
//             'mobile' => $payload['phone'],
//             'name' => $user->name ?? 'Customer',
//         ];

//         $res = $this->payinService->commonCurl(
//             $url,
//             $req,
//             ['Content-Type: application/json']
//         );

//         dd($res);
//     }
// }
