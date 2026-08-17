<?php

namespace App\Http\Controllers\Api\Payin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\User;
use App\Models\Credential;
use App\Models\Scheme;
use App\Models\AuthToken;
use App\Models\Report;
// use App\Models\User;
use Validator;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Http;


class ChargebackController extends Controller
{
//////////////////////////////////////////////////////////////////////////

        public function Chargeback_record(Request $request)
        {
            $request->validate([
                'order_id' => 'required'
            ]);
        
            DB::beginTransaction();
        
            try {
        
                Log::info("Chargeback Request Received", $request->all());
        
                // Get report
                // $report = Report::where('id', $request->order_id)->first();
                $report = Report::where('id', $request->order_id)
                        ->where('chargeback_status', 'not_accepted')
                        ->first();
                
                $report->update([
                    "chargeback_status" => "accepted"
                ]);
        
                if (!$report) {
                    return response()->json([
                        'status' => false,
                        'message' => 'Report not found'
                    ]);
                }
        
                // Get user
                $user = User::where('id', $report->user_id)->first();
        
                if (!$user) {
                    return response()->json([
                        'status' => false,
                        'message' => 'User not found'
                    ]);
                }
        
                // Charges
                $gst = 270;
                $charge = 1500;
                $profit=$gst + $charge;
        
                // Total debit amount
                $totalamount = $report->amount + $gst + $charge;
        
                // Wallet balance
                $openingBalance = (float) $user->payout_wallet;
        
                // Check balance
                // if ($openingBalance < $totalamount) {
                //     return response()->json([
                //         'status' => false,
                //         'message' => 'Insufficient wallet balance'
                //     ]);
                // }
        
                // Deduct balance
                $user->decrement('payout_wallet', $totalamount);
                $user->refresh();
        
                $closingBalance = (float) $user->payout_wallet;
                $CB_Id = 'CB' . now()->format('YmdHis') . rand(11111111, 99999999);
                // Prepare data
                $data = [
                    "gst" => $gst,
                    "charge" => $charge,
                    "mobile" => $report->mobile,
                    "txnid" => $report->txnid,
                    "payid" => $report->payid,
                    "glide_uiwidget_sessionid" => $CB_Id,
                    "apitxnid" => $report->apitxnid,
                    "mytxnid" => $report->mytxnid,
                    "amount" => $report->amount,
                    "user_id" => $report->user_id,
                    "profit" => $profit,
                    "payin_amount" => $report->payin_amount,
                    "payin_rolling_amount" => $report->payin_rolling_amount,
                    "transaction_type" => $report->transaction_type,
                    "status" => $report->status,
                    "remark" => $report->remark,
                    "product" => "chargeback",
                    "payment_platform" => $report->payment_platform,
                    "description" => $report->description,
                    "payer_email" => $report->payer_email,
                    "option1" => $report->option1,
                    "option2" => $totalamount,
                    "option4" => $report->option4,
                    "payout_opening_balance" => $openingBalance,
                    "payout_closing_balance" => $closingBalance,
                    "chargeback_status" => "accepted"
                ];
        // dd($data);
                // Create chargeback record
                Report::create($data);
        
                DB::commit();
        
                Log::info("Chargeback processed successfully", [
                    'order_id' => $request->order_id,
                    'debited_amount' => $totalamount
                ]);
        
                return response()->json([
                    'status' => true,
                    'chargeback_status' => 'accepted',
                    'message' => 'Chargeback processed successfully'
                ]);
        
            } catch (\Exception $e) {
        
                DB::rollBack();
        
                Log::error("Chargeback Failed: " . $e->getMessage());
        
                return response()->json([
                    'status' => false,
                    'message' => 'Something went wrong'
                ]);
            }
        }
        
        public function Reverse_chargeback(Request $request)
        {
            $request->validate([
                'chargeback_id' => 'required'
            ]);
            // dump($request->chargeback_id);
        
            DB::beginTransaction();
        
            try {
        
                Log::info("Chargeback Request Received", $request->all());
        
                // Get report
                $report = Report::where('glide_uiwidget_sessionid', $request->chargeback_id)
                    ->where('product', 'chargeback')
                    ->where('chargeback_status', 'accepted')
                    ->where('option3',null)
                    ->first();
        // dd($report);
                if (!$report) {
                    return response()->json([
                        'status' => false,
                        'message' => 'Report not found'
                    ]);
                }
        
                // Get user
                $user = User::where('id', $report->user_id)->first();
        
                if (!$user) {
                    return response()->json([
                        'status' => false,
                        'message' => 'User not found'
                    ]);
                }
                
                $report->update([
                        'option3' => 'reverse'
                    ]);
        
                // Charges
                $gst = 270;
                $charge = 1500;
        
                // Total credit amount
                $totalamount = $report->amount + $gst + $charge;
        
                // ✅ Opening balance
                $openingBalance = (float) $user->payout_wallet;
        
                // Add balance back
                $user->increment('payout_wallet', $totalamount);
                $user->refresh();
        
                // ✅ Closing balance
                $closingBalance = (float) $user->payout_wallet;
                $R_CB_Id = 'RCB' . now()->format('YmdHis') . rand(11111111, 99999999);
                // Prepare data
                $data = [
                    "gst" => $gst,
                    "charge" => $charge,
                    "mobile" => $report->mobile,
                    "txnid" => $report->glide_uiwidget_sessionid,
                    "payid" => $report->payid,
                    "mytxnid" => $report->mytxnid,
                    "glide_uiwidget_sessionid" => $R_CB_Id,
                    "amount" => $report->amount,
                    "user_id" => $report->user_id,
                    "profit" => $report->profit,
                    "payin_amount" => $report->payin_amount,
                    "payin_rolling_amount" => $report->payin_rolling_amount,
                    "transaction_type" => $report->transaction_type,
                    "status" => $report->status,
                    "remark" => $report->remark,
                    "product" => "chargeback",
                    "payment_platform" => $report->payment_platform,
                    "description" => "Chargeback Reversed",
                    "payer_email" => $report->payer_email,
                    "option1" => $report->option1,
                    "option2" => $totalamount,
                    "option4" => $report->option4,
                    "payout_opening_balance" => $openingBalance,
                    "payout_closing_balance" => $closingBalance,
                    "chargeback_status" => "reverse"
                ];
                // dd($data);
                // ✅ Create new reverse entry
                Report::create($data);
                
                // $upireport = Report::where('txnid', $report->txnid)
                //     ->where('product', 'upi')
                //     ->where('chargeback_status', 'accepted')
                //     ->first();
        
                // // ✅ Update old record status (optional but recommended)
                // $upireport->update([
                //     'chargeback_status' => 'not_accepted'
                // ]);
                
                $upireport = Report::where('txnid', $report->txnid)
                    ->where('chargeback_status', 'accepted')
                    ->where('product', 'upi')
                    ->first();
                
                if ($upireport) {
                    $upireport->update([
                        'chargeback_status' => 'not_accepted'
                    ]);
                } else {
                    Log::warning("UPI Report not found", [
                        'txnid' => $report->txnid,
                        'mytxnid' => $report->mytxnid
                    ]);
                }
        
                // ✅ OR delete old record (if you want)
                // $report->delete();
        
                DB::commit();
        
                Log::info("Chargeback reversed successfully", [
                    'order_id' => $request->order_id,
                    'amount' => $totalamount
                ]);
        
                return response()->json([
                    'status' => true,
                    'message' => 'Chargeback reversed successfully'
                ]);
        
            } catch (\Exception $e) {
        
                DB::rollBack();
        
                Log::error("Chargeback Failed: " . $e->getMessage());
        
                return response()->json([
                    'status' => false,
                    'message' => 'Something went wrong'
                ]);
            }
        }
    
}