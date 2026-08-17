<?php

namespace App\Services\Payin;

use App\Models\AuthToken;
use App\Models\Credential;
use App\Models\Report;
use App\Models\Scheme;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class PayinService
{
    // ✅ Put your MID config here (or move to config file later)
    private array $mids = [
        [
            'id' => 'MID1',
            'limit' => 10,
            'bank' => 'YES BANK',
            'credentials' => [
                "merchant_id"=> "353405",
                "username"   => "zY4KPwTjP4",
                "password"   => "dqQE4f8z",
                "client_id"  => "454969",
                "client_secret"=> "4fbb61f1f5a95a242b14f4e44218dcc5",
                "secretKey"  => "67d5c956c204bb6719bff713904d5bd7",
                "secret"     => "4fbb61f1f5a95a242b14f4e44218dcc5"
            ]
        ],
        [
            'id' => 'MID2',
            'limit' => 10,
            'bank' => 'YES BANK',
            'credentials' => [
                "merchant_id"=> "348596",
                "username"   => "KM8928FwqU",
                "password"   => "G5MfYzFn",
                "client_id"  => "ce7453",
                "client_secret"=> "3241f7d471d3390ba612b8b756bb8db8",
                "secretKey"  => "057551b9657cb585f89d76fa0794f0ae",
                "secret"     => "3241f7d471d3390ba612b8b756bb8db8"
            ]
        ],
    ];

    public function authByToken(string $token): ?AuthToken
    {
        return AuthToken::where('token', $token)->first();
    }

    public function getActiveMid(float $transactionAmount): ?array
    {
        $midUsage = DB::table('reports')
            ->select('option4 as mid', DB::raw('SUM(amount) as total'))
            ->where('product', 'UPI')
            ->whereIn('status', ['initiated', 'success'])
            ->whereDate('created_at', Carbon::today())
            ->groupBy('option4')
            ->pluck('total', 'mid')
            ->toArray();

        Log::info('🧾 Today MID Usage: ', $midUsage);

        foreach ($this->mids as $mid) {
            $used = $midUsage[$mid['credentials']['merchant_id']] ?? 0;
            $remaining = $mid['limit'] - $used;

            if ($transactionAmount <= $remaining) {
                Log::info("Using MID: {$mid['credentials']['merchant_id']} ({$mid['bank']}) | Remaining: ₹{$remaining}");
                return $mid;
            }
        }

        Log::warning("⚠️ All MIDs reached limit for amount ₹{$transactionAmount}");
        return null;
    }

    public function commonCurl(string $url, array $payload = [], array $headers = [], bool $forceIpv4 = false): array
    {
        // var_dump("common".$payload);
        $curl = curl_init();

        // curl_setopt_array($curl, [
        //     CURLOPT_URL            => $url,
        //     CURLOPT_RETURNTRANSFER => true,
        //     CURLOPT_POST           => true,
        //     CURLOPT_POSTFIELDS     => $payload, // form-data style (same as your code)
        //     CURLOPT_TIMEOUT        => 60,
        // ]);
        
        // curl_setopt_array($curl, [
        //     CURLOPT_URL            => $url,
        //     CURLOPT_RETURNTRANSFER => true,
        //     CURLOPT_POST           => true,
        //     CURLOPT_POSTFIELDS     => json_encode($payload), // ✅ FIX
        //     CURLOPT_HTTPHEADER     => $headers,              // ✅ ADD THIS
        //     CURLOPT_TIMEOUT        => 60,
        // ]);
        
        $options = [
            CURLOPT_URL            => $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST           => true,
            CURLOPT_POSTFIELDS     => json_encode($payload),
            CURLOPT_HTTPHEADER     => $headers,
            CURLOPT_TIMEOUT        => 60,
        ];
        
        ///---- for shamavenue
        if ($forceIpv4) {
            $options[CURLOPT_IPRESOLVE] = CURL_IPRESOLVE_V4;
        }
    
        curl_setopt_array($curl, $options);
        ///----

        $response  = curl_exec($curl);
        // dd($response);
        $curlError = curl_error($curl);
        curl_close($curl);

        if ($curlError) {
            return ["status" => "error", "message" => $curlError];
        }

        $res = json_decode($response, true);
        return is_array($res) ? $res : ["status" => "error", "message" => "Invalid JSON response", "raw" => $response];
    }

    /**
     * ✅ Your AIRpay_create logic moved here
     * Creates report & returns Report model.
     */
    public function createReportForUpiQr(array $req, User $user, string $providerName, ?array $activeMid = null): Report
    {
        $transactionAmount = (float) $req['amount'];

        $schemeInfo = Scheme::where('id', $user->scheme_id)
            ->where('status', true)
            ->first();

        if (!$schemeInfo) {
            throw new \Exception("Scheme not defined for this user");
        }

        // Commission
        // $payinCommissionType   = $schemeInfo->payin_commision_type;
        // $payinCommissionAmount = (float) $schemeInfo->payin_commision_amount;

        // $calculatedCommission = 0;
        // if ($payinCommissionType === 'percent') {
        //     $calculatedCommission = ($transactionAmount * $payinCommissionAmount) / 100;
        // } elseif ($payinCommissionType === 'flat') {
        //     $calculatedCommission = $payinCommissionAmount;
        // }
        
        // ------------------- COMMISSION & WALLET -------------------

            $below = (float) $schemeInfo->payin_commision_amount_below;
            $above = (float) $schemeInfo->payin_commision_amount_above;

            if ($transactionAmount <= 500) {
                $calculatedCommission = $below;
            } elseif ($transactionAmount > 500 && $transactionAmount <= 100000) {
                $calculatedCommission = ($transactionAmount * $above) / 100;
            } else {
                return response()->json([
                    'status'  => 'failed',
                    'message' => 'Maximum payin limit ₹1,00,000'
                ], 400);
            }

        $gst = ($calculatedCommission * 18) / 100;

        // Rolling
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
            "mobile"               => $req['phone'] ?? null,
            "txnid"                => $orderId,
            "payid"                => $orderId,
            "mytxnid"              => $req['orderid'],
            "amount"               => $transactionAmount,
            "user_id"              => $user->id,
            "profit"               => $totalCommissionWithGst,
            "payin_amount"         => $remainingAmount,
            "payin_rolling_amount" => $rolling_amount,
            "transaction_type"     => "credit",
            "status"               => "initiated",
            "remark"               => trim($providerName),
            "product"              => "UPI",
            "payment_platform"     => "api",
            "description"          => "Payment initiated",
            "payer_email"          => $req['email'] ?? null,
            "option1"              => 'payin calculation is pending',
            "option4"              => $activeMid['credentials']['merchant_id'] ?? null,
        ];

        return Report::create($data);
    }

    public function getCredentialDescription(User $user): array
    {
        $credential = Credential::find($user->credentials_id);
        if (!$credential) return [];

        $desc = $credential->description;
        if (is_string($desc)) {
            $decoded = json_decode($desc, true);
            return is_array($decoded) ? $decoded : [];
        }
        return is_array($desc) ? $desc : [];
    }
}