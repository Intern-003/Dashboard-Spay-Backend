<?php


namespace App\Services\Payin\Providers;

use App\Models\User;
use App\Models\Report;
use App\Services\Payin\Contracts\PayinProviderInterface;
use App\Services\Payin\PayinService;


class ShaymavenueProvider implements PayinProviderInterface
{
    public function __construct(private PayinService $payinService) {}

    public function generateUpiQr(array $payload, User $user, Report $report): array
    {
        // dd("Shaymavenue");
        
        $url = "https://shaymavenue.in/api/v1/create_order";
        
        $req = [
            'mid'             => 'SHYAM5184179346',
            'apikey'          => 'Q8@vL2#Rx7!Mp4$Zk',
            'client_txn_id'   => $payload['orderid'],
            'amount'          => $payload['amount'],
            'customer_mobile' => $payload['phone'],
        ];
            
        $res = $this->payinService->commonCurl($url, $req, ['Content-Type: application/json'],true);

        $decoded = $res;
        
        // dd($decoded);
        
        if (($decoded['status'] ?? '') !== 'True') {
            return [
                'http_code' => 500,
                'body' => [
                    'status'  => 'failed',
                    'message' => $decoded['msg'] ?? 'QR API did not return success',
                    'raw'     => $decoded,
                ],
            ];
        }


        $report->update([
            'apitxnid' => $decoded['client_txn_id'] ?? null,
            'option3'  => $decoded['upi_string'] ?? null,
        ]);
        

        return [
            'http_code' => 200,
            'body' => [
                'status' => $decoded['status'],
                'message' => $decoded['msg'] ?? null,
                'data' => [
                    'upi_string'   => preg_replace('/\s+/', '', $decoded['payment_url'] ?? ''),
                    'orderid'      => $payload['orderid'],
                    'txnid'        => $report->txnid,
                ],
            ],
        ];
    }
}