<?php

namespace App\Services\Payin\Providers;

use App\Models\User;
use App\Models\Report;
use App\Services\Payin\Contracts\PayinProviderInterface;
use App\Services\Payin\PayinService;

class PaytmProvider implements PayinProviderInterface
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

        $url = "https://ebookspay.co.in/ebookspayBackend/api/paytm/create-token";


        $req = [
            //'orderId' => $report->mytxnid,
            'orderId' => $payload['orderid'],
            'amount' => $payload['amount'],
            'email' => $payload['email'],
            'mobile' => $payload['phone'],
            'callback_url' => $payload['redirect_url']
        ];

        $response = $this->payinService->commonCurl(
            $url,
            $req,
            [
                "Content-Type: application/json"
            ]
        );

        if (
            !isset($response['txnToken'])
        ) {

            return [
                'http_code' => 500,
                'body' => [
                    'status' => 'failed',
                    'message' => 'Unable to create Paytm order',
                    'raw' => $response
                ]
            ];
        }

        // $txnToken = $response['body']['txnToken'];
        $txnToken = $response['txnToken'] ?? null;

        $report->update([
            // 'mytxnid' => $response['orderId'] ?? null,
            'apitxnid' => $response['orderId'] ?? null,

        ]);

        return [
            'http_code' => 200,
            'body' => [
                'status' => 'success',
                'payment_type' => 'paytm',
                'data' => [
                    'mid' => env('PAYTM_MID'),
                    'orderId' => $response['orderId'] ?? $report->apitxnid,
                    'txnToken' => $txnToken,
                    'amount' => $payload['amount'],
                    //'payment_url' => url('/paytm/checkout/' . $response['orderId']),
                    'payment_url'=>$response['payment_url'],
                    'merchantOrderId' => $payload['orderid'],
                ]
                
            ]
        ];
    }
}