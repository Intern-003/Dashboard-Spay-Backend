<?php

namespace App\Http\Controllers\Api\Payout;

use App\Http\Controllers\Controller;
use App\Models\Report;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

   
class BridgStatusPayoutController extends Controller
{
   
    private function payoutLog($message, $data = null)
    {
        $logPath = storage_path('logs/status/payout');

        if (!file_exists($logPath)) {
            mkdir($logPath, 0777, true);
        }

        $file = $logPath . '/status_' . date('Y-m-d') . '.log';

        $text = "[" . date('Y-m-d H:i:s') . "] " . $message;

        if (!is_null($data)) {
            if (is_array($data) || is_object($data)) {
                $text .= "\n" . json_encode($data, JSON_PRETTY_PRINT);
            } else {
                $text .= " " . $data;
            }
        }

        $text .= "\n\n";

        file_put_contents($file, $text, FILE_APPEND);
    }

    public function bridgMoneyPayoutStatus()
    {
        try {

            $this->payoutLog("========== BRIDG MONEY STATUS API STARTED ==========");

            $reports = Report::with('user')
                ->where('product', 'payout')
                ->where('status', 'pending')
                ->where('remark', 'Bridg Money')
                ->get();

            if ($reports->isEmpty()) {

                $this->payoutLog("No Pending Reports Found");

                return response()->json([
                    'status' => true,
                    'message' => 'No pending reports found'
                ]);
            }

            foreach ($reports as $report) {

                try {

                    $this->payoutLog("Checking Report ID: {$report->mytxnid}");

                    $response = Http::timeout(30)
                        ->withHeaders([
                            'Content-Type' => 'application/json'
                        ])
                        ->post(
                            'https://spay.live/spayliveBackend/api/BM/payout/status',
                            [
                                'payoutTransactionId' => $report->payid
                            ]
                        );

                    if (!$response->successful()) {

                        $this->payoutLog(
                            "Status API Failed For Report {$report->mytxnid}",
                            [
                                'http_code' => $response->status(),
                                'response' => $response->body()
                            ]
                        );

                        continue;
                    }

                    $result = $response->json();

                    $this->payoutLog(
                        "BRIDG RESPONSE Report {$report->payid}",
                        $result
                    );

                    $bridgStatus = $result['response']['data'][0]['status'] ?? null;

                    $utr = $result['response']['data'][0]['transactionReference'] ?? null;

                    $responseMessage =
                        $result['response']['data'][0]['responseMessage']
                        ?? 'No Response';
                        
                    $txnid = $result['response']['data'][0]['transactionId'] ?? null;
                    
                    $timestamp = $result['response']['data'][0]['updatedDate'] ?? null;

                    $user = $report->user;

                    if (!$user) {

                        $this->payoutLog(
                            "User Not Found For Report {$report->mytxnid}"
                        );

                        continue;
                    }

                    /*
                    |--------------------------------------------------------------------------
                    | SUCCESS
                    |--------------------------------------------------------------------------
                    */
                    if ($bridgStatus == 11) {

                        $report->status  = 'success';
                        $report->option3 = $responseMessage;
                        $report->option1 = 'BridgMoney Success By Status API';
                        $report->refno   = $utr;
                        $report->save();


                        $this->payoutLog(
                            "SUCCESS Report {$report->mytxnid}",
                            [
                                'utr' => $utr,
                                'message' => $responseMessage
                            ]
                        );

                        
                        
                        $this->merchantCallback(
                            $user->payout_callback,
                            'SUCCESS',
                            $txnid,
                            $report->mytxnid,
                            $report->amount,
                            $utr,
                            $timestamp
                        );

                        continue;
                    }

                    /*
                    |--------------------------------------------------------------------------
                    | FAILED
                    |--------------------------------------------------------------------------
                    */
                    
                    if ($bridgStatus == 12) {
                    
                        $refundAmount = 0;
                        $alreadyProcessed = in_array($report->option1, [
                            'BridgMoney Failed By Status API',
                            // 'BridgMoney Success By Status API',
                            'BridgMoney Failed',
                            // 'BridgMoney Success'
                        ]);
                    
                        if (!$alreadyProcessed) {
                    
                            $refundAmount = $report->amount + $report->charge;
                    
                            $user->increment('payout_wallet', $refundAmount);
                    
                            $report->option1 = 'BridgMoney Failed By Status API';
                        }
                    
                        $report->status = 'refunded';
                        $report->option3 = $responseMessage;
                        $report->save();
                    
                        $this->payoutLog(
                            $alreadyProcessed
                                ? "FAILED REPORT ALREADY REFUNDED"
                                : "FAILED & REFUNDED Report {$report->mytxnid}",
                            [
                                'refund_amount' => $refundAmount,
                                'message' => $responseMessage
                            ]
                        );
                    
                        $this->merchantCallback(
                            $user->payout_callback,
                            'FAILED',
                            $txnid,
                            $report->mytxnid,
                            $report->amount,
                            'null',
                            $timestamp
                        );
                        
                    
                        continue;
                    }
                    
                    // if ($bridgStatus == 12) {

                    //     $refundAmount = $report->amount + $report->charge;

                    //     $user->payout_wallet += $refundAmount;
                    //     $user->save();

                    //     $report->status  = 'refunded';
                    //     $report->option3 = $responseMessage;
                    //     $report->option1 = 'BridgMoney Failed By Status API';
                    //     // $report->description  = "Refunded ₹{$refundAmount} to wallet";
                    //     $report->save();

                    //     $this->payoutLog(
                    //         "FAILED & REFUNDED Report {$report->mytxnid}",
                    //         [
                    //             'refund_amount' => $refundAmount,
                    //             'message' => $responseMessage
                    //         ]
                    //     );

                    //     $this->merchantCallback(
                    //         $user,
                    //         'FAILED',
                    //         $report,
                    //         null
                    //     );

                    //     continue;
                    // }

                    /*
                    |--------------------------------------------------------------------------
                    | PENDING
                    |--------------------------------------------------------------------------
                    */
                    if (in_array($bridgStatus, [2, 3, 4])) {

                        $this->payoutLog(
                            "STILL PENDING Report {$report->mytxnid}",
                            [
                                'status' => $bridgStatus,
                                'message' => $responseMessage
                            ]
                        );

                        continue;
                    }

                    /*
                    |--------------------------------------------------------------------------
                    | UNKNOWN STATUS
                    |--------------------------------------------------------------------------
                    */
                    $this->payoutLog(
                        "Unknown Status For Report {$report->mytxnid}",
                        [
                            'status' => $bridgStatus,
                            'response' => $result
                        ]
                    );

                } catch (\Exception $e) {

                    $this->payoutLog(
                        "Error Processing Report {$report->mytxnid}",
                        [
                            'message' => $e->getMessage(),
                            'line' => $e->getLine(),
                            'file' => $e->getFile()
                        ]
                    );
                }
            }

            $this->payoutLog("=====/////===== BRIDG MONEY STATUS END =====/////=====");

            return response()->json([
                'status' => true,
                'message' => 'Status check completed'
            ]);

        } catch (\Exception $e) {

            $this->payoutLog(
                "FATAL ERROR",
                [
                    'message' => $e->getMessage(),
                    'line' => $e->getLine(),
                    'file' => $e->getFile()
                ]
            );

            return response()->json([
                'status' => false,
                'message' => $e->getMessage()
            ], 500);
        }
    }
    
     /*
    |--------------------------------------------------------------------------
    | Merchant Callback
    |--------------------------------------------------------------------------
    */
    public function merchantCallback(
      $callbackurl,
      $status,
      $txnid,
      $mytxnid,
      $amount,
      $referenceId,
      $timestamp
    ) {
    
        $postData = [
            'status'      => $status,
            'txnid'       => $txnid,
            'clienttxnid' => $mytxnid,
            'amount'      => $amount,
            'UTR'         => $referenceId,
            'timestamp'   => $timestamp,
        ];
    
        if (empty($callbackurl)) {
    
            $this->payoutLog("Callback URL Missing", [
                'clienttxnid' => $mytxnid
            ]);
    
            return;
        }
    
        // Log request
        $this->payoutLog("Sending Merchant Callback", [
            'url'     => $callbackurl,
            'payload' => $postData
        ]);
    
        $jsonPayload = json_encode(
            $postData,
            JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE
        );
    
        $ch = curl_init($callbackurl);
    
        curl_setopt_array($ch, [
            CURLOPT_POST           => true,
            CURLOPT_POSTFIELDS     => $jsonPayload,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_TIMEOUT        => 15,
            CURLOPT_HTTPHEADER     => [
                'Content-Type: application/json',
                'Accept: application/json',
                'Content-Length: ' . strlen($jsonPayload),
            ],
        ]);
    
        $response = curl_exec($ch);
        
        $this->payoutLog("Raw Callback Response", [
            'response' => $response
        ]);
    
        $httpCode    = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $contentType = curl_getinfo($ch, CURLINFO_CONTENT_TYPE);
    
        if (curl_errno($ch)) {
    
            $this->payoutLog("Merchant Callback CURL Error", [
                'error' => curl_error($ch)
            ]);
    
        } else {
    
            $this->payoutLog("Merchant Callback Result", [
                'http_code'    => $httpCode,
                'content_type' => $contentType,
                'request_json' => $postData,
                'response'     => $response ?: 'EMPTY RESPONSE'
            ]);
        }
    
        curl_close($ch);
    }

    
}