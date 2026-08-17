<?php
namespace App\Http\Controllers\Api\Payin;

use App\Http\Controllers\Controller;
use App\Models\Report;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class RazorpayStatusPayinController extends Controller
{
    
    private function payinLog($message, $data = null)
    {
        $logPath = storage_path('logs/status/payin');

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
    
    // public function razorpayPayinStatus(Request $request)
    // {
    //     $this->payinLog("========== RAZORPAY STATUS API STARTED ==========");
    //     $request->validate([
    //         'order_id' => 'required|string'
    //     ]);
        
    //     $this->payinLog("Checking Report ID: {$request->order_id}");
    
    //     try {
    
    //         $response = Http::asMultipart()
    //             ->post(
    //                 'https://insurance.spay.live/Backend/laravel_project/public/api/razorpay/status',
    //                 [
    //                     [
    //                         'name'     => 'order_id',
    //                         'contents' => $request->order_id
    //                     ]
    //                 ]
    //             );

                        
    //         $this->payinLog(
    //             "Status API Response {$request->order_id}",
    //             [
    //                 'http_code' => $response->status(),
    //                 'response' => $response->body()
    //             ]
    //         );
            
    //         return response()->json(
    //             $response->json(),
    //             $response->status()
    //         );

    
    //     } catch (\Exception $e) {
    
    //         return response()->json([
    //             'status' => false,
    //             'message' => $e->getMessage()
    //         ], 500);
    //     }
    // }
    
    public function razorpayPayinStatus()
    {
        try {

            $this->payinLog("========== RAZORPAY STATUS API STARTED ==========");
    
            $reports = Report::with('user')
                ->where('product', 'UPI')
                ->where('status', 'initiated')
                ->where('remark', 'Razorpay')
                ->get();

            if ($reports->isEmpty()) {
    
                $this->payinLog("No initiated Reports Found");
    
                return response()->json([
                    'status' => true,
                    'message' => 'No initiated reports found'
                ]);
            }
    
            foreach ($reports as $report) {
    
                try {
                    
                    $this->payinLog("Checking Report ID: {$report->mytxnid}");
    
                    $response = Http::asMultipart()
                        ->timeout(30)
                        ->post(
                            'https://insurance.spay.live/Backend/laravel_project/public/api/razorpay/status',
                            [
                                [
                                    'name' => 'order_id',
                                    'contents' => $report->apitxnid
                                ]
                            ]
                        );
    
                    // if (!$response->successful()) {
    
                    //     $this->payinLog(
                    //         "Status API Failed",
                    //         [
                    //             'report' => $report->mytxnid,
                    //             'response' => $response->body()
                    //         ]
                    //     );
    
                    //     continue;
                    // }
    
                    $result = $response->json();
                    $this->payinLog("Status Response",
                            [
                                'report' => $report->apitxnid,
                                'response' => $response->body()
                            ]
                        );

                    $status = strtolower(
                        $result['payment_status'] ?? ''
                    );
    
                    $txnid = $result['payment_id'] ?? null;
    
                    $utr = $result['data']['acquirer_data']['rrn'] ?? null;
    
                    $timestamp = isset($result['data']['created_at'])
                        ? date(
                            'Y-m-d H:i:s',
                            $result['data']['created_at']
                        )
                        : now()->toDateTimeString();
    
                    $message = $result['payment_status'] ?? 'No Response';
    
                    $user = $report->user;
    
                    if (!$user) {
                        continue;
                    }
    
                    /*
                    |--------------------------------------------------------------------------
                    | SUCCESS
                    |--------------------------------------------------------------------------
                    */
    
                    // if ($status == 'captured') {
    
                    //     $report->status = 'success';
                    //     $report->option1 = 'Razorpay Success By Status API';
                    //     $report->option3 = $message;
                    //     $report->refno = $utr;
                    //     $report->save();
                        
                    //     $this->payinLog("RAZORPAY DB UPDATES SUCCESS");
    
                    //     $this->merchantCallback(
                    //         $user->payin_callback,
                    //         'SUCCESS',
                    //         $txnid,
                    //         $report->mytxnid,
                    //         $report->amount,
                    //         $utr,
                    //         $timestamp
                    //     );
    
                    //     continue;
                    // }
                    
                    if ($status == 'captured') {

                        // Prevent duplicate wallet credit
                        if ($report->description !== 'Payment counted') {
                    
                            $this->payinLog('Wallet credit started from Status API', [
                                'user_id'   => $user->id,
                                'report_id' => $report->id,
                                'amount'    => $report->amount
                            ]);
                    
                            // Credit wallet
                            // $user->rolling_amount = ($user->rolling_amount ?? 0) + ($report->payin_rolling_amount ?? 0);
                            // $user->payin_wallet   = ($user->payin_wallet ?? 0) + ($report->payin_amount ?? 0);
                            // $user->total_charges  = ($user->total_charges ?? 0) + ($report->profit ?? 0);
                            // $user->save();
                            
                            $user->increment( 'rolling_amount', $report->payin_rolling_amount ?? 0 );
                            $user->increment( 'payin_wallet', $report->payin_amount ?? 0 );
                            $user->increment( 'total_charges', $report->profit ?? 0 );
                    
                            $this->payinLog('Wallet credit completed', [
                                'rolling_amount' => $user->rolling_amount,
                                'payin_wallet'   => $user->payin_wallet,
                                'total_charges'  => $user->total_charges,
                            ]);
                        }
                    
                        $report->status      = 'success';
                        $report->option1     = 'Razorpay Success By Status API';
                        $report->option3     = $message;
                        $report->refno       = $utr;
                        $report->description = 'Payment counted';
                        $report->save();
                    
                        $this->payinLog("RAZORPAY DB UPDATES SUCCESS");
                    
                        $this->merchantCallback(
                            $user->payin_callback,
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
    
                    if (in_array($status, ['failed', 'cancelled'])) {
    
                        $report->status = 'failed';
                        $report->option1 = 'Razorpay Failed By Status API';
                        $report->option3 = $message;
                        $report->save();
                        
                        $this->payinLog("RAZORPAY DB UPDATES FAILED");
    
                        $this->merchantCallback(
                            $user->payin_callback,
                            'FAILED',
                            $txnid,
                            $report->mytxnid,
                            $report->amount,
                            null,
                            $timestamp
                        );
    
                        continue;
                    }
    
                    /*
                    |--------------------------------------------------------------------------
                    | PENDING
                    |--------------------------------------------------------------------------
                    */
    
                    if (in_array($status, [
                        'created',
                        'authorized',
                        'pending'
                    ])) {
    
                        continue;
                    }
    
                } catch (\Exception $e) {
    
                    $this->payinLog(
                        "Error Processing Report {$report->mytxnid}",
                        [
                            'message' => $e->getMessage()
                        ]
                    );
                }
            }
    
            return response()->json([
                'status' => true,
                'message' => 'Status check completed'
            ]);
    
        } catch (\Exception $e) {
    
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
        $utr,
        $timestamp
    ) {

        if (empty($callbackurl)) {
            return;
        }

        $payload = [
            'status'      => $status,
            'txnid'       => $txnid,
            'clienttxnid' => $mytxnid,
            'amount'      => $amount,
            'utr'         => $utr,
            'timestamp'   => $timestamp,
        ];

        try {

            $response = Http::timeout(15)
                ->acceptJson()
                ->post($callbackurl, $payload);

            $this->payinLog(
                "Merchant Callback",
                [
                    'url' => $callbackurl,
                    'payload' => $payload,
                    'response' => $response->body()
                ]
            );

        } catch (\Exception $e) {

            $this->payinLog(
                "Callback Failed",
                [
                    'message' => $e->getMessage()
                ]
            );
        }
    }
    
    
    
}

?>



