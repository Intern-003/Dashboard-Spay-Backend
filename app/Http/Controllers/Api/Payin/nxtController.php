<?php

namespace App\Http\Controllers\Api\Payin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\User;
use App\Models\Credential;
use App\Models\Scheme;
use App\Models\AuthToken;
use App\Models\Report;
use Validator;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Http;


class nxtController extends Controller
{
    
     function timestamp(){
        //  $timestamp = time();
                 $timestamp = $_SERVER['HTTP_TIMESTAMP'];

         return $timestamp;
     }
     

     public function uniqueid(){
         return time() . rand(100, 999);
     }
    public function nxt_create($request, $apiToken)
    {
        $transactionAmount = $request->amount;
        $user = User::find($apiToken->user_id);

        $schemeInfo = Scheme::where('id', $user->scheme_id)
            ->where('status', true)
            ->first();

        if (!$schemeInfo) {
            // dd("scheme has not been set");
             return response()->json([
                 'status' => 'failed',
                 'message' => 'Scheme not defined for this user',
             ], 400);
        }

        // Commission Calculation
        $payinCommissionType   = $schemeInfo->payin_commision_type;
        $payinCommissionAmount = $schemeInfo->payin_commision_amount;
        
        // $Set_GST = $schemeInfo->gst_amount;
        // dd($Set_GST);

        $calculatedCommission = 0;
        if ($payinCommissionType === 'percent') {
            $calculatedCommission = ($transactionAmount * $payinCommissionAmount) / 100;
        } elseif ($payinCommissionType === 'flat') {
            $calculatedCommission = $payinCommissionAmount;
        }

        // GST on commission
        $gst = ($calculatedCommission * 18) / 100;

        // Rolling Charge
        $rollingPayinAmount = $schemeInfo->rolling_payin_amount;
        $rollingFixedAmount = $schemeInfo->rolling_fixed_amount;

        $rollingCharge = 0;
        $rolling_amount = 0;

        if (!empty($rollingPayinAmount)) {
            $rollingCharge = ($transactionAmount * $rollingPayinAmount) / 100;
            $rolling_amount = $rollingCharge;
        } elseif (!empty($rollingFixedAmount)) {
            $rollingCharge = 0;
            $rolling_amount = $rollingFixedAmount;
        }

        $totalCommissionWithGst = $calculatedCommission + $gst;
        $remainingAmount = $transactionAmount - ($totalCommissionWithGst + $rollingCharge);
        
        
        if ($user->payin_at_onboard == 'Airpay_all') {
          $activeMid = $this->getActiveMid($request->amount);
                //   dd($activeMid);
                 if (!$activeMid) {
                       return response()->json([
                            'statuscode' => 400,
                             'message'    => 'All MIDs have reached their limits',
                        ]);
                 }        
        }
        $orderId = 'SPAY' . now()->format('YmdHis') . rand(11111111, 99999999);

        $data = [
            "gst"                  => $gst,
            "charge"               => $calculatedCommission,
            "mobile"               => $request->phone,
            "txnid"                => $orderId,
            "payid"                => $orderId,
            "mytxnid"              => $request->orderid,
            "amount"               => $transactionAmount,
            "user_id"              => $apiToken->user_id,
            "profit"               => $totalCommissionWithGst,
            "payin_amount"         => $remainingAmount,
            "payin_rolling_amount" => $rolling_amount,
            "transaction_type"     => "credit",
            "status"               => "initiated",
            "remark"               => "airpay",
            "product"              => "UPI",
            "payment_platform"     => "portal",
            "description"          => "Payment initiated",
            "payer_email"          => $request->email,
            "option1"              =>'payin calculation is pending',
            "option4"              => $activeMid['credentials']['merchant_id'] ?? $description['merchant_id'] ?? null
         ]; 
        //  dd($data);

        return Report::create($data);
    }
    public function nxt_intent(Request $request)
{
    // dd("hello");
    // 1️⃣ Server time (ONCE)
    $timestamp = time();
     
    // 2️⃣ Request body (ARRAY)
    $bodyArray = [
        "amount" => 100,
        "transaction_id" => $request->orderid,
        "account_holder_name" =>  "test",
        "account_number" => "9448272727",
        "ifsc_code" => "KKBK0005333",
        "mode"=> "IMPS",
        "mobile" => "9876543210",
        "remarks" => "Test transaction",
        "latitude" => "28.704",
        "longitude" => "77.1025",
        "purpose" => "Product Purchase",
    ];

    // 3️⃣ Convert to COMPACT JSON
    $body = json_encode($bodyArray, JSON_UNESCAPED_SLASHES);

    // 4️⃣ Build payload for signature
    $method = "POST";
    $endpoint = "/api/v1/payout/initiate";
    $secretKey = "sk_b2f5d411f8e62d2146bf9b23e72418ab9a5812d597ded6498635db7504da630a";

    $payload = $method . "|" . $endpoint . "|" . $timestamp . "|" . $body;

    // 5️⃣ Generate signature
    $signature = hash_hmac("sha256", $payload, $secretKey);

    // 6️⃣ Headers (SPACE after colon is IMPORTANT)
    $headers = [
        'Content-Type: application/json',
        'X-Client-ID: client_e9527ce27a4c3cf06b52ceeb4302339a0f39f77a50450318',
        'X-Signature: ' . $signature,
        'X-Timestamp: ' . $timestamp,
        'X-Request-ID: ' . $timestamp . rand(100,999),
        'X-API-Key: your_api_key_here'
    ];

    // 7️⃣ cURL (SEND JSON STRING)
    $curl = curl_init();

    curl_setopt_array($curl, [
        CURLOPT_URL => 'https://nxtbanking.ai/api/v1/payout/initiate',
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => $body, // ✅ JSON STRING
        CURLOPT_HTTPHEADER => $headers,
    ]);

    $response = curl_exec($curl);
    $data = json_decode($response,true);
    dd($data);
    // dd($data['data']['upi_intent']);
    curl_close($curl);

    return $data;
}


}
