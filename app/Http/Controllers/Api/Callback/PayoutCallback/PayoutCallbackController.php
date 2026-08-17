<?php

namespace App\Http\Controllers\Api\Callback\PayoutCallback;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use App\Models\User;
use App\Models\Report;

class PayoutCallbackController extends Controller
{
    /*
    |--------------------------------------------------------------------------
    | Custom Log Function (Safe for Arrays)
    |--------------------------------------------------------------------------
    */
    private function payoutLog($message, $data = null)
    {
        $logPath = storage_path('logs/callback/payout');

        if (!file_exists($logPath)) {
            mkdir($logPath, 0777, true);
        }

        $file = $logPath . '/callback_' . date('Y-m-d') . '.log';

        $text = "[" . date('Y-m-d H:i:s') . "] " . $message;

        if (!is_null($data)) {
            $text .= " " . json_encode($data, JSON_PRETTY_PRINT);
        }

        $text .= "\n";

        file_put_contents($file, $text, FILE_APPEND);
    }

    /*
    |--------------------------------------------------------------------------
    | Merchant Callback
    |--------------------------------------------------------------------------
    */
    public function merchantCallBackResponse(
        $callbackurl,
        $status,
        $txnid,
        $mytxnid,
        $amount,
        $referenceId,
        $timestamp
    ) {

        $postData = [
            'status'       => $status,
            'txnid'        => $txnid,
            'clienttxnid'  => $mytxnid,
            'amount'       => $amount,
            'UTR'          => $referenceId,
            'timestamp'    => $timestamp,
        ];

        $this->payoutLog("Sending Merchant Callback", [
            'url' => $callbackurl,
            'payload' => $postData
        ]);

        $ch = curl_init($callbackurl);

        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($postData));
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 15);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Content-Type: application/json',
            'Accept: application/json'
        ]);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);

        if (curl_errno($ch)) {
            $this->payoutLog("Merchant Callback CURL Error", [
                'error' => curl_error($ch)
            ]);
        } else {
            $this->payoutLog("Merchant Callback Response", [
                'http_code' => $httpCode,
                'response'  => $response
            ]);
        }

        curl_close($ch);
    }

    /*
    |--------------------------------------------------------------------------
    | e2pay Callback
    |--------------------------------------------------------------------------
    */
    public function e2payCallback(Request $request)
    {
        // dd('heello');
        try {

            $this->payoutLog("========== NEW e2pay CALLBACK ==========");

            // Raw Body Log
            $rawBody = $request->getContent();
            $this->payoutLog("Raw Body", ['body' => $rawBody]);

            // Decode JSON
            $data = json_decode($rawBody, true);

            if (!$data) {
                $this->payoutLog("Invalid JSON Received");
                return response()->json(['status' => false], 400);
            }

            $this->payoutLog("Parsed Payload", $data);

            // ✅ According to your actual response
            $statusCode = $data['statusCode'] ?? null;
            $message    = $data['message'] ?? null;
            $orderId    = $data['orderId'] ?? null;
            $utr        = $data['Utr'] ?? null;

            if (!$orderId) {
                $this->payoutLog("Missing orderId");
                return response()->json(['status' => false], 400);
            }

            // Find pending report
            $report = Report::where('mytxnid', $orderId)
                ->where('product', 'payout')
                ->where('status', 'pending')
                ->first();

            if (!$report) {
                $this->payoutLog("No Pending Report Found", ['orderId' => $orderId]);
                return response()->json(['status' => true]);
            }

            $user = User::find($report->user_id);

            if (!$user) {
                $this->payoutLog("User Not Found", ['report_id' => $report->id]);
                return response()->json(['status' => false], 404);
            }

            /*
            |--------------------------------------------------------------------------
            | SUCCESS CASE (statusCode = 200)
            |--------------------------------------------------------------------------
            */
            if ($statusCode == 200 && strtolower($message) == 'success') {

                $report->update([
                    'status'      => 'success',
                    'payout_mode' => 'IMPS', // or UPI if fixed
                    'refno'       => $utr,
                    'payid'       => $utr,
                    'option4'     => 'E2PAY Success',
                ]);

                $this->payoutLog("Report Marked SUCCESS", [
                    'report_id' => $report->id
                ]);
            }
            /*
            |--------------------------------------------------------------------------
            | FAILED CASE
            |--------------------------------------------------------------------------
            */
            else {

                $totalRefund = $report->amount + $report->charge;

                // Refund wallet
                $user->increment('mainwallet', $totalRefund);

                $report->update([
                    'status'      => 'refunded',
                    'remark'      => "Refunded ₹{$totalRefund} to wallet",
                    'payout_mode' => 'IMPS',
                    'option4'     => 'E2PAY failed',
                ]);

                $this->payoutLog("Refund Processed", [
                    'user_id' => $user->id,
                    'amount'  => $totalRefund
                ]);
            }

            /*
            |--------------------------------------------------------------------------
            | Merchant Callback
            |--------------------------------------------------------------------------
            */
            if (!empty($user->payout_callback)) {

                $merchantStatus = ($statusCode == 200) ? 'SUCCESS' : 'FAILED';

                $this->merchantCallBackResponse(
                    $user->payout_callback,
                    $merchantStatus,
                    $report->txnid,
                    $orderId,
                    $report->amount,
                    $utr,
                    now()
                );
            }

            return response()->json(['status' => true]);

        } catch (\Exception $e) {

            $this->payoutLog("e2pay Callback Exception", [
                'message' => $e->getMessage(),
                'trace'   => $e->getTraceAsString()
            ]);

            return response()->json(['status' => false], 500);
        }
    }

    public function e2payVanCallback(Request $request)
    {
        // dd("Hello Yuvraj Mishra");
        try {

            $this->payoutLog("========== NEW e2pay VAN CALLBACK ==========");

            // Raw Body Log
            $rawBody = $request->getContent();
            $this->payoutLog("Raw Body", ['body' => $rawBody]);

            // Decode JSON
            $data = json_decode($rawBody, true);

            if (!$data) {
                $this->payoutLog("Invalid JSON Received");
                return response()->json(['status' => false], 400);
            }

            $this->payoutLog("Parsed Payload", $data);

            // ✅ According to your actual response
            $statusCode = $data['statusCode'] ?? null;
            $message    = $data['message'] ?? null;
            $orderId    = $data['orderId'] ?? null;
            $utr        = $data['Utr'] ?? null;

            if (!$orderId) {
                $this->payoutLog("Missing orderId");
                return response()->json(['status' => false], 400);
            }

            // Find pending report
            $report = Report::where('mytxnid', $orderId)
                ->where('product', 'payout')
                ->where('status', 'pending')
                ->first();

            if (!$report) {
                $this->payoutLog("No Pending Report Found", ['orderId' => $orderId]);
                return response()->json(['status' => true]);
            }

            $user = User::find($report->user_id);

            if (!$user) {
                $this->payoutLog("User Not Found", ['report_id' => $report->id]);
                return response()->json(['status' => false], 404);
            }

            /*
            |--------------------------------------------------------------------------
            | SUCCESS CASE (statusCode = 200)
            |--------------------------------------------------------------------------
            */
            if ($statusCode == 200 && strtolower($message) == 'success') {

                $report->update([
                    'status'      => 'success',
                    'payout_mode' => 'IMPS', // or UPI if fixed
                    'refno'       => $utr,
                    'payid'       => $utr,
                    'option4'     => 'E2PAY Success',
                ]);

                $this->payoutLog("Report Marked SUCCESS", [
                    'report_id' => $report->id
                ]);
            }
            /*
            |--------------------------------------------------------------------------
            | FAILED CASE
            |--------------------------------------------------------------------------
            */
            else {

                $totalRefund = $report->amount + $report->charge;

                // Refund wallet
                $user->increment('mainwallet', $totalRefund);

                $report->update([
                    'status'      => 'refunded',
                    'remark'      => "Refunded ₹{$totalRefund} to wallet",
                    'payout_mode' => 'IMPS',
                    'option4'     => 'E2PAY failed',
                ]);

                $this->payoutLog("Refund Processed", [
                    'user_id' => $user->id,
                    'amount'  => $totalRefund
                ]);
            }

            /*
            |--------------------------------------------------------------------------
            | Merchant Callback
            |--------------------------------------------------------------------------
            */
            if (!empty($user->payout_callback)) {

                $merchantStatus = ($statusCode == 200) ? 'SUCCESS' : 'FAILED';

                $this->merchantCallBackResponse(
                    $user->payout_callback,
                    $merchantStatus,
                    $report->txnid,
                    $orderId,
                    $report->amount,
                    $utr,
                    now()
                );
            }

            return response()->json(['status' => true]);

        } catch (\Exception $e) {

            $this->payoutLog("e2pay Callback Exception", [
                'message' => $e->getMessage(),
                'trace'   => $e->getTraceAsString()
            ]);

            return response()->json(['status' => false], 500);
        }
    }

    /*
    |--------------------------------------------------------------------------
    | IDFC Callback
    |--------------------------------------------------------------------------
    */
    public function IdfcCallback(Request $request)
    {
        try {
        //    dd("helo");
            $this->payoutLog("========== NEW IDFC CALLBACK ==========");

            // Raw Body Log
            $rawBody = $request->getContent();
            $this->payoutLog("Raw Body", ['body' => $rawBody]);

            // Decode JSON
            $data = json_decode($rawBody, true);

            if (!$data) {
                $this->payoutLog("Invalid JSON Received");
                return response()->json(['status' => false], 400);
            }

            $this->payoutLog("Parsed Payload", $data);

            // ✅ According to your actual response
            $statusCode = $data['statusCode'] ?? null;
            $message    = $data['message'] ?? null;
            $orderId    = $data['orderId'] ?? null;
            $utr        = $data['Utr'] ?? null;

            if (!$orderId) {
                $this->payoutLog("Missing orderId");
                return response()->json(['status' => false], 400);
            }

            // Find pending report
            $report = Report::where('mytxnid', $orderId)
                ->where('product', 'payout')
                ->where('status', 'pending')
                ->first();

            if (!$report) {
                $this->payoutLog("No Pending Report Found", ['orderId' => $orderId]);
                return response()->json(['status' => true]);
            }

            $user = User::find($report->user_id);

            if (!$user) {
                $this->payoutLog("User Not Found", ['report_id' => $report->id]);
                return response()->json(['status' => false], 404);
            }

            /*
            |--------------------------------------------------------------------------
            | SUCCESS CASE (statusCode = 200)
            |--------------------------------------------------------------------------
            */
            if ($statusCode == 200 && strtolower($message) == 'success') {

                $report->update([
                    'status'      => 'success',
                    'payout_mode' => 'IMPS', // or UPI if fixed
                    'refno'       => $utr,
                    'payid'       => $utr,
                    'option4'     => 'E2PAY Success',
                ]);

                $this->payoutLog("Report Marked SUCCESS", [
                    'report_id' => $report->id
                ]);
            }
            /*
            |--------------------------------------------------------------------------
            | FAILED CASE
            |--------------------------------------------------------------------------
            */
            else {

                $totalRefund = $report->amount + $report->charge;

                // Refund wallet
                $user->increment('mainwallet', $totalRefund);

                $report->update([
                    'status'      => 'refunded',
                    'remark'      => "Refunded ₹{$totalRefund} to wallet",
                    'payout_mode' => 'IMPS',
                    'option4'     => 'E2PAY failed',
                ]);

                $this->payoutLog("Refund Processed", [
                    'user_id' => $user->id,
                    'amount'  => $totalRefund
                ]);
            }

            /*
            |--------------------------------------------------------------------------
            | Merchant Callback
            |--------------------------------------------------------------------------
            */
            if (!empty($user->payout_callback)) {

                $merchantStatus = ($statusCode == 200) ? 'SUCCESS' : 'FAILED';

                $this->merchantCallBackResponse(
                    $user->payout_callback,
                    $merchantStatus,
                    $report->txnid,
                    $orderId,
                    $report->amount,
                    $utr,
                    now()
                );
            }

            return response()->json(['status' => true]);

        } catch (\Exception $e) {

            $this->payoutLog("e2pay Callback Exception", [
                'message' => $e->getMessage(),
                'trace'   => $e->getTraceAsString()
            ]);

            return response()->json(['status' => false], 500);
        }
    }
    
    
    /*
    |--------------------------------------------------------------------------
    | Bridg Money Callback
    |--------------------------------------------------------------------------
    */
    // public function bridgMoneyCallback(Request $request)
    // {
    //     // dd('Hello Yuvraj');
    //     try {

    //         $this->payoutLog("========== BRIDG MONEY CALLBACK ==========");

    //         // Raw Body Log
    //         $rawBody = $request->getContent();
    //         $this->payoutLog("Raw Body", ['body' => $rawBody]);

    //         // Decode JSON
    //         $data = json_decode($rawBody, true);

    //         if (!$data) {
    //             $this->payoutLog("Invalid JSON Received");
    //             return response()->json(['status' => false], 400);
    //         }

    //         $this->payoutLog("Parsed Payload", $data);

    //         // ✅ According to your actual response
    //         $statusCode = $data['statusCode'] ?? null;
    //         $message    = $data['message'] ?? null;
    //         $orderId    = $data['orderId'] ?? null;
    //         $utr        = $data['Utr'] ?? null;

    //         if (!$orderId) {
    //             $this->payoutLog("Missing orderId");
    //             return response()->json(['status' => false], 400);
    //         }

    //         // Find pending report
    //         $report = Report::where('mytxnid', $orderId)
    //             ->where('product', 'payout')
    //             ->where('status', 'pending')
    //             ->first();

    //         if (!$report) {
    //             $this->payoutLog("No Pending Report Found", ['orderId' => $orderId]);
    //             return response()->json(['status' => true]);
    //         }

    //         $user = User::find($report->user_id);

    //         if (!$user) {
    //             $this->payoutLog("User Not Found", ['report_id' => $report->id]);
    //             return response()->json(['status' => false], 404);
    //         }

    //         /*
    //         |--------------------------------------------------------------------------
    //         | SUCCESS CASE (statusCode = 200)
    //         |--------------------------------------------------------------------------
    //         */
    //         if ($statusCode == 200 && strtolower($message) == 'success') {

    //             $report->update([
    //                 'status'      => 'success',
    //                 'payout_mode' => 'IMPS', // or UPI if fixed
    //                 'refno'       => $utr,
    //                 'payid'       => $utr,
    //                 'option4'     => 'E2PAY Success',
    //             ]);

    //             $this->payoutLog("Report Marked SUCCESS", [
    //                 'report_id' => $report->id
    //             ]);
    //         }
    //         /*
    //         |--------------------------------------------------------------------------
    //         | FAILED CASE
    //         |--------------------------------------------------------------------------
    //         */
    //         else {

    //             $totalRefund = $report->amount + $report->charge;

    //             // Refund wallet
    //             $user->increment('mainwallet', $totalRefund);

    //             $report->update([
    //                 'status'      => 'refunded',
    //                 'remark'      => "Refunded ₹{$totalRefund} to wallet",
    //                 'payout_mode' => 'IMPS',
    //                 'option4'     => 'E2PAY failed',
    //             ]);

    //             $this->payoutLog("Refund Processed", [
    //                 'user_id' => $user->id,
    //                 'amount'  => $totalRefund
    //             ]);
    //         }

    //         /*
    //         |--------------------------------------------------------------------------
    //         | Merchant Callback
    //         |--------------------------------------------------------------------------
    //         */
    //         if (!empty($user->payout_callback)) {

    //             $merchantStatus = ($statusCode == 200) ? 'SUCCESS' : 'FAILED';

    //             $this->merchantCallBackResponse(
    //                 $user->payout_callback,
    //                 $merchantStatus,
    //                 $report->txnid,
    //                 $orderId,
    //                 $report->amount,
    //                 $utr,
    //                 now()
    //             );
    //         }

    //         return response()->json(['status' => true]);

    //     } catch (\Exception $e) {

    //         $this->payoutLog("Bridg Money Callback Exception", [
    //             'message' => $e->getMessage(),
    //             'trace'   => $e->getTraceAsString()
    //         ]);

    //         return response()->json(['status' => false], 500);
    //     }
    // }
    
    public function bridgMoneyCallback(Request $request)
    {
        try {
    
            $this->payoutLog("========== BRIDG WEBHOOK START ==========");
    
            /*
            |--------------------------------------------------------------------------
            | STEP 1: RAW BODY
            |--------------------------------------------------------------------------
            */
            $rawBody = $request->getContent();
    
            $this->payoutLog("STEP 1 - RAW BODY", [
                'body' => $rawBody
            ]);
    
            /*
            |--------------------------------------------------------------------------
            | STEP 2: JSON DECODE
            |--------------------------------------------------------------------------
            */
            $data = json_decode($rawBody, true);
    
            if (json_last_error() !== JSON_ERROR_NONE) {
                return response()->json(['status' => false, 'message' => 'Invalid JSON'], 400);
            }
    
            $this->payoutLog("STEP 2 - PARSED DATA", $data);
    
            /*
            |--------------------------------------------------------------------------
            | STEP 3: FIELD EXTRACTION
            |--------------------------------------------------------------------------
            */
            $transactionId = $data['transactionId'] ?? null;
            $statusCode    = $data['status'] ?? null;
            $event         = strtolower($data['event'] ?? '');
            $utr           = $data['utr'] ?? null;
            $responseMessage = $data['responseMessage']
        ?? ($data['rawPayload']['data']['responseMessage'] ?? null)
        ?? ($data['message'] ?? null);
    
            if (!$transactionId) {
                $this->payoutLog("MISSING transactionId");
                return response()->json(['status' => false], 400);
            }
    
            $this->payoutLog("STEP 3 - EXTRACTED", [
                'transactionId' => $transactionId,
                'status' => $statusCode,
                'event' => $event,
                'utr' => $utr,
                'option3' =>$responseMessage
            ]);
    
            /*
            |--------------------------------------------------------------------------
            | STEP 4: FIND REPORT (ALSO USED FOR IDEMPOTENCY)
            |--------------------------------------------------------------------------
            */
            $report = Report::where('apitxnid', $transactionId)
                ->where('product', 'payout')
                ->first();
    
            if (!$report) {
                $this->payoutLog("REPORT NOT FOUND", [
                    'transactionId' => $transactionId
                ]);
    
                return response()->json(['status' => true]);
            }
    
            /*
            |--------------------------------------------------------------------------
            | STEP 5: IDEMPOTENCY (DB BASED)
            |--------------------------------------------------------------------------
            */
            if ($report->status === 'success' || $report->status === 'refunded') {
                $this->payoutLog("ALREADY PROCESSED", [
                    'status' => $report->status
                ]);
    
                return response()->json(['status' => true, 'message' => 'Already processed']);
            }
    
            /*
            |--------------------------------------------------------------------------
            | STEP 6: USER FETCH
            |--------------------------------------------------------------------------
            */
            $user = User::find($report->user_id);
    
            if (!$user) {
                $this->payoutLog("USER NOT FOUND");
                return response()->json(['status' => false], 404);
            }
    
            /*
            |--------------------------------------------------------------------------
            | STEP 7: SUCCESS CHECK
            |--------------------------------------------------------------------------
            */
            $isSuccess = ($statusCode == 11 || $event === 'successful');
    
            $this->payoutLog("STEP 7 - SUCCESS CHECK", [
                'isSuccess' => $isSuccess
            ]);
    
            /*
            |--------------------------------------------------------------------------
            | STEP 8: PROCESS RESULT
            |--------------------------------------------------------------------------
            */
            if ($isSuccess) {
    
                $report->update([
                    'status'      => 'success',
                    'refno'       =>  $utr,
                    'option1'     => 'BridgMoney Success',
                    'option3'     =>  $responseMessage ?? 'Payout Successful via BridgMoney',
                ]);
    
                $this->payoutLog("SUCCESS UPDATED");
    
            } else {
    
                $totalRefund = (float) $report->amount + (float) $report->charge;
    
                $user->increment('payout_wallet', $totalRefund);
    
                $report->update([
                    'status'       => 'refunded',
                    'option3'      => ($responseMessage ?? 'Payout Failed') . " | Refunded ₹{$totalRefund}",
                    'option1'      => 'BridgMoney Failed',
                    // 'description'  => "Refunded ₹{$totalRefund} to wallet",
                ]);
    
                $this->payoutLog("REFUND DONE", [
                    'amount' => $totalRefund
                ]);
            }
    
            /*
            |--------------------------------------------------------------------------
            | STEP 9: MERCHANT CALLBACK (SAFE)
            |--------------------------------------------------------------------------
            */
            try {
    
                if (!empty($user->payout_callback)) {
    
                    $this->merchantCallBackResponse(
                        $user->payout_callback,
                        $isSuccess ? 'SUCCESS' : 'FAILED',
                        $report->txnid ?? null,
                        $transactionId,
                        $report->amount,
                        $utr,
                        now()
                    );
                }
    
            } catch (\Throwable $ex) {
                $this->payoutLog("CALLBACK FAILED", [
                    'error' => $ex->getMessage()
                ]);
            }
    
            return response()->json(['status' => true]);
    
        } catch (\Throwable $e) {
    
            $this->payoutLog("FATAL ERROR", [
                'message' => $e->getMessage(),
                'line' => $e->getLine()
            ]);
    
            return response()->json(['status' => false, 'message' => 'Server Error'], 500);
        }
    }



    /*
    |--------------------------------------------------------------------------
    | Shaymavenue Callback
    |--------------------------------------------------------------------------
    */

    public function shaymavenueCallback(Request $request)
    {
        try {

            dump("Yuvraj");

            $this->payoutLog("==========SHAYAMAVENUE WEBHOOK START ==========");

            /*
            |--------------------------------------------------------------------------
            | STEP 1: RAW BODY
            |--------------------------------------------------------------------------
            */
            $rawBody = $request->getContent();

            $this->payoutLog("STEP 1 - RAW BODY", [
                'body' => $rawBody
            ]);

            /*
            |--------------------------------------------------------------------------
            | STEP 2: JSON DECODE
            |--------------------------------------------------------------------------
            */
            $data = json_decode($rawBody, true);

            if (json_last_error() !== JSON_ERROR_NONE) {
                $this->payoutLog("INVALID JSON", [
                    'error' => json_last_error_msg()
                ]);

                return response()->json([
                    'status'  => false,
                    'message' => 'Invalid JSON'
                ], 400);
            }

            $this->payoutLog("STEP 2 - PARSED DATA", $data);

            /*
            |--------------------------------------------------------------------------
            | STEP 3: GET FIRST TRANSACTION
            |--------------------------------------------------------------------------
            */

            if (!isset($data[0]) || !is_array($data[0])) {

                $this->payoutLog("INVALID RESPONSE FORMAT", [
                    'data' => $data
                ]);

                return response()->json([
                    'status'  => false,
                    'message' => 'Invalid response format'
                ], 400);
            }

            $transaction = $data[0];

            /*
            |--------------------------------------------------------------------------
            | STEP 4: FIELD EXTRACTION
            |--------------------------------------------------------------------------
            */

            $transactionId = $transaction['Txn_ID'] ?? null;
            $txnDate       = $transaction['TXN_date'] ?? null;
            $txnAmount     = $transaction['TXN_amount'] ?? null;
            $utr           = $transaction['UTR'] ?? null;
            $txnStatus     = strtolower(trim($transaction['TXN_Status'] ?? ''));

            if (!$transactionId) {

                $this->payoutLog("MISSING Txn_ID");

                return response()->json([
                    'status'  => false,
                    'message' => 'Missing Txn_ID'
                ], 400);
            }

            $this->payoutLog("STEP 4 - EXTRACTED", [
                'transactionId' => $transactionId,
                'txnDate'       => $txnDate,
                'txnAmount'     => $txnAmount,
                'utr'           => $utr,
                'txnStatus'     => $txnStatus,
            ]);

            /*
            |--------------------------------------------------------------------------
            | STEP 5: FIND REPORT
            |--------------------------------------------------------------------------
            */

            $report = Report::where('apitxnid', $transactionId)
                ->where('product', 'payout')
                ->first();

            if (!$report) {

                $this->payoutLog("REPORT NOT FOUND", [
                    'transactionId' => $transactionId
                ]);

                // Return true so provider does not keep retrying
                return response()->json([
                    'status' => true
                ]);
            }

            /*
            |--------------------------------------------------------------------------
            | STEP 6: IDEMPOTENCY
            |--------------------------------------------------------------------------
            */

            if (in_array($report->status, ['success', 'refunded'])) {

                $this->payoutLog("ALREADY PROCESSED", [
                    'status' => $report->status
                ]);

                return response()->json([
                    'status'  => true,
                    'message' => 'Already processed'
                ]);
            }

            /*
            |--------------------------------------------------------------------------
            | STEP 7: USER FETCH
            |--------------------------------------------------------------------------
            */

            $user = User::find($report->user_id);

            if (!$user) {

                $this->payoutLog("USER NOT FOUND", [
                    'user_id' => $report->user_id
                ]);

                return response()->json([
                    'status' => false
                ], 404);
            }

            /*
            |--------------------------------------------------------------------------
            | STEP 8: SUCCESS CHECK
            |--------------------------------------------------------------------------
            |
            | Your response contains:
            | TXN_Status = "success"
            |
            |--------------------------------------------------------------------------
            */

            $isSuccess = ($txnStatus === 'success');

            $this->payoutLog("STEP 8 - STATUS CHECK", [
                'txnStatus' => $txnStatus,
                'isSuccess' => $isSuccess
            ]);

            /*
            |--------------------------------------------------------------------------
            | STEP 9: PROCESS RESULT
            |--------------------------------------------------------------------------
            */

            if ($isSuccess) {

                /*
                | SUCCESS
                */

                $report->update([
                    'status'  => 'success',
                    'refno'   => $utr,
                    'option1' => 'Shaymavenue Success',
                    'option3' => 'Payout Successful via Shaymavenue',
                ]);

                $this->payoutLog("SUCCESS UPDATED", [
                    'transactionId' => $transactionId,
                    'utr'           => $utr,
                ]);

            } else {

                /*
                | FAILED -> REFUND
                */

                $totalRefund = (float) $report->amount + (float) $report->charge;

                $user->increment('payout_wallet', $totalRefund);

                $report->update([
                    'status'  => 'refunded',
                    'option1' => 'Shaymavenue Failed',
                    'option3' => 'Payout Failed | Refunded ₹' . $totalRefund,
                ]);

                $this->payoutLog("REFUND DONE", [
                    'transactionId' => $transactionId,
                    'amount'        => $totalRefund,
                    'status'        => $txnStatus,
                ]);
            }

            /*
            |--------------------------------------------------------------------------
            | STEP 10: MERCHANT CALLBACK
            |--------------------------------------------------------------------------
            */

            try {

                if (!empty($user->payout_callback)) {

                    $this->merchantCallBackResponse(
                        $user->payout_callback,
                        $isSuccess ? 'SUCCESS' : 'FAILED',
                        $report->txnid ?? null,
                        $transactionId,
                        $report->amount,
                        $utr,
                        now()
                    );
                }

            } catch (\Throwable $ex) {

                $this->payoutLog("CALLBACK FAILED", [
                    'error' => $ex->getMessage()
                ]);
            }

            /*
            |--------------------------------------------------------------------------
            | STEP 11: RESPONSE
            |--------------------------------------------------------------------------
            */

            return response()->json([
                'status' => true
            ]);

        } catch (\Throwable $e) {

            $this->payoutLog("FATAL ERROR", [
                'message' => $e->getMessage(),
                'line'    => $e->getLine(),
                'file'    => $e->getFile()
            ]);

            return response()->json([
                'status'  => false,
                'message' => 'Server Error'
            ], 500);
        }
    }


}