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
            'token'                     => 'required',
            'orderid'                   => 'required|alpha_num|min:8|max:20|unique:reports,mytxnid',
            'beneficiary_email'         => 'required|email',
            'beneficiary_phone'         => 'required|digits_between:10,15',
            'beneficiary_account_number'=> 'required|string',
            'beneficiary_ifsc'          => 'required|string',
            'beneficiary_name'          => 'required|string',
            'amount'                    => 'required|numeric|min:1'
        ];

        $validator = Validator::make($request->all(), $rules);
        if ($validator->fails()) {
            return response()->json([
                'status'     => 'failed',
                'statuscode' => 422,
                'message'    => $validator->errors()->first(),
            ], 422);
        }

        // ------------------- TOKEN + IP CHECK -------------------
        $AuthToken = AuthToken::where('ip', $request->ip())
            ->where('token', $request->token)
            ->first(['user_id']);

        if (!$AuthToken) {
            return response()->json([
                'status'     => 'failed',
                'statuscode' => 401,
                'message'    => "IP or Token mismatch",
            ], 401);
        }

        // ------------------- USER WITH LOCK -------------------
        $user = User::where('id', $AuthToken->user_id)->lockForUpdate()->first();
      
        if (!$user) {
            return response()->json([
                'status'     => 'failed',
                'statuscode' => 404,
                'message'    => 'User not found',
            ], 404);
        }

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

        // ------------------- COMMISSION & WALLET -------------------
        // $payout_commision_type_above = $schemeInfo->payout_commision_type_above;
        $payout_commision_amount_below   = $schemeInfo->payout_commision_amount_below;
        $payout_commision_amount_above = $schemeInfo->payout_commision_amount_above;
        $amount = $request->amount;
        $commission = 0;
        
        if ($amount <= 700) {
            $commission = $payout_commision_amount_below;
        } elseif ($amount > 700 && $amount <= 1000000) {
            $commission = ($amount * $payout_commision_amount_above) / 100;
        } else {
            return response()->json([
                'status'     => 'failed',
                'statuscode' => 400,
                'message'    => 'Maximum payout limit ₹10,00,000',
            ], 400);
        }
       
        
        
        $gst = ($commission * 18) / 100;
        $charge = $commission + $gst;
        $totalDebit = $amount + $charge;
        $openingBalance = $user->payout_wallet;
 

        if ($openingBalance < $totalDebit) {
            return response()->json([
                'status'     => 'failed',
                'statuscode' => 402,
                'message'    => "Insufficient Balance. Available: ₹{$openingBalance}, Required: ₹{$totalDebit}"
            ], 402);
        }

        
        $user->decrement('payout_wallet', $totalDebit);
        $closingBalance = $user->payout_wallet;

        // ------------------- CREATE REPORT -------------------
        $txnid = 'SPAY' . now()->format('YmdHis') . rand(111111,999999);
        
        $data = [
            'user_id'                => $user->id,
            'mobile'                 => $request->beneficiary_phone,
            'amount'                 => $amount,
            'profit'                 => $commission,
            'gst'                    => $gst,
            'charge'                 => $charge,
            
            'txnid'                  => $txnid,
            'apitxnid'               => $request->orderid,
            'mytxnid'                => $request->orderid,

            'payout_amount'          => $amount,
            'payout_opening_balance' => $openingBalance,
            'payout_closing_balance' => $closingBalance,

            'payer_acc_no'           => $request->beneficiary_account_number,
            'payer_ifsc'             => $request->beneficiary_ifsc,
            'payer_mobile'           => $request->beneficiary_phone,
            'payer_name'             => $request->beneficiary_name,
            'payer_email'            => $request->beneficiary_email,

            'commission_inc_gst'     => $charge,
            'product'                => 'payout',
            'transaction_type'       => 'debit',
            'payment_platform'       => 'api',
            'status'                 => 'pending',
            'bank_other_charges'     => 0,
            "description"            => "Debit ₹{$totalDebit} to Payout Wallet",
            "remark"                 => "Payout pending",
        ];
        // dd($data);
        $report = Report::create($data);
        // ------------------- PROVIDER FACTORY -------------------
        $providerName = $user->payout_at_onboard;
        // dd($providerName);
        $provider = PayoutProviderFactory::make($providerName);

        if (!$provider) {
            return response()->json([
                'status'  => 'failed',
                'message' => "Invalid Provider"
            ], 400);
        }

        // ------------------- PAYLOAD -------------------
        $payload = [
            'orderid'             => $request->orderid,
            'amount'              => $amount,
            'bank_account_number' => $request->beneficiary_account_number,
            'bank_ifsc'           => $request->beneficiary_ifsc,
            'beneficiary_name'    => $request->beneficiary_name,
            'beneficiary_email'   => $request->beneficiary_email,
            'beneficiary_phone'   => $request->beneficiary_phone,
        ];
//   dd($payload);
        // ------------------- EXECUTE PROVIDER -------------------
        $apiResponse = $provider->send($payload);
// dd($apiResponse);
        Log::debug("Payout Provider Response", [
            'provider' => $providerName,
            'payload'  => $payload,
            'response' => $apiResponse
        ]);

        if (!is_array($apiResponse)) {
            $report->update(['status' => 'failed']);
            return response()->json([
                'status'  => 'failed',
                'message' => 'Invalid Provider Response'
            ], 500);
        }

        if (($apiResponse['status'] ?? '') == 'failed') {
            $report->update([
                'status' => 'failed',
                'remark' => $apiResponse['error'] ?? "Provider Error"
            ]);

            return response()->json([
                'status'  => 'failed',
                'message' => $apiResponse['error'] ?? "Provider Error"
            ], 500);
        }

        // ------------------- MAP STATUS -------------------
        $providerStatus = strtolower($apiResponse['status'] ?? 'pending');
        switch ($providerStatus) {
            case 'received':
            case 'processed':
            case 'success':
                $status = 'success';
                break;
            case 'failed':
                $status = 'failed';
                break;
            default:
                $status = 'pending';
        }

        // ------------------- UPDATE REPORT -------------------
        $report->update([
            'status' => $status,
            'refno'  => $apiResponse['refno'] ?? null,
            'payid'  => $apiResponse['cf_transfer_id'] ?? null,
        ]);

        return response()->json([
            'status'     => 'success',
            'statuscode' => 200,
            'message'    => "Payout Successfully Initiated",
            'data'       => $apiResponse
        ], 200);

    } catch (\Exception $e) {

        Log::error("PAYOUT ERROR", [
            'line'  => $e->getLine(),
            'file'  => $e->getFile(),
            'error' => $e->getMessage()
        ]);

        return response()->json([
            'status'  => 'failed',
            'message' => 'Internal Server Error'
        ], 500);
    }
}


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
    

    
    
    
}