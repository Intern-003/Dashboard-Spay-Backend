<?php

namespace App\Http\Controllers\Api\Payout;

use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use Carbon\Carbon;
use App\Models\User;
use App\Models\Mahaagent;
use App\Models\Company;
use App\Models\Mahastate;
use App\Models\Report;
use App\Models\Commission;
use App\Models\Aepsreport;
use App\Models\Provider;
use App\Models\Api;
use App\Models\Cosmosmerchant;
use App\Models\AuthToken;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Http;
use App\Models\Microatmreport;
use App\Models\UserPermission;
use App\Models\Apilog;
use App\Models\Scheme;
use App\Models\Utiid;
use App\Models\Packagecommission;
use App\Models\Package;

use App\Services\PayoutProviders\PayoutProviderFactory;
use Illuminate\Support\Facades\Validator;

// use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class CommanPayoutController extends Controller
{

public function payout_request(Request $request)
{
    try {
        // ------------------- VALIDATION -------------------
        $rules = [
            'token'                      => 'required',
            'orderid'                    => 'required|alpha_num|min:8|max:20|unique:reports,mytxnid',
            'beneficiary_email'          => 'required|email',
            'beneficiary_phone'          => 'required|digits_between:10,15',
            'beneficiary_account_number' => 'required|string',
            'beneficiary_ifsc'           => 'required|string',
            'beneficiary_name'           => 'required|string',
            'amount'                     => 'required|numeric|min:100'
        ];

        $validator = Validator::make($request->all(), $rules);
        if ($validator->fails()) {
            return $this->payoutCommonResponse(
                'failed',
                ['message' => $validator->errors()->first()],
                $request->orderid ?? '',
                (float)($request->amount ?? 0),
                $request->beneficiary_name ?? '',
                $request->beneficiary_account_number ?? '',
                422
            );
        }

        // ------------------- TOKEN + IP CHECK -------------------
        // $AuthToken = AuthToken::where('ip', $request->ip())
        //     ->where('token', $request->token)
        //     ->first(['user_id']);
        $AuthToken = AuthToken::where('token', $request->token)->first();

        if (!$AuthToken) {
            return $this->payoutCommonResponse(
                'failed',
                ['message' => 'IP or Token mismatch'],
                $request->orderid,
                (float)$request->amount,
                $request->beneficiary_name,
                $request->beneficiary_account_number,
                401
            );
        }

        // ✅ Transaction (wallet + report)
        return DB::transaction(function () use ($request, $AuthToken) {

            // ------------------- USER WITH LOCK -------------------
            $user = User::where('id', $AuthToken->user_id)->lockForUpdate()->first();

            if (!$user) {
                return $this->payoutCommonResponse(
                    'failed',
                    ['message' => 'User not found'],
                    $request->orderid,
                    (float)$request->amount,
                    $request->beneficiary_name,
                    $request->beneficiary_account_number,
                    404
                );
            }

        $user = User::find($AuthToken->user_id);
        if (!$user || (int) $user->payout_status !== 1) {
            return response()->json([
                'status'     => 'failed',
                'statuscode' => 403,
                'message'    => 'Your PayOut account is deactivated. Please contact admin.',
            ], 403);
        }
        
            $schemeInfo = Scheme::where('id', $user->scheme_id)
                ->where('status', true)
                ->first();

            if (!$schemeInfo) {
                return $this->payoutCommonResponse(
                    'failed',
                    ['message' => 'Scheme not defined for this user'],
                    $request->orderid,
                    (float)$request->amount,
                    $request->beneficiary_name,
                    $request->beneficiary_account_number,
                    400
                );
            }

            // ------------------- COMMISSION & WALLET -------------------
            $amount = (float) $request->amount;

            $below = (float) $schemeInfo->payout_commision_amount_below;
            $above = (float) $schemeInfo->payout_commision_amount_above;

            if ($amount <= 1000) {
                $commission = $below;
            } elseif ($amount > 1000 && $amount <= 1000000) {
                $commission = ($amount * $above) / 100;
            } else {
                return $this->payoutCommonResponse(
                    'failed',
                    ['message' => 'Maximum payout limit ₹10,00,000'],
                    $request->orderid,
                    $amount,
                    $request->beneficiary_name,
                    $request->beneficiary_account_number,
                    400
                );
            }

            $gst        = ($commission * 18) / 100;
            $charge     = $commission;
            $profit     = $charge + $gst;
            $totalDebit = $amount + $profit;

            if ((float)$user->payout_wallet < (float)$totalDebit) {
                return $this->payoutCommonResponse(
                    'failed',
                    ['message' => "Insufficient Balance. Available: ₹{$user->payout_wallet}, Required: ₹{$totalDebit}"],
                    $request->orderid,
                    $amount,
                    $request->beneficiary_name,
                    $request->beneficiary_account_number,
                    402
                );
            }

            $openingBalance = (float)$user->payout_wallet;

            // debit
            $user->decrement('payout_wallet', $totalDebit);
            $user->refresh();
            $closingBalance = (float)$user->payout_wallet;

            // ------------------- CREATE REPORT -------------------
            $txnid = 'SPAY' . now()->format('YmdHis') . rand(111111, 999999);

            $report = Report::create([
                'user_id'                => $user->id,
                'mobile'                 => $request->beneficiary_phone,
                'amount'                 => $amount,
                'charge'                 => $charge,
                'profit'                 => $profit,
                'gst'                    => $gst,
                'txnid'                  => $txnid,
                'apitxnid'               => $request->orderid,
                'mytxnid'                => $request->orderid,
                'product'                => 'payout',

                'payout_amount'          => $totalDebit,
                'payout_opening_balance' => $openingBalance,
                'payout_closing_balance' => $closingBalance,

                'payer_acc_no'           => $request->beneficiary_account_number,
                'payer_ifsc'             => $request->beneficiary_ifsc,
                'payer_mobile'           => $request->beneficiary_phone,
                'payer_name'             => $request->beneficiary_name,
                'payer_email'            => $request->beneficiary_email,

                'commission_inc_gst'     => $charge,
				'description'            => 'Debit ₹' . $totalDebit . ' to Payout Wallet',
                'payout_mode'            => 'IMPS',
                'transaction_type'       => 'debit',
                'payment_platform'       => 'api',
                'status'                 => 'pending',
                'bank_other_charges'     => 0,
            ]);

            // dd($report );
            // ------------------- PROVIDER -------------------
            $providerName = $user->payout_at_onboard;
            // dd($providerName);
            $provider     = PayoutProviderFactory::make($providerName);
            // dd($provider);
            Log::debug("Provider Response", [
                'provider' => $provider,
            ]);

            if (!$provider) {
                // rollback
                // $user->increment('payout_wallet', $totalDebit);
                // $report->update(['status' => 'failed', 'remark' => 'Invalid Provider']);

                return $this->payoutCommonResponse(
                    'failed',
                    ['message' => 'Invalid Provider'],
                    $request->orderid,
                    $amount,
                    $request->beneficiary_name,
                    $request->beneficiary_account_number,
                    400
                );
            }
            
            $payload = [
                'orderid'             => $request->orderid,
                'amount'              => $amount,
                'bank_account_number' => $request->beneficiary_account_number,
                'bank_ifsc'           => $request->beneficiary_ifsc,
                'beneficiary_name'    => $request->beneficiary_name,
                'beneficiary_email'   => $request->beneficiary_email,
                'beneficiary_phone'   => $request->beneficiary_phone,
            ];
            // dd($payload);
            $apiResponse = $provider->send($payload);
            // dd( "reaponse",  $apiResponse);
            Log::debug("Payout Provider Response", [
                'provider name' => $providerName,
                'payload'  => $payload,
                'response' => $apiResponse
            ]);
            if (!is_array($apiResponse)) {
                // rollback
                // $user->increment('payout_wallet', $totalDebit);
                // $report->update(['status' => 'failed', 'remark' => 'Invalid Provider Response']);
                return $this->payoutCommonResponse(
                    'failed',
                    ['message' => 'Invalid Provider Response'],
                    $request->orderid,
                    $amount,
                    $request->beneficiary_name,
                    $request->beneficiary_account_number,
                    400
                );
            }

            // provider failed
            if (strtolower($apiResponse['status'] ?? '') === 'failed') {
                $errorMsg = $apiResponse['error'] ?? $apiResponse['message'] ?? 'Provider Error';
                // rollback
                // $user->increment('payout_wallet', $totalDebit);
                // $report->update(['status' => 'failed', 'remark' => $errorMsg]);

                // ✅ IMPORTANT: still send data
                return $this->payoutCommonResponse(
                    'failed',
                    array_merge($apiResponse, ['message' => $errorMsg]),
                    $request->orderid,
                    $amount,
                    $request->beneficiary_name,
                    $request->beneficiary_account_number,
                    400
                );
            }

            // ------------------- MAP STATUS -------------------
            // $providerStatusRaw =
            //     $apiResponse['status']
            //     ?? $apiResponse['txn_status']
            //     ?? $apiResponse['transaction_status']
            //     ?? $apiResponse['message']
            //     ??  ($apiResponse['response'][0]['status_id'] ?? null)
            //     ?? 'failed';
            
            
            $providerStatusRaw = $apiResponse['payoutTxnStatus'] ?? 'failed';
            $providerStatus = strtolower($providerStatusRaw);

            if (in_array($providerStatus, ['received', 'processed', 'success', 'successful','1','11'])) {
                $finalStatus = 'success';
            } elseif (in_array($providerStatus, ['pending', 'initiated', 'processing','2','3','4'])) {
                $finalStatus = 'pending';
            } else {
                $finalStatus = 'failed';
            }
            // $refno = $apiResponse['refno'] ?? $apiResponse['rrn'] ?? $apiResponse['utr'] ?? null;
            $refno = $apiResponse['transactionReference'] ?? $apiResponse['rrn'] ?? $apiResponse['utr'] ?? null;
            $beneficiaryId = $apiResponse['beneficiary_id'] ??  null;
            $payoutTransactionId = $apiResponse['payoutTxnId'] ??  null;
            $transactionId = $apiResponse['transactionId'] ??  null;
            
            // dump($providerStatusRaw);
            // dump($providerStatus);
            // dump($finalStatus);
            // dump($refno);
            // dump($beneficiaryId);
            // dump($payoutTransactionId);
            // dd($transactionId);

            $report->update([
                'status' => $finalStatus,
                'glide_uiwidget_sessionid' => $beneficiaryId,
                'payid'    => $payoutTransactionId,
                'apitxnid' => $transactionId,
                'option3'   => $apiResponse['message'] ?? null,
                'remark'   =>  'Bridg Money',
            ]);

            // ✅ If mapped failed here, still return data + 400
            $httpCode = ($finalStatus === 'failed') ? 400 : 200;

            return $this->payoutCommonResponse(
                $finalStatus,
                $apiResponse,
                $request->orderid,
                $amount,
                $request->beneficiary_name,
                $request->beneficiary_account_number,
                $httpCode
            );
        });

    } catch (\Exception $e) {

        Log::error("PAYOUT ERROR", [
            'line'  => $e->getLine(),
            'file'  => $e->getFile(),
            'error' => $e->getMessage()
        ]);

        // internal error -> 500
        return $this->payoutCommonResponse(
            'failed',
            ['message' => 'Internal Server Error'],
            $request->orderid ?? '',
            (float)($request->amount ?? 0),
            $request->beneficiary_name ?? '',
            $request->beneficiary_account_number ?? '',
            500
        );
    }
}


private function payoutCommonResponse(
    string $status,
    array $apiResponse,
    string $orderid,
    float $amount,
    string $beneName,
    string $accountNo,
    int $httpCode = 200
) {
    $status = strtolower($status);

    /* ================= STATUS MESSAGE ================= */

    switch ($status) {
        case 'failed':
            $mainMessage = "❌ Payout status: Failed";
            $httpCode    = 400;
            break;

        case 'success':
            $mainMessage = "✅ Payout status: Success";
            $httpCode    = 200;
            break;

        case 'pending':
        default:
            $mainMessage = "⏳ Payout status: Pending";
            $httpCode    = 200;   // ✅ Pending should NOT be 400
            break;
    }

    /* ================= PROVIDER MESSAGE ================= */

    $providerMessage = $apiResponse['message']
        ?? $apiResponse['msg']
        ?? ($status === 'failed'
            ? 'Transfer Failed'
            : ($status === 'pending'
                ? 'Transfer Initiated'
                : 'Transfer Successful'
            )
        );

    /* ================= RRN ================= */

    $rrn = $apiResponse['rrn']
        ?? $apiResponse['bank_rrn']
        ?? $apiResponse['utr']
        ?? $apiResponse['refno']
        ?? null;

    /* ================= TXN DATE ================= */

    $txnDate = $apiResponse['txn_date']
        ?? $apiResponse['transaction_date']
        ?? now()->format('Y-m-d H:i:s');

    /* ================= CLIENT REF ================= */

    $clientRefNo = $apiResponse['client_ref_no']
        ?? $apiResponse['clientRefNo']
        ?? $apiResponse['refno']
        ?? $orderid;

    /* ================= MASK ACCOUNT ================= */

    $accountNo = (string) $accountNo;
    $maskedAcc = strlen($accountNo) > 4
        ? str_repeat('x', strlen($accountNo) - 4) . substr($accountNo, -4)
        : $accountNo;
        
// dump($status);
// dump($mainMessage);
// dump($httpCode);
// dump($providerMessage);
// dump($rrn);
// dump($txnDate);
// dump($clientRefNo);
// dump($accountNo);
// dump($beneName);
// dd($maskedAcc);

    return response()->json([
        "status"     => $status,
        "statuscode" => $httpCode,
        "message"    => $mainMessage,
        "data"       => [
            "message"          => $providerMessage,
            "bene_name"        => $beneName,
            "customer_account" => $maskedAcc,
            "amount"           => number_format($amount, 2, '.', ''),
            "client_ref_no"    => $clientRefNo,
            "txn_date"         => $txnDate,
            "rrn"              => $rrn ?? 'Null',
        ]
    ], $httpCode);
}








// private function payoutCommonResponse(
//     string $status,
//     array $apiResponse,
//     string $orderid,
//     float $amount,
//     string $beneName,
//     string $accountNo,
//     int $httpCode = 200
// ) {
//     $status = strtolower($status);

//     // ✅ emoji + message
//     if ($status === 'failed') {
//         $mainMessage = "❌ Payout status: Failed";
//     } elseif ($status === 'success') {
//         $mainMessage = "✅ Payout status: Success";
//     } else {
//         $mainMessage = "✅ Payout status: Pending";
//     }

//     // ✅ provider message
//     $providerMessage = $apiResponse['message']
//         ?? $apiResponse['msg']
//         ?? ($status === 'failed' ? 'Transfer Failed' : 'Transfer Initiated');

//     // ✅ rrn
//     $rrn = $apiResponse['rrn']
//         ?? $apiResponse['bank_rrn']
//         ?? $apiResponse['utr']
//         ?? $apiResponse['refno']
//         ?? null;

//     // ✅ txn date
//     $txnDate = $apiResponse['txn_date']
//         ?? $apiResponse['transaction_date']
//         ?? now()->format('Y-m-d H:i:s');

//     // ✅ client ref no
//     $clientRefNo = $apiResponse['client_ref_no']
//         ?? $apiResponse['clientRefNo']
//         ?? $apiResponse['refno']
//         ?? $orderid;

//     // ✅ mask account
//     $accountNo = (string) $accountNo;
//     $maskedAcc = strlen($accountNo) > 4
//         ? str_repeat('x', strlen($accountNo) - 4) . substr($accountNo, -4)
//         : $accountNo;

//     return response()->json([
//         "status"     => $status,
//         "statuscode" => $httpCode,   // ✅ in JSON also
//         "message"    => $mainMessage,
//         "data"       => [            // ✅ ALWAYS present (even failed)
//             "message"          => $providerMessage,
//             "bene_name"        => $beneName,
//             "customer_account" => $maskedAcc,
//             "amount"           => number_format($amount, 2, '.', ''),
//             "client_ref_no"    => $clientRefNo,
//             "txn_date"         => $txnDate,
//             "rrn"              => $rrn,
//         ]
//     ], $httpCode);
// }
    // ---------------- STATUS CHECK ----------------


    
    public function payout_status(Request $request)
    {
    
    $report = \App\Models\Report::where('product', 'payout')
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
    $refno =  $report->refno ?? null;
    $status = strtoupper($report->status ?? 'UNKNOWN'); // ensure uppercase

    switch ($status) {
        case 'SUCCESS':
            return response()->json([
                'message' => 'Transaction Successfully Done.',
                'success' => true,
                'status'  => 'SUCCESS',
                'amount'  => $amount,
                'txnid'   => $txnid,
                'UTR' => $refno,
            ]);

        case 'PENDING':
            return response()->json([
                'message' => 'Transaction Pending',
                'success' => true,
                'status'  => 'PENDING',
                'amount'  => $amount,
                'txnid'   => $txnid,
                'UTR' => $refno,
            ]);

        case 'FAILED':
            return response()->json([
                'message' => 'Transaction Failed',
                'success' => false,
                'status'  => 'FAILED',
                'amount'  => $amount,
                'txnid'   => $txnid,
                'UTR' => $refno,
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
    
    public static function getCommission($amount, $scheme, $slab, $role)
    {
        $commission = 0;
    
        try {
            $schememanager = \DB::table('portal_settings')->where('code', 'schememanager')->first(['value']);
            if ($schememanager->value != "all") {
                $myscheme = \App\Models\Scheme::find($scheme);
                if ($myscheme && $myscheme->status == "1") {
                    $comdata = \App\Models\Commission::where('scheme_id', $scheme)->where('slab', $slab)->first();
                    if ($comdata) {
                        $commission = $comdata->type == "percent"
                            ? ($amount * ($comdata[$role] ?? 0) / 100)
                            : ($comdata[$role] ?? 0);
                    }
                }
            } else {
                $myscheme = \App\Models\Package::find($scheme);
                if ($myscheme && $myscheme->status == "1") {
                    $comdata = \App\Models\Packagecommission::where('scheme_id', $scheme)->where('slab', $slab)->first();
                    if ($comdata) {
                        $commission = $comdata->type == "percent"
                            ? ($amount * ($comdata->value ?? 0) / 100)
                            : ($comdata->value ?? 0);
                    }
                }
            }
        } catch (\Exception $e) {
            \Log::error("Commission Calculation Error", ['message' => $e->getMessage()]);
        }
    
        \Log::debug("Final Commission Returned", ['commission' => $commission]);
    
        return $commission;
    }

    public function getGst($amount,$gstrate){
        return ($gstrate/100)*$amount;
    }
    
    
    
}