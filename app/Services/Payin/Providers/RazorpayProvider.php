<?php

namespace App\Services\Payin\Providers;

use App\Models\Report;
use App\Models\User;
use App\Services\Payin\Contracts\PayinProviderInterface;
use App\Services\Payin\PayinService;

class RazorpayProvider implements PayinProviderInterface
{
    public function __construct(
        private PayinService $payinService
    ) {}

    public function generateUpiQr(
        array $payload,
        User $user,
        Report $report
    ): array {

        $url = 'https://insurance.spay.live/Backend/laravel_project/public/api/create-order';

        $req = [
            'amount' => $payload['amount'],
            'email' => $payload['email'],
            'phone' => $payload['phone'],
            'name' => $user->name ?? 'Customer',

            // Internal Tracking
            'orderid' => $report->mytxnid,
            'txnid' => $report->txnid,
            'userid' => $user->id,
        ];

        $decoded = $this->payinService->commonCurl(
            $url,
            $req,
            ['Content-Type: application/json']
        );


        if (
            ! is_array($decoded) ||
            ! ($decoded['success'] ?? false)
        ) {

            return [
                'http_code' => 500,
                'body' => [
                    'status' => 'failed',
                    'message' => 'Razorpay Order Creation Failed',
                    'raw' => $decoded,
                ],
            ];
        }

        $report->update([
            'apitxnid' => $decoded['order_id'] ?? null,
        ]);

        return [
            'http_code' => 200,
            'body' => [
                'status' => 'success',
                'data' => [
                    'order_id' => $decoded['order_id'] ?? null,
                    'orderid' => $report->mytxnid,
                    'txnid' => $report->txnid,
                    'amount' => $payload['amount'],
                    'email' => $payload['email'],
                    'phone' => $payload['phone'],
                    'name' => $user->name ?? 'Customer',

                    'payment_url' => $decoded['payment_url'] ?? null,
                ],
            ],
        ];
    }
}
