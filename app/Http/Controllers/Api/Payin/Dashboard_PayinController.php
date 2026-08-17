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

class Dashboard_PayinController extends Controller
{

    public function AIRpay_create($request, $apiToken)
    {
        $transactionAmount = $request->amount;
        $user = User::find($apiToken);

        $schemeInfo = Scheme::where('id', $user->scheme_id)
            ->where('status', true)
            ->first();

        if (!$schemeInfo) {
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

        $orderId = 'SPAY' . now()->format('YmdHis') . rand(11111111, 99999999);

        $data = [
            "gst"                  => $gst,
            "charge"               => $calculatedCommission,
            "mobile"               => $request->buyer_phone,
            "txnid"                => $orderId,
            "payid"                => $orderId,
            "mytxnid"              => $request->orderid,
            "amount"               => $transactionAmount,
            "user_id"              => $apiToken,
            "profit"               => $totalCommissionWithGst,
            "payin_amount"         => $remainingAmount,
            "payin_rolling_amount" => $rolling_amount,
            "transaction_type"     => "credit",
            "status"               => "initiated",
            "remark"               => "airpay",
            "product"              => "UPI",
            "payment_platform"     => "portal",
            "description"          => "Payment initiated",
            "payer_email"          => $request->buyer_email,
            "payer_name"           => $request->buyer_name,
            "option1"              =>'payin calculation is pending',
        ];

        return Report::create($data);
    }

     public function CommonCurl($url,$payload = [], $headers = [])
     {
        $curl = curl_init();

        curl_setopt_array($curl, [
            CURLOPT_URL            => $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST           => true,
            CURLOPT_POSTFIELDS     => $payload,
            // CURLOPT_HTTPHEADER     => $headers, // dynamic headers
            CURLOPT_TIMEOUT        => 60,
        ]);
    
        $response = curl_exec($curl);
        $curlError = curl_error($curl);
        curl_close($curl);
    
        // Handle errors
        if ($curlError) {
            return [
                "status" => "error",
                "message" => $curlError
            ];
        }
    
        // Decode JSON response
        $res = json_decode($response, true);
        // dump($res);
        return $res;
    }
    private $mids = [
               [
            'id' => 'MID1',
            'limit' => 2500000,
            'bank' => 'YES BANK',
            'credentials' => [
                "merchant_id"=> "352568",
                "username"   => "UB2uYkYSYM",
                "password"   => "Yst9qn9A",
                "client_id"  => "179754",
                "client_secret"=> "1d290bf8c8d70aa6d05f68cebfd3e9f1",
                "secretKey"  => "e5619176a4122465e38232fbb5be074c",
                "secret"     => "1d290bf8c8d70aa6d05f68cebfd3e9f1"
            ]
        ]
        // [
        //     'id' => 'MID1',
        //     'limit' => 10,
        //     'bank' => 'JIO BANK',
        //     'credentials' => [
        //         "merchant_id"=> "348960",
        //         "username"   => "u33xJ9aXgu",
        //         "password"   => "82CHrfxe",
        //         "client_id"  => "d5c541",
        //         "client_secret"=> "7c15d6e7a92e948f1215308fc2e82c16",
        //         "secretKey"  => "ac2469d35f8aaf78a1c3079ed49bc232",
        //         "secret"     => "7c15d6e7a92e948f1215308fc2e82c16"
        //     ]
        // ],
        // [
        //     'id' => 'MID2',
        //     'limit' => 10,
        //     'bank' => 'YES BANK',
        //     'credentials' => [
        //         "merchant_id"=> "348596",
        //         "username"   => "KM8928FwqU",
        //         "password"   => "G5MfYzFn",
        //         "client_id"  => "ce7453",
        //         "client_secret"=> "3241f7d471d3390ba612b8b756bb8db8",
        //         "secretKey"  => "057551b9657cb585f89d76fa0794f0ae",
        //         "secret"     => "3241f7d471d3390ba612b8b756bb8db8"
        //     ]
        // ],
        // [
        //     'id' => 'MID3',
        //     'limit' => 10,
        //     'bank' => 'FINO',
        //     'credentials' => [
        //         "merchant_id"=> "348962",
        //         "username"   => "u8k9xrGV2Z",
        //         "password"   => "DY5cJpvf",
        //         "client_id"  => "288154",
        //         "client_secret"=> "6a6c1598f1f928cbf9cc8f2d1ad47893",
        //         "secretKey"  => "5fbd5197acf53d00889c8ed35f8e0fef",
        //         "secret"     => "6a6c1598f1f928cbf9cc8f2d1ad47893"
        //     ]
        // ],
        // [
        //     'id' => 'MID4',
        //     'limit' => 10,
        //     'bank' => 'COSMOS',
        //     'credentials' => [
        //         "merchant_id"=> "348959",
        //         "username"   => "9P9XUBTkQ3",
        //         "password"   => "G7bA3pW8",
        //         "client_id"  => "b83a60",
        //         "client_secret"=> "5b062792125f9cc25cb9f87d527b68e2",
        //         "secretKey"  => "1121a9129c01c6cac00d7162197f397a",
        //         "secret"     => "5b062792125f9cc25cb9f87d527b68e2"
        //     ]
        // ],
        // [
        //     'id' => 'MID5',
        //     'limit' => 10,
        //     'bank' => 'RBL',
        //     'credentials' => [
        //         "merchant_id"=> "348961",
        //         "username"   => "GFwCaT9x7h",
        //         "password"   => "NcWwV3xm",
        //         "client_id"  => "2a1978",
        //         "client_secret"=> "f76bf33f99e93fd176f483d946b747a3",
        //         "secretKey"  => "aa5b0f9a38b1ef36017a77022442c26b",
        //         "secret"     => "f76bf33f99e93fd176f483d946b747a3"
        //     ]
        // ],
        // [
        //     'id' => 'MID6',
        //     'limit' => 10,
        //     'bank' => 'SBI',
        //     'credentials' => [
        //         "merchant_id"=> "348963",
        //         "username"   => "6yyXehkFQM",
        //         "password"   => "FUmSzNp5",
        //         "client_id"  => "996007",
        //         "client_secret"=> "ad840e798a5f5557cf76356ab74bb972",
        //         "secretKey"  => "223acc8d8dda1b6c9ede087b2bf95f64",
        //         "secret"     => "ad840e798a5f5557cf76356ab74bb972"
        //     ]
        // ],
        // [
        //     'id' => 'MID6',
        //     'limit' => 10,
        //     'bank' => 'Fino Bank',    
        //     'credentials' => [
        //         "merchant_id"=> "348962",
        //         "username"   => "u8k9xrGV2Z",
        //         "password"   => "DY5cJpvf",
        //         "client_id"  => "288154",
        //         "client_secret"=> "6a6c1598f1f928cbf9cc8f2d1ad47893",
        //         "secretKey"  => "5fbd5197acf53d00889c8ed35f8e0fef",
        //         "secret"     => "6a6c1598f1f928cbf9cc8f2d1ad47893" 
        //     ],    
        //  ]

        
    ];
    
    private function getActiveMid($transactionAmount)
    {
        // dump("get mid function called ".$transactionAmount );
        $midUsage = DB::table('reports')
            ->select('option4 as mid', DB::raw('SUM(amount) as total'))
            ->where('product', 'UPI')
            // ->where('status', 'initiated')
            ->whereIn('status', ['initiated', 'success'])
            ->whereDate('created_at', Carbon::today())
            ->groupBy('option4')
            ->pluck('total', 'mid')
            ->toArray();
          dump($midUsage);  
          
        Log::info('🧾 Today MID Usage test: ', $midUsage);
        foreach ($this->mids as $mid) {
            $used = $midUsage[$mid['credentials']['merchant_id']] ?? 0;
            $remaining = $mid['limit'] - $used;

            if ($transactionAmount <= $remaining) {
                Log::info("Using MID: {$mid['credentials']['merchant_id']} ({$mid['bank']}) | Remaining: ₹{$remaining}");
                return $mid;
            }
        }

        Log::warning("⚠️ All MIDs have reached their limits for amount ₹{$transactionAmount}");
        return null;
    } 
   public function generate_Airpay_UPIQR(Request $request)
    {
        $rules = [
            'orderid'      => 'required|alpha_num|min:8|max:20|unique:reports,mytxnid',
            'buyer_email'  => 'required|email',
            'buyer_phone'  => 'required|digits_between:10,15',
            'amount'       => 'required|numeric|min:10',
        ];

        $validator = Validator::make($request->all(), $rules);

        if ($validator->fails()) {
            return response()->json([
                'statuscode' => 422,
                'message'    => $validator->errors()->first(),
            ], 422);
        }

        $apiToken = auth()->id();

        if (!$apiToken) {
            return response()->json([
                'statuscode' => 401,
                'message'    => "User not authenticated",
            ], 401);
        }

        $user = User::find($apiToken);
        if (!$user || (int) $user->payin_status !== 1) {
            return response()->json([
                'status'     => 'failed',
                'statuscode' => 403,
                'message'    => 'Your PayIN account is deactivated. Please contact admin.',
            ], 403);
        }

        $report = $this->AIRpay_create($request, $apiToken);

        if (!($report instanceof Report)) {
            return response()->json([
                'status'     => 'failed',
                'message'    => 'Unable to create report entry',
            ], 500);
        }
        // $credential = Credential::find($user->credentials_id);
        
        // $description = $credential->description;
        
        // // decode JSON from DB
        // if (is_string($description)) {
        //     $description = json_decode($description, true);
        // }

    $providerName = trim($user->payin_at_onboard);

    if (empty($providerName)) {
        return response()->json([
            'status'  => 'failed',
            'message' => 'Provider name not found for this user'
        ], 400);
    }

    switch ($providerName) {

        /*
        |--------------------------------------------------------------------------
        | ðŸ”µ AIRPAY
        |--------------------------------------------------------------------------
        */
        case 'Airpay':
          
            $credential = Credential::find($user->credentials_id);

            if (!$credential) {
                return response()->json([
                    'status' => 'failed',
                    'message' => 'Credentials not found'
                ], 400);
            }

            $description = $credential->description;

            if (is_string($description)) {
                $description = json_decode($description, true);
            }

            $payload = [
                'orderid'     => $request->orderid,
                'amount'      => $request->amount,
                'buyer_email' => $request->buyer_email,
                'buyer_phone' => $request->buyer_phone,
                'mid'         => json_encode($description, JSON_UNESCAPED_SLASHES),
            ];

            $response = $this->CommonCurl(
                "https://ebookspay.co.in/dashboard/api/generateQR",
                $payload,
                ["Content-Type: application/json"]
            );

            $decoded = $response['response'] ?? [];

            if (!is_array($decoded) || ($decoded['status'] ?? '') !== 'success') {
                return response()->json([
                    'status' => 'failed',
                    'message' => 'QR API did not return success',
                    'raw' => $response
                ], 500);
            }

            $data = $decoded['data'] ?? [];

            $report->update([
                'apitxnid' => $data['ap_transactionid'] ?? null,
                'option4'  => $description['merchant_id'] ?? null,
            ]);

            return response()->json([
                'status_code' => $decoded['status_code'] ?? 200,
                'status'      => $decoded['status'],
                'data'        => [
                    'qrcode_string' => $data['qrcode_string'] ?? null,
                    'orderid'       => $request->orderid,
                    'txnid'         => $report->txnid,
                ]
            ], 200);


        /*
        |--------------------------------------------------------------------------
        | ðŸŸ£ AIRPAY ALL (MULTI MID)
        |--------------------------------------------------------------------------
        */
        case 'Airpay_all':

            $activeMid = $this->getActiveMid($request->amount);

            if (!$activeMid) {
                return response()->json([
                    'statuscode' => 400,
                    'message'    => 'All MIDs have reached their limits',
                ]);
            }

            $payload = [
                'orderid'     => $request->orderid,
                'amount'      => $request->amount,
                'buyer_email' => $request->buyer_email,
                'buyer_phone' => $request->buyer_phone,
                'mid'         => json_encode($activeMid['credentials'], JSON_UNESCAPED_SLASHES),
            ];

            $response = $this->CommonCurl(
                "https://ebookspay.co.in/dashboard/api/generateQR",
                $payload,
                ["Content-Type: application/json"]
            );

            $decoded = $response['response'] ?? [];

            if (!is_array($decoded) || ($decoded['status'] ?? '') !== 'success') {
                return response()->json([
                    'status' => 'failed',
                    'message' => 'QR API did not return success',
                    'raw' => $response
                ], 500);
            }

            $data = $decoded['data'] ?? [];

            $report->update([
                'apitxnid' => $data['ap_transactionid'] ?? null,
            ]);

            return response()->json([
                'status_code' => $decoded['status_code'] ?? 200,
                'status'      => $decoded['status'],
                'data'        => [
                    'qrcode_string' => $data['qrcode_string'] ?? null,
                    'orderid'       => $request->orderid,
                    'txnid'         => $report->txnid,
                ]
            ], 200);
        default:
            return response()->json([
                'status'  => 'failed',
                'message' => 'Unsupported provider'
            ], 400);

    }
    }
    public function check_status(Request $request)
    {
    // dd($request->orderid);
    $report = \App\Models\Report::where('product', 'UPI')
        ->where('mytxnid', $request->orderid)   
        ->first();

    if (!$report) {
        return response()->json([
            'success' => false,
            'message' => 'Transaction not found',
        ]);
    }

    $txnid  = $report->txnid;
    $amount = $report->amount;
    $status = strtoupper($report->status ?? 'UNKNOWN'); // ensure uppercase

    switch ($status) {
        case 'SUCCESS':
            return response()->json([
                'message' => 'Transaction Successfully Done.',
                'success' => true,
                'status'  => 'SUCCESS',
                'amount'  => $amount,
                'txnid'   => $txnid,
            ]);

        case 'PENDING':
            return response()->json([
                'message' => 'Transaction Pending',
                'success' => true,
                'status'  => 'PENDING',
                'amount'  => $amount,
                'txnid'   => $txnid,
            ]);

        case 'FAILED':
            return response()->json([
                'message' => 'Transaction Failed',
                'success' => false,
                'status'  => 'FAILED',
                'amount'  => $amount,
                'txnid'   => $txnid,
            ]);

        default:
            return response()->json([
                'success' => false,
                'status'  => 'UNKNOWN',
                'amount'  => $amount,
                'txnid'   => $txnid,
            ]);
    }
}
}
