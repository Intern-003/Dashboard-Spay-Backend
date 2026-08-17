<?php

namespace App\Http\Controllers\Api\Payout;

use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use App\Models\Report;
use App\Models\User;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\DB;

class E2payStatusapiPayoutController extends Controller
{
    
    public function E2pay_payout_status(Request $request)
    {
        try {

            Log::info("🚀 E2PAY STATUS API STARTED at " . now());

            $reports = Report::with('user')
                ->where('product', 'payout')
                ->where('status', 'pending')
                ->get();

            if ($reports->isEmpty()) {
                Log::info("❗ No Pending Reports Found");
            }

            foreach ($reports as $report) {

                Log::info("🧾 Checking Report ID: " . $report->id);

                $response = Http::withHeaders([
                'Token' => 'k9njUwyaPf4RXyNPmaQVF6SWPIDwz5nO',
                'Content-Type' => 'application/json'
                ])->post(
                    'https://marketingllp.in/api/v1/Payout/CheckStatus',
                    [
                        'orderId' => $report->apitxnid
                    ]
                );

                if (!$response->successful()) {
                    Log::error("❌ HTTP Failed for Report " . $report->id);
                    continue;
                }

                $result = $response->json();

                Log::info("E2PAY RESPONSE:", $result);

                $statusCode = $result['statusCode'] ?? '';
                $message    = strtolower($result['message'] ?? '');
                $utr        = $result['utr'] ?? '';

                $user = $report->user;

                if (!$user) {
                    Log::error("❌ User Not Found for Report " . $report->id);
                    continue;
                }

                // ==============================
                // SUCCESS
                // ==============================
                if ($statusCode == 200) {

                    $report->status = 'success';
                    $report->description = 'Payment Success';
                    $report->refno = $utr;
                    // $report->payid = $utr;
                    $report->save();

                    Log::info("✅ SUCCESS Report " . $report->id);

                    $this->merchantCallback($user, 'SUCCESS', $report, $utr);

                    continue;
                }

                // ==============================
                // FAILED
                // ==============================
                if ($statusCode == 400) {

                    $refundAmount = $report->amount + $report->charge;

                    $user->payout_wallet += $refundAmount;
                    $user->save();

                    $report->status = 'refunded';
                    $report->description = 'Payment Failed';
                    $report->remark = "Refunded ₹{$refundAmount} to wallet";
                    $report->save();

                    Log::info("❌ FAILED & REFUNDED Report " . $report->id);

                    $this->merchantCallback($user, 'FAILED', $report, null);

                    continue;
                }

                // ==============================
                // STILL PENDING
                // ==============================
                if ($statusCode == 202) {
                    Log::info("⏳ STILL PENDING Report " . $report->id);
                    continue;
                }

                Log::warning("⚠️ Unknown Status for Report " . $report->id);
            }

            Log::info("🏁 E2PAY STATUS API COMPLETED at " . now());

            return response()->json([
                'status' => true,
                'message' => 'Status check completed'
            ]);

        } catch (\Exception $e) {

            Log::error("💥 Fatal Error: " . $e->getMessage());

            return response()->json([
                'status' => false,
                'message' => 'Something went wrong'
            ], 500);
        }
    }

    /*
    |--------------------------------------------------------------------------
    | Merchant Callback Function
    |--------------------------------------------------------------------------
    */
    private function merchantCallback($user, $status, $report, $utr = null)
    {
        if (empty($user->payout_callback)) {
            return;
        }

        try {

            Http::timeout(15)->post($user->payout_callback, [
                'status'     => $status,
                'txnid'      => $report->txnid,
                'orderId'    => $report->mytxnid,
                'amount'     => $report->amount,
                'utr'        => $utr,
                'updated_at' => now()->format('Y-m-d H:i:s')
            ]);

            Log::info("📡 Merchant Callback Sent for Report {$report->id}");

        } catch (\Exception $e) {
            Log::error("❌ Merchant Callback Failed {$report->id}: " . $e->getMessage());
        }
    }
}