<?php

namespace App\Services\Payin\Providers;

use App\Models\User;
use App\Models\Report;
use App\Services\Payin\Contracts\PayinProviderInterface;
use App\Services\Payin\PayinService;

class VRazorProvider implements PayinProviderInterface
{
    public function __construct(
        private PayinService $payinService
    ) {
    }

    public function generateUpiQr(
        array $payload,
        User $user,
        Report $report
    ): array {

        $url = "https://insurance.spay.live/Backend/laravel_project/public/api/Vcreate-order";

        $req = [
            'amount' => $payload['amount'],
            'name'   => $payload['name'] ?? ($payload['customer_name'] ?? ''),
            'email'  => $payload['email'] ?? '',
            'phone'  => $payload['phone'] ?? ($payload['mobile'] ?? ''),
        ];
        
        $response = $this->payinService->commonCurl(
            $url,
            $req,
            
                
                [
    "Accept: application/json",
    "Content-Type: application/json"
]
            
        );

        if (
            !isset($response['success']) ||
            $response['success'] !== true ||
            !isset($response['order_id'])
        ) {

            return [
                'http_code' => 500,
                'body' => [
                    'status' => 'failed',
                    'message' => 'Unable to create Razorpay order',
                    'raw' => $response
                ]
            ];
        }

        $report->update([
            'apitxnid' => $response['order_id'] ?? null,
        ]);

        return [
            'http_code' => 200,
            'body' => [
                'status' => 'success',
                'payment_type' => 'razorpay',
                'data' => [
                    'order_id' => $response['order_id'],
                    'amount' => $response['amount'],
                    'payment_url' => $response['payment_url'],
                    'merchantOrderId' => $payload['orderid'] ?? null,
                ]
            ]
        ];
    }
}