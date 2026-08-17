<?php

namespace App\Services\Payin\Providers;

use App\Models\User;
use App\Models\Report;
use App\Services\Payin\Contracts\PayinProviderInterface;
use App\Services\Payin\PayinService;



class BulkPeProvider implements PayinProviderInterface
{
    public function __construct(private PayinService $payinService) {}

    public function generateUpiQr(array $payload, User $user, Report $report): array
    {
        

        // Get credentials
        
        $url = "https://api.bulkpe.in/client/pg1UpiIntent";

        // ✅ Extract token properly
        $token = 'aWSVQNyt+z3IiJHV+YX9UnzVgAaqZeAgGbnhPBnfin7IDjWy9i6OSc4Kd1Zpmwdeb1RWzfVG2rIx3kSzNv9vlA==';

        // ✅ Request (FORM-DATA like your curl)
        $req = [
            'note'                  => 'TEST',
            'referenceId'           => $payload['orderid'] ?? '',
            'device'                => '',
            'amount'                => $payload['amount'] ?? 31,
            'device'                => 'M',
        ];

        // ✅ Headers EXACTLY like working curl
        $headers = [
            "token: Bearer $token",
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
        if (!is_array($decoded) || ($decoded['status'] ?? '') !== 'success') {
            return [
                'http_code' => 500,
                'body' => [
                    'status'  => 'failed',
                    'message' => $decoded['message'] ?? 'Xpaisa API failed',
                    'raw'     => $res
                ]
            ];
        }

        $data = $decoded['data'] ?? [];

        // ✅ Save API response
        $report->update([
            'apitxnid' => $data['client_transction_id'] ?? null,
            'option4'  => $req['merchant_id'],
        ]);

        // ✅ Final response
        return [
            'http_code' => 200,
            'body' => [
                'status' => $decoded['status'],
                'data'   => [
                    'payment_url' => $data['qrString'] ?? null, // ✅ FIXED
                    'orderid'     => $payload['orderid'] ?? null,
                    'txnid'       => $report->txnid,
                ],
            ]
        ];
    }
}