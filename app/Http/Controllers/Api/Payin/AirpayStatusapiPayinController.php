<?php

namespace App\Http\Controllers\Api\Payin;

use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use App\Models\Report;
use App\Models\User;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\DB;

class AirpayStatusapiPayinController extends Controller
{

    public function Airpay_payin_status(Request $request)
{

    //For single txn

    try {

        $request->validate([
            'ap_transactionid' => 'required'
        ]);

        $apTxnId = $request->ap_transactionid;

        Log::info("🔍 Checking Single TXN: " . $apTxnId);

        // Find report
        $report = Report::with('user')
            ->where('apitxnid', $apTxnId)
            ->first();
        // dd($report);

        if (!$report) {
            return response()->json([
                'status' => false,
                'message' => 'Transaction not found'
            ], 404);
        }

        // Call Airpay Status API
        // $response = Http::timeout(12)
        //     ->retry(2, 2000)
        //     ->get('https://omishajewels.com/Backend/api/checkstatus', [
        //         'ap_transactionid' => $apTxnId
        //     ]);
        
        $url = '';

        if ($report->remark === 'Omisha') {
            $url = 'https://omishajewels.com/Backend/api/checkstatus';
        } elseif ($report->remark === 'Ebook') {
            $url = 'https://ebookspay.co.in/dashboard/api/checkstatus';
        }
        
        $response = Http::timeout(12)
            ->retry(2, 2000)
            ->get($url, [
                'ap_transactionid' => $apTxnId
            ]);
            
            

        if (!$response->successful()) {
            return response()->json([
                'status' => false,
                'message' => 'Airpay API failed'
            ], 500);
        }

        $result = $response->json();

        Log::info("Airpay Response:", $result);

        $statusCode  = (int) ($result['status_code'] ?? 0);
        $txnStatus   = (int) ($result['data']['transaction_status'] ?? 0);
        $paymentFlag = strtolower(trim($result['data']['transaction_payment_status'] ?? ''));
        $utr         = $result['data']['rrn'] ?? null;
        $reason      = $result['data']['transaction_reason'] ?? null;

        $user = $report->user;

        if (!$user) {
            return response()->json([
                'status' => false,
                'message' => 'User not found'
            ], 404);
        }

        // ====================================
        // ✅ SUCCESS
        // ====================================
        if (
            $statusCode === 200 &&
            $txnStatus === 200 &&
            $paymentFlag === 'success' &&
            $utr
        ) {

            DB::transaction(function () use ($user, $report, $utr) {

                $user->rolling_amount += $report->payin_rolling_amount ?? 0;
                $user->payin_wallet   += $report->payin_amount ?? 0;
                $user->total_charges  += $report->profit ?? 0;
                $user->save();

                $report->status      = 'success';
                $report->description = 'Payment counted';
                $report->refno       = $utr;
                $report->option1     = 'Manual status check';
                $report->save();
            });

            $this->merchantCallback($user, 'SUCCESS', $report, $utr);

            return response()->json([
                'status' => true,
                'message' => 'Transaction Success & Updated',
                'data' => $result
            ]);
        }

        // ====================================
        // ❌ FAILED
        // ====================================
        if (in_array($statusCode, [400, 403])) {

            $report->status      = 'failed';
            $report->description = 'Payment Failed';
            $report->option1     = $reason;
            $report->save();

            return response()->json([
                'status' => true,
                'message' => 'Transaction Failed & Updated',
                'data' => $result
            ]);
        }

        // ====================================
        // ⏳ PENDING (503 / others)
        // ====================================
        if ($statusCode === 503) {

            return response()->json([
                'status' => true,
                'message' => 'Transaction Still Processing',
                'data' => $result
            ]);
        }

        return response()->json([
            'status' => false,
            'message' => 'Unknown status received',
            'data' => $result
        ]);

    } catch (\Exception $e) {

        Log::error("💥 Single TXN Error: " . $e->getMessage());

        return response()->json([
            'status' => false,
            'message' => 'Something went wrong'
        ], 500);
    }
}


public function All_payin_status(Request $request)
{
    try {

        Log::info("🚀 Airpay STATUS API STARTED at " . now());

        $successCount = 0;
        $failedCount  = 0;
        $totalChecked = 0;

        // $date   = $request->input('date');     // example: 2026-03-05
        $userId = $request->input('user_id');  // optional

        $query = Report::with('user')
            ->where('user_id', $userId)
            ->where('product', 'upi')
            ->where('description', 'Payment Failed (auto-timeout after 20 mins)')
            ->where('status', 'failed')
            ->orderBy('id', 'asc')

        // ✅ Filter by date
        // if ($date) {
        //     $query->whereDate('created_at', $date);
        // }

        // // ✅ Filter by user_id
        // if ($userId) {
        //     $query->where('user_id', $userId);
        // }

    
            ->chunkById(5, function ($reports) use (&$successCount, &$failedCount, &$totalChecked) {

                foreach ($reports as $report) {

                    $totalChecked++;

                    Log::info("Checking Report ID: " . $report->id);

                    $response = Http::timeout(12)
                        ->retry(2, 2000)
                        ->get('https://omishajewels.com/Backend/api/checkstatus', [
                            'ap_transactionid' => $report->apitxnid
                        ]);

                    if (!$response->successful()) {
                        Log::error("HTTP Failed for Report ID: " . $report->id);
                        continue;
                    }

                    $result = $response->json();

                    // your existing status logic
                    $statusCode   = (int) ($result['status_code'] ?? 0);
                    $utr          = $result['data']['rrn'] ?? null;
                    $reason       = $result['data']['transaction_reason'] ?? null;
                    $txnStatus    = (int) ($result['data']['transaction_status'] ?? 0);
                    $paymentFlag  = strtolower(trim($result['data']['transaction_payment_status'] ?? ''));

                    $user = $report->user;

                    if (!$user) {
                        Log::error("❌ User Not Found for Report ID: " . $report->id);
                        continue;
                    }

                    // ✅ SUCCESS
                    if (
                        $statusCode === 200 &&
                        $txnStatus === 200 &&
                        $paymentFlag === 'success'
                    ) {

                        $successCount++;

                        DB::transaction(function () use ($user, $report, $utr) {

                            $user->rolling_amount += $report->payin_rolling_amount ?? 0;
                            $user->payin_wallet   += $report->payin_amount ?? 0;
                            $user->total_charges  += $report->profit ?? 0;
                            $user->save();

                            $report->status      = 'success';
                            $report->description = 'Payment counted';
                            $report->refno       = $utr;
                            $report->option1     = 'status Api Run';
                            $report->save();
                        });

                        Log::info("✅ SUCCESS - Report ID: " . $report->id);

                        $this->merchantCallback($user, 'SUCCESS', $report, $utr);

                        continue;
                    }

                    // ❌ FAILED
                    if (in_array($statusCode, [400, 403, 503])) {

                        $failedCount++;

                        $report->status      = 'failed';
                        $report->description = 'Payment Failed by Status API';
                        $report->option1     = $reason;
                        $report->save();

                        Log::info("❌ FAILED - Report ID: " . $report->id);

                        continue;
                    }

                    Log::warning("⚠️ Unknown Status for Report ID: " . $report->id);
                }


            });

        return response()->json([
            'status' => true,
            'message' => 'Status check completed',
            // 'date' => $date,
            'user_id' => $userId,
            'success_count' => $successCount,
            'failed_count' => $failedCount,
            'total_checked' => $totalChecked
        ]);

    } catch (\Exception $e) {

        Log::error("💥 Fatal Error: " . $e->getMessage());

        return response()->json([
            'status' => false,
            'message' => 'Something went wrong'
        ], 500);
    }
}







    // ======================================
    // Merchant Callback Function
    // ======================================
    private function merchantCallback($user, $status, $report, $utr = null)
    {
        try {

            if (empty($user->callback_url)) {
                Log::warning("⚠️ Callback URL Missing for User ID: " . $user->id);
                return;
            }

            $payload = [
                'orderid'     => $report->orderid,
                'status'      => $status,
                'amount'      => $report->amount,
                'utr'         => $utr,
                'transaction' => $report->apitxnid
            ];

            Http::timeout(10)->post($user->callback_url, $payload);

            Log::info("📤 Callback Sent for Report ID: " . $report->id);

        } catch (\Exception $e) {
            Log::error("💥 Callback Failed: " . $e->getMessage());
        }
    }

}