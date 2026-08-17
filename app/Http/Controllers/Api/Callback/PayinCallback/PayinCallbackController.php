<?php

namespace App\Http\Controllers\Api\Callback\PayinCallback;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;
use App\Models\User;
use App\Models\Report;

class PayinCallbackController extends Controller
{
    /**
     * Send callback to merchant.
     */
    private function payinLog($message, $data = []){
        $log_path = storage_path('logs/callback/payin');
        if(!file_exists($log_path)){
            mkdir($log_path, 0777,true);
        }
        $file = $log_path . '/AIRPay_' . date('Y-m-d') . '.log';
        $text = ' [ '. date('Y-m-d H:i:s') . ' ] '. " : " . $message;
        if(!empty($data)){
            $text .= " " .json_encode($data);
        }
        $text .= "\n\n";
        file_put_contents($file, $text, FILE_APPEND);
    }
     
     
    private function paytmLog($message, $data = []){
        $logPath = storage_path('logs/callback/payin');
        if (!file_exists($logPath)) {
            mkdir($logPath, 0777, true);
        }
        $file = $logPath . '/Paytm_' . date('Y-m-d') . '.log';
        $text = '[' . date('Y-m-d H:i:s') . '] : ' . $message;
        if (!empty($data)) {
            $text .= ' ' . json_encode($data);
        }
        $text .= "\n\n";
        file_put_contents($file, $text, FILE_APPEND);
    }
    
    private function riseXLog($message, $data = []){
         $log_path = storage_path('logs/callback/payin');
         if(!file_exists($log_path)){
             mkdir($log_path, 0777,true);
         }
         $file = $log_path . '/RiseXPay_' . date('Y-m-d') . '.log';
         $text = ' [ '. date('Y-m-d H:i:s') . ' ] '. " : " . $message;
         if(!empty($data)){
             $text .= " " .json_encode($data);
         }
         $text .= "\n\n";
         file_put_contents($file, $text, FILE_APPEND);
     }
     
    private function shaymavenueLog($message, $data = [])
    {
        $log_path = storage_path('logs/callback/payin');
        if (!file_exists($log_path)) {
            mkdir($log_path, 0777, true);
        }
        $file = $log_path . '/Shaymavenue_' . date('Y-m-d') . '.log';
        $text = ' [ ' . date('Y-m-d H:i:s') . ' ] : ' . $message;
        if (!empty($data)) {
            $text .= ' ' . json_encode($data);
        }
        $text .= "\n\n";
        file_put_contents($file, $text, FILE_APPEND);
    }
     
////////////////////////////////////////////////////////////////////////////////            
    public function merchantCallBackResponse($callbackurl, $status, $txnid, $mytxnid, $amount, $referenceId, $timestamp)
    {
        $this->payinLog("Callback function called", [
            'callbackurl' => $callbackurl,
            'status' => $status,
            'txnid' => $txnid,
            'clienttxnid' => $mytxnid,
            'amount' => $amount,
            'UTR' => $referenceId,
            'timestamp' => $timestamp,
        ]);

        $postData = [
            'status' => $status,
            'txnid' => $txnid,
            'clienttxnid' => $mytxnid,
            'amount' => $amount,
            'UTR' => $referenceId,
            'timestamp' => $timestamp,
        ];

        $ch = curl_init($callbackurl);

        curl_setopt_array($ch, [
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => json_encode($postData),
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_USERAGENT => 'OmishaWebhookBot/1.0',
            CURLOPT_TIMEOUT => 300,
            CURLOPT_HTTPHEADER => [
                'Content-Type: application/json',
                'Accept: application/json',
            ],
        ]);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);

        if (curl_errno($ch)) {
            $error = curl_error($ch);
            Log::error("Callback Error: $error", [
                'callbackurl' => $callbackurl,
                'data' => $postData
            ]);
        } else {
            $this->payinLog("Callback sent", [
                'url' => $callbackurl,
                'data' => $postData,
                'http_code' => $httpCode,
                'response' => $response
            ]);
        }

        curl_close($ch);
    }

    /**
     * Handle Airpay callback.
     */
////////////////////////////////////////////////////////////////////////////////
    public function airpaycallbkp(Request $request)
    {
        $this->payinLog("airpay log");
    
        $this->payinLog('Airpay Callback Received', [
            'request_array' => $request->all(),
            'raw_content' => $request->getContent()
        ]);
    
        DB::table('micro_logs')->insert([
            'product_response' => json_encode($request->all()),
            'product_name' => 'Airpay',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    
        $data = json_decode($request->getContent(), true);
    
        if (!$data) {
            $this->payinLog('Failed to decode Airpay response', ['input' => $request->getContent()]);
            return response()->json(['status' => false, 'message' => 'Invalid response']);
        }
    
        $payid = $data['decodedResponse']['data']['orderid'] ?? null;
        $airpayStatus = $data['decodedResponse']['data']['transaction_payment_status'] ?? null;
        $timestamp = $data['timestamp'] ?? null;
    
        if (!$payid) {
            $this->payinLog('payid missing in Airpay response', ['response' => $data]);
            return response()->json(['status' => false, 'message' => 'Order ID missing']);
        }
    
        $report = Report::where('mytxnid', $payid)
            ->where('status', 'initiated')
            ->where('product', 'UPI')
            ->first();
    
        if (!$report) {
            $this->payinLog('No report found for payid', ['payid' => $payid]);
            return response()->json(['status' => false, 'message' => 'Report not found']);
        }
    
        $user = User::find($report->user_id);
        $refno = $data['decodedResponse']['data']['rrn'] ?? null;
    
        $updateOrder = [
            'option2' => $data['decodedResponse']['data']['transaction_payment_status'] ?? $data['decodedResponse']['data']['reason'] ?? $data['decodedResponse']['data']['transaction_reason'] ?? null,
            'option3' => $data['decodedResponse']['data']['ap_securehash'] ?? null,
        ];
    
        if ($airpayStatus === 'FAIL') {
            $updateOrder['option2'] = $data['decodedResponse']['data']['transaction_payment_status'] ?? $data['decodedResponse']['data']['reason'] ?? $data['decodedResponse']['data']['transaction_reason'] ?? null;
        }
    
        if ($refno) {
            $updateOrder['refno'] = $refno;
        }
    
        $updateOrder['status'] = $airpayStatus === 'SUCCESS'
            ? 'success'
            : ($airpayStatus === 'FAIL' ? 'failed' : $report->status);
    
        // ✅ Update report first
        $report->update($updateOrder);
    
        $this->payinLog("Report updated", [
            'report_id' => $report->id,
            'update' => $updateOrder
        ]);
    
        /*
        |--------------------------------------------------------------------------
        | 🔥 WALLET UPDATE + DESCRIPTION UPDATE
        |--------------------------------------------------------------------------
        */
    
        if ($airpayStatus === 'SUCCESS') {
    
            $this->payinLog('Wallet credit started', [
                'user_id' => $user->id,
                'report_id' => $report->id,
                'amount' => $report->amount
            ]);
    
            // Update wallet
            $user->rolling_amount = ($user->rolling_amount ?? 0) + ($report->payin_rolling_amount ?? 0);
            $user->payin_wallet   = ($user->payin_wallet ?? 0) + ($report->payin_amount ?? 0);
            $user->total_charges  = ($user->total_charges ?? 0) + ($report->profit ?? 0);
            $user->save();
    
            // ✅ Update description = Payment counted
            $report->update([
                'description' => 'Payment counted'
            ]);
    
            $this->payinLog('Wallet credit completed', [
                'rolling_amount' => $user->rolling_amount,
                'payin_wallet' => $user->payin_wallet,
                'total_charges' => $user->total_charges,
            ]);
        }
    
        /*
        |--------------------------------------------------------------------------
        | END WALLET UPDATE
        |--------------------------------------------------------------------------
        */
    
        if ($user->payin_callback) {
            $this->payinLog('Calling merchant callback', [
                'callbackurl' => $user->payin_callback,
                'status' => $airpayStatus,
                'txnid' => $report->txnid,
                'mytxnid' => $report->mytxnid,
                'amount' => $report->amount,
                'refno' => $refno,
                'timestamp' => $timestamp
            ]);
    
            $this->merchantCallBackResponse(
                $user->payin_callback,
                $airpayStatus,
                $report->txnid,
                $report->mytxnid,
                $report->amount,
                $refno,
                $timestamp
            );
        }
    
        $this->payinLog('Airpay callback processing finished');
    
        return response()->json(['status' => true, 'message' => 'AIRPAY Ready to work']);
    }
////////////////////////////////////////////////////////////////////////////////    
    public function omishacallbkp(Request $request)
    {
        $this->payinLog("omisha log");
    
        $this->payinLog('omisha Callback Received', [
            'request_array' => $request->all(),
            'raw_content' => $request->getContent()
        ]);
    
        DB::table('micro_logs')->insert([
            'product_response' => json_encode($request->all()),
            'product_name' => 'Airpay',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    
        $data = json_decode($request->getContent(), true);
    
        if (!$data) {
            $this->payinLog('Failed to decode omisha response', ['input' => $request->getContent()]);
            return response()->json(['status' => false, 'message' => 'Invalid response']);
        }
    
        $payid = $data['decodedResponse']['data']['orderid'] ?? null;
        $airpayStatus = $data['decodedResponse']['data']['transaction_payment_status'] ?? null;
        $timestamp = $data['timestamp'] ?? null;
    
        if (!$payid) {
            $this->payinLog('payid missing in Airpay response', ['response' => $data]);
            return response()->json(['status' => false, 'message' => 'Order ID missing']);
        }
    
        $report = Report::where('mytxnid', $payid)
            ->where('status', 'initiated')
            ->where('product', 'UPI')
            ->first();
    
        if (!$report) {
            $this->payinLog('No report found for payid', ['payid' => $payid]);
            return response()->json(['status' => false, 'message' => 'Report not found']);
        }
    
        $user = User::find($report->user_id);
        $refno = $data['decodedResponse']['data']['rrn'] ?? null;
    
        // $updateOrder = [
        //     'option2' => $data['decodedResponse']['data']['charge_type'] ?? null,
        //     'option3' => $data['decodedResponse']['data']['ap_securehash'] ?? null,
        // ];
    
        // if ($airpayStatus === 'FAIL') {
        //     $updateOrder['option2'] = $data['decodedResponse']['data']['reason'] ?? null;
        // }
        
        $updateOrder = [
            'option2' => $data['decodedResponse']['data']['transaction_payment_status'] ?? $data['decodedResponse']['data']['reason'] ?? $data['decodedResponse']['data']['transaction_reason'] ?? null,
            'option3' => $data['decodedResponse']['data']['ap_securehash'] ?? null,
        ];
    
        if ($airpayStatus === 'FAIL') {
            $updateOrder['option2'] = $data['decodedResponse']['data']['transaction_payment_status'] ?? $data['decodedResponse']['data']['reason'] ?? $data['decodedResponse']['data']['transaction_reason'] ?? null;
        }
    
        if ($refno) {
            $updateOrder['refno'] = $refno;
        }
    
        $updateOrder['status'] = $airpayStatus === 'SUCCESS'
            ? 'success'
            : ($airpayStatus === 'FAIL' ? 'failed' : $report->status);
    
        // ✅ Update report first
        $report->update($updateOrder);
    
        $this->payinLog("Report updated", [
            'report_id' => $report->id,
            'update' => $updateOrder
        ]);
    
        /*
        |--------------------------------------------------------------------------
        | 🔥 WALLET UPDATE + DESCRIPTION UPDATE
        |--------------------------------------------------------------------------
        */
    
        if ($airpayStatus === 'SUCCESS') {
    
            $this->payinLog('Wallet credit started', [
                'user_id' => $user->id,
                'report_id' => $report->id,
                'amount' => $report->amount
            ]);
    
            // Update wallet
            $user->rolling_amount = ($user->rolling_amount ?? 0) + ($report->payin_rolling_amount ?? 0);
            $user->payin_wallet   = ($user->payin_wallet ?? 0) + ($report->payin_amount ?? 0);
            $user->total_charges  = ($user->total_charges ?? 0) + ($report->profit ?? 0);
            $user->save();
    
            // ✅ Update description = Payment counted
            $report->update([
                'description' => 'Payment counted'
            ]);
    
            $this->payinLog('Wallet credit completed', [
                'rolling_amount' => $user->rolling_amount,
                'payin_wallet' => $user->payin_wallet,
                'total_charges' => $user->total_charges,
            ]);
        }
    
        /*
        |--------------------------------------------------------------------------
        | END WALLET UPDATE
        |--------------------------------------------------------------------------
        */
    
        if ($user->payin_callback) {
            $this->payinLog('Calling merchant callback', [
                'callbackurl' => $user->payin_callback,
                'status' => $airpayStatus,
                'txnid' => $report->txnid,
                'mytxnid' => $report->mytxnid,
                'amount' => $report->amount,
                'refno' => $refno,
                'timestamp' => $timestamp
            ]);
    
            $this->merchantCallBackResponse(
                $user->payin_callback,
                $airpayStatus,
                $report->txnid,
                $report->mytxnid,
                $report->amount,
                $refno,
                $timestamp
            );
        }
    
        $this->payinLog('omisha callback processing finished');
    
        return response()->json(['status' => true, 'message' => 'omisha Ready to work']);
    }
////////////////////////////////////////////////////////////////////////////////
    public function ebookcallbkp(Request $request)
    {
        $this->payinLog("Ebook_spay log");
    
        $this->payinLog('Ebook_spay Callback Received', [
            'request_array' => $request->all(),
            'raw_content' => $request->getContent()
        ]);
    
        DB::table('micro_logs')->insert([
            'product_response' => json_encode($request->all()),
            'product_name' => 'Airpay',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    
        $data = json_decode($request->getContent(), true);
    
        if (!$data) {
            $this->payinLog('Failed to decode Ebook_spay response', ['input' => $request->getContent()]);
            return response()->json(['status' => false, 'message' => 'Invalid response']);
        }
    
        $payid = $data['decodedResponse']['data']['orderid'] ?? null;
        $airpayStatus = $data['decodedResponse']['data']['transaction_payment_status'] ?? null;
        $timestamp = $data['timestamp'] ?? null;
    
        if (!$payid) {
            $this->payinLog('payid missing in Ebook_spay response', ['response' => $data]);
            return response()->json(['status' => false, 'message' => 'Order ID missing']);
        }
    
        $report = Report::where('mytxnid', $payid)
            ->where('status', 'initiated')
            ->where('product', 'UPI')
            ->first();
    
        if (!$report) {
            $this->payinLog('No report found for payid', ['payid' => $payid]);
            return response()->json(['status' => false, 'message' => 'Report not found']);
        }
    
        $user = User::find($report->user_id);
        $refno = $data['decodedResponse']['data']['rrn'] ?? null;
    
        // $updateOrder = [
        //     'option2' => $data['decodedResponse']['data']['charge_type'] ?? null,
        //     'option3' => $data['decodedResponse']['data']['ap_securehash'] ?? null,
        // ];
    
        // if ($airpayStatus === 'FAIL') {
        //     $updateOrder['option2'] = $data['decodedResponse']['data']['reason'] ?? null;
        // }
        
        $updateOrder = [
            'option2' => $data['decodedResponse']['data']['transaction_payment_status'] ?? $data['decodedResponse']['data']['reason'] ?? $data['decodedResponse']['data']['transaction_reason'] ?? null,
            'option3' => $data['decodedResponse']['data']['ap_securehash'] ?? null,
        ];
    
        if ($airpayStatus === 'FAIL') {
            $updateOrder['option2'] = $data['decodedResponse']['data']['transaction_payment_status'] ?? $data['decodedResponse']['data']['reason'] ?? $data['decodedResponse']['data']['transaction_reason'] ?? null;
        }
    
        if ($refno) {
            $updateOrder['refno'] = $refno;
        }
    
        $updateOrder['status'] = $airpayStatus === 'SUCCESS'
            ? 'success'
            : ($airpayStatus === 'FAIL' ? 'failed' : $report->status);
    
        // ✅ Update report first
        $report->update($updateOrder);
    
        $this->payinLog("Report updated", [
            'report_id' => $report->id,
            'update' => $updateOrder
        ]);
    
        /*
        |--------------------------------------------------------------------------
        | 🔥 WALLET UPDATE + DESCRIPTION UPDATE
        |--------------------------------------------------------------------------
        */
    
        if ($airpayStatus === 'SUCCESS') {
    
            $this->payinLog('Wallet credit started', [
                'user_id' => $user->id,
                'report_id' => $report->id,
                'amount' => $report->amount
            ]);
    
            // Update wallet
            $user->rolling_amount = ($user->rolling_amount ?? 0) + ($report->payin_rolling_amount ?? 0);
            $user->payin_wallet   = ($user->payin_wallet ?? 0) + ($report->payin_amount ?? 0);
            $user->total_charges  = ($user->total_charges ?? 0) + ($report->profit ?? 0);
            $user->save();
    
            // ✅ Update description = Payment counted
            $report->update([
                'description' => 'Payment counted'
            ]);
    
            $this->payinLog('Wallet credit completed', [
                'rolling_amount' => $user->rolling_amount,
                'payin_wallet' => $user->payin_wallet,
                'total_charges' => $user->total_charges,
            ]);
        }
    
        /*
        |--------------------------------------------------------------------------
        | END WALLET UPDATE
        |--------------------------------------------------------------------------
        */
    
        if ($user->payin_callback) {
            $this->payinLog('Calling merchant callback', [
                'callbackurl' => $user->payin_callback,
                'status' => $airpayStatus,
                'txnid' => $report->txnid,
                'mytxnid' => $report->mytxnid,
                'amount' => $report->amount,
                'refno' => $refno,
                'timestamp' => $timestamp
            ]);
    
            $this->merchantCallBackResponse(
                $user->payin_callback,
                $airpayStatus,
                $report->txnid,
                $report->mytxnid,
                $report->amount,
                $refno,
                $timestamp
            );
        }
    
        $this->payinLog('Ebook_spay callback processing finished');
    
        return response()->json(['status' => true, 'message' => 'Ebook_spay Ready to work']);
    }
    
////////////////////////////////////////////////////////////////////////////////
    public function evahcallbkp(Request $request)
    {
        $this->payinLog("Evah_spay log");
    
        $this->payinLog('Evah_spay Callback Received', [
            'request_array' => $request->all(),
            'raw_content' => $request->getContent()
        ]);
    
        DB::table('micro_logs')->insert([
            'product_response' => json_encode($request->all()),
            'product_name' => 'Airpay',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    
        $data = json_decode($request->getContent(), true);
    
        if (!$data) {
            $this->payinLog('Failed to decode Evah_spay response', ['input' => $request->getContent()]);
            return response()->json(['status' => false, 'message' => 'Invalid response']);
        }
    
        $payid = $data['decodedResponse']['data']['orderid'] ?? null;
        $airpayStatus = $data['decodedResponse']['data']['transaction_payment_status'] ?? null;
        $timestamp = $data['timestamp'] ?? null;
    
        if (!$payid) {
            $this->payinLog('payid missing in Evah_spay response', ['response' => $data]);
            return response()->json(['status' => false, 'message' => 'Order ID missing']);
        }
    
        $report = Report::where('mytxnid', $payid)
            ->where('status', 'initiated')
            ->where('product', 'UPI')
            ->first();
    
        if (!$report) {
            $this->payinLog('No report found for payid', ['payid' => $payid]);
            return response()->json(['status' => false, 'message' => 'Report not found']);
        }
    
        $user = User::find($report->user_id);
        $refno = $data['decodedResponse']['data']['rrn'] ?? null;
    
        // $updateOrder = [
        //     'option2' => $data['decodedResponse']['data']['charge_type'] ?? null,
        //     'option3' => $data['decodedResponse']['data']['ap_securehash'] ?? null,
        // ];
    
        // if ($airpayStatus === 'FAIL') {
        //     $updateOrder['option2'] = $data['decodedResponse']['data']['reason'] ?? null;
        // }
        
        $updateOrder = [
            'option2' => $data['decodedResponse']['data']['transaction_payment_status'] ?? $data['decodedResponse']['data']['reason'] ?? $data['decodedResponse']['data']['transaction_reason'] ?? null,
            'option3' => $data['decodedResponse']['data']['ap_securehash'] ?? null,
        ];
    
        if ($airpayStatus === 'FAIL') {
            $updateOrder['option2'] = $data['decodedResponse']['data']['transaction_payment_status'] ?? $data['decodedResponse']['data']['reason'] ?? $data['decodedResponse']['data']['transaction_reason'] ?? null;
        }
    
        if ($refno) {
            $updateOrder['refno'] = $refno;
        }
    
        $updateOrder['status'] = $airpayStatus === 'SUCCESS'
            ? 'success'
            : ($airpayStatus === 'FAIL' ? 'failed' : $report->status);
    
        // ✅ Update report first
        $report->update($updateOrder);
    
        $this->payinLog("Report updated", [
            'report_id' => $report->id,
            'update' => $updateOrder
        ]);
    
        /*
        |--------------------------------------------------------------------------
        | 🔥 WALLET UPDATE + DESCRIPTION UPDATE
        |--------------------------------------------------------------------------
        */
    
        if ($airpayStatus === 'SUCCESS') {
    
            $this->payinLog('Wallet credit started', [
                'user_id' => $user->id,
                'report_id' => $report->id,
                'amount' => $report->amount
            ]);
    
            // Update wallet
            $user->rolling_amount = ($user->rolling_amount ?? 0) + ($report->payin_rolling_amount ?? 0);
            $user->payin_wallet   = ($user->payin_wallet ?? 0) + ($report->payin_amount ?? 0);
            $user->total_charges  = ($user->total_charges ?? 0) + ($report->profit ?? 0);
            $user->save();
    
            // ✅ Update description = Payment counted
            $report->update([
                'description' => 'Payment counted'
            ]);
    
            $this->payinLog('Wallet credit completed', [
                'rolling_amount' => $user->rolling_amount,
                'payin_wallet' => $user->payin_wallet,
                'total_charges' => $user->total_charges,
            ]);
        }
    
        /*
        |--------------------------------------------------------------------------
        | END WALLET UPDATE
        |--------------------------------------------------------------------------
        */
    
        if ($user->payin_callback) {
            $this->payinLog('Calling merchant callback', [
                'callbackurl' => $user->payin_callback,
                'status' => $airpayStatus,
                'txnid' => $report->txnid,
                'mytxnid' => $report->mytxnid,
                'amount' => $report->amount,
                'refno' => $refno,
                'timestamp' => $timestamp
            ]);
    
            $this->merchantCallBackResponse(
                $user->payin_callback,
                $airpayStatus,
                $report->txnid,
                $report->mytxnid,
                $report->amount,
                $refno,
                $timestamp
            );
        }
    
        $this->payinLog('Evah_spay callback processing finished');
    
        return response()->json(['status' => true, 'message' => 'Evah_spay Ready to work']);
    }
////////////////////////////////////////////////////////////////////////////////   
    public function soulfulcallbkp(Request $request)
    {
        $this->payinLog("airpay log");
    
        $this->payinLog('Airpay Callback Received', [
            'request_array' => $request->all(),
            'raw_content' => $request->getContent()
        ]);
    
        DB::table('micro_logs')->insert([
            'product_response' => json_encode($request->all()),
            'product_name' => 'Airpay',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    
        $data = json_decode($request->getContent(), true);
    
        if (!$data) {
            $this->payinLog('Failed to decode Airpay response', ['input' => $request->getContent()]);
            return response()->json(['status' => false, 'message' => 'Invalid response']);
        }
    
        $payid = $data['decodedResponse']['data']['orderid'] ?? null;
        $airpayStatus = $data['decodedResponse']['data']['transaction_payment_status'] ?? null;
        $timestamp = $data['timestamp'] ?? null;
    
        if (!$payid) {
            $this->payinLog('payid missing in Airpay response', ['response' => $data]);
            return response()->json(['status' => false, 'message' => 'Order ID missing']);
        }
    
        $report = Report::where('mytxnid', $payid)
            ->where('status', 'initiated')
            ->where('product', 'UPI')
            ->first();
    
        if (!$report) {
            $this->payinLog('No report found for payid', ['payid' => $payid]);
            return response()->json(['status' => false, 'message' => 'Report not found']);
        }
    
        $user = User::find($report->user_id);
        $refno = $data['decodedResponse']['data']['rrn'] ?? null;
    
        // $updateOrder = [
        //     'option2' => $data['decodedResponse']['data']['charge_type'] ?? null,
        //     'option3' => $data['decodedResponse']['data']['ap_securehash'] ?? null,
        // ];
    
        // if ($airpayStatus === 'FAIL') {
        //     $updateOrder['option2'] = $data['decodedResponse']['data']['reason'] ?? null;
        // }
        
        $updateOrder = [
            'option2' => $data['decodedResponse']['data']['transaction_payment_status'] ?? $data['decodedResponse']['data']['reason'] ?? $data['decodedResponse']['data']['transaction_reason'] ?? null,
            'option3' => $data['decodedResponse']['data']['ap_securehash'] ?? null,
        ];
    
        if ($airpayStatus === 'FAIL') {
            $updateOrder['option2'] = $data['decodedResponse']['data']['transaction_payment_status'] ?? $data['decodedResponse']['data']['reason'] ?? $data['decodedResponse']['data']['transaction_reason'] ?? null;
        }
    
        if ($refno) {
            $updateOrder['refno'] = $refno;
        }
    
        $updateOrder['status'] = $airpayStatus === 'SUCCESS'
            ? 'success'
            : ($airpayStatus === 'FAIL' ? 'failed' : $report->status);
    
        // ✅ Update report first
        $report->update($updateOrder);
    
        $this->payinLog("Report updated", [
            'report_id' => $report->id,
            'update' => $updateOrder
        ]);
    
        /*
        |--------------------------------------------------------------------------
        | 🔥 WALLET UPDATE + DESCRIPTION UPDATE
        |--------------------------------------------------------------------------
        */
    
        if ($airpayStatus === 'SUCCESS') {
    
            $this->payinLog('Wallet credit started', [
                'user_id' => $user->id,
                'report_id' => $report->id,
                'amount' => $report->amount
            ]);
    
            // Update wallet
            $user->rolling_amount = ($user->rolling_amount ?? 0) + ($report->payin_rolling_amount ?? 0);
            $user->payin_wallet   = ($user->payin_wallet ?? 0) + ($report->payin_amount ?? 0);
            $user->total_charges  = ($user->total_charges ?? 0) + ($report->profit ?? 0);
            $user->save();
    
            // ✅ Update description = Payment counted
            $report->update([
                'description' => 'Payment counted'
            ]);
    
            $this->payinLog('Wallet credit completed', [
                'rolling_amount' => $user->rolling_amount,
                'payin_wallet' => $user->payin_wallet,
                'total_charges' => $user->total_charges,
            ]);
        }
    
        /*
        |--------------------------------------------------------------------------
        | END WALLET UPDATE
        |--------------------------------------------------------------------------
        */
    
        if ($user->payin_callback) {
            $this->payinLog('Calling merchant callback', [
                'callbackurl' => $user->payin_callback,
                'status' => $airpayStatus,
                'txnid' => $report->txnid,
                'mytxnid' => $report->mytxnid,
                'amount' => $report->amount,
                'refno' => $refno,
                'timestamp' => $timestamp
            ]);
    
            $this->merchantCallBackResponse(
                $user->payin_callback,
                $airpayStatus,
                $report->txnid,
                $report->mytxnid,
                $report->amount,
                $refno,
                $timestamp
            );
        }
    
        $this->payinLog('Airpay callback processing finished');
    
        return response()->json(['status' => true, 'message' => 'AIRPAY Ready to work']);
    }

////////////////////////////////////////////////////////////////////////////////
    public function nxtcallbkp(Request $request)
    {
        // dd('hello');
        $this->payinLog("nxt log");
    
        $this->payinLog('nxt Callback Received', [
            'request_array' => $request->all(),
            'raw_content' => $request->getContent()
        ]);
    }
    
////////////////////////////////////////////////////////////////////////////////
    public function E2Paycallbkp(Request $request)
    {
        // dd('Hello Yuvraj');
        
        $this->payinLog("E2PAY LOG");
    
        $this->payinLog('E2PAY Callback Received', [
            'request_array' => $request->all(),
            'raw_content' => $request->getContent()
        ]);
    }
    
////////////////////////////////////////////////////////////////////////////////
     public function e2paycallback(Request $request)
    {
        $this->payinLog("e2pay log");
    
        $this->payinLog('e2pay Callback Received', [
            'request_array' => $request->all(),
            'raw_content' => $request->getContent()
        ]);
    
        DB::table('micro_logs')->insert([
            'product_response' => json_encode($request->all()),
            'product_name' => 'E2PAY',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    
        $data = json_decode($request->getContent(), true);
    
        if (!$data) {
            $this->payinLog('Failed to decode E2PAY response', ['input' => $request->getContent()]);
            return response()->json(['status' => false, 'message' => 'Invalid response']);
        }
    
        $payid = $data['orderId']  ?? $data['data']['orderid'] ?? null;
        // $airpayStatus = $data['message'] ?? null;
        $airpayStatus = strtolower($data['message'] ?? '');
        $timestamp = $data['timestamp'] ?? null;
    
        if (!$payid) {
            $this->payinLog('payid missing in E2PAY response', ['response' => $data]);
            return response()->json(['status' => false, 'message' => 'Order ID missing']);
        }
    
        $report = Report::where('mytxnid', $payid)
            ->where('status', 'initiated')
            ->where('product', 'UPI')
            ->first();
    
        if (!$report) {
            $this->payinLog('No report found for payid', ['payid' => $payid]);
            return response()->json(['status' => false, 'message' => 'Report not found']);
        }
    
        $user = User::find($report->user_id);
        $refno = $data['Utr'] ?? null;
    
        // $updateOrder = [
        //     'option2' => $data['decodedResponse']['data']['charge_type'] ?? null,
        //     'option3' => $data['decodedResponse']['data']['ap_securehash'] ?? null,
        // ];
    
        // if ($airpayStatus === 'FAIL') {
        //     $updateOrder['option2'] = $data['decodedResponse']['data']['reason'] ?? null;
        // }
        
        $updateOrder = [
            'option2' => $data['message'] ?? $data['data']['reason'] ?? $data['data']['transaction_reason'] ?? null,
            'option3' => $data['Utr'] ?? null,
        ];
    
        if ($airpayStatus === 'FAIL') {
            $updateOrder['option2'] = $data['message'] ?? $data['decodedResponse']['data']['reason'] ?? $data['decodedResponse']['data']['transaction_reason'] ?? null;
        }
    
        if ($refno) {
            $updateOrder['refno'] = $refno;
        }
    
        $updateOrder['status'] = $airpayStatus === 'success'
            ? 'success'
            : ($airpayStatus === 'FAIL' ? 'failed' : $report->status);
    
        // ✅ Update report first
        $report->update($updateOrder);
    
        $this->payinLog("Report updated", [
            'report_id' => $report->id,
            'update' => $updateOrder
        ]);
    
        /*
        |--------------------------------------------------------------------------
        | 🔥 WALLET UPDATE + DESCRIPTION UPDATE
        |--------------------------------------------------------------------------
        */
    
        if ($airpayStatus === 'success') {
    
            $this->payinLog('Wallet credit started', [
                'user_id' => $user->id,
                'report_id' => $report->id,
                'amount' => $report->amount
            ]);
    
            // Update wallet
            $user->rolling_amount = ($user->rolling_amount ?? 0) + ($report->payin_rolling_amount ?? 0);
            $user->payin_wallet   = ($user->payin_wallet ?? 0) + ($report->payin_amount ?? 0);
            $user->total_charges  = ($user->total_charges ?? 0) + ($report->profit ?? 0);
            $user->save();
    
            // ✅ Update description = Payment counted
            $report->update([
                'description' => 'Payment counted'
            ]);
    
            $this->payinLog('Wallet credit completed', [
                'rolling_amount' => $user->rolling_amount,
                'payin_wallet' => $user->payin_wallet,
                'total_charges' => $user->total_charges,
            ]);
        }
    
        /*
        |--------------------------------------------------------------------------
        | END WALLET UPDATE
        |--------------------------------------------------------------------------
        */
    
        if ($user->payin_callback) {
            $this->payinLog('Calling merchant callback', [
                'callbackurl' => $user->payin_callback,
                'status' => $airpayStatus,
                'txnid' => $report->txnid,
                'mytxnid' => $report->mytxnid,
                'amount' => $report->amount,
                'refno' => $refno,
                'timestamp' => $timestamp
            ]);
    
            $this->merchantCallBackResponse(
                $user->payin_callback,
                $airpayStatus,
                $report->txnid,
                $report->mytxnid,
                $report->amount,
                $refno,
                $timestamp
            );
        }
    
        $this->payinLog('E2PAY callback processing finished');
    
        return response()->json(['status' => true, 'message' => 'E2PAY Ready to work']);
    }  
    
////////////////////////////////////////////////////////////////////////////////
     public function ebookpaytmcallback(Request $request)
    {
    //dd("hello");
    $this->paytmLog("EBOOK PAYTM CALLBACK LOG");

    $this->paytmLog('EBOOK PAYTM CALLBACK RECEIVED', [
        'request_array' => $request->all(),
        //'raw_content' => $request->getContent()
    ]);

    DB::table('micro_logs')->insert([
        'product_response' => json_encode($request->all()),
        'product_name' => 'EBOOK_PAYTM',
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    $data = $request->all();

    $payid = $data['orderId'] ?? null;

    if (!$payid) {

        $this->paytmLog('ORDER ID MISSING', [
            'response' => $data
        ]);

        return response()->json([
            'status' => false,
            'message' => 'OrderId missing'
        ]);
    }
// $report = Report::where('txnid', $payid)
//     ->orWhere('payid', $payid)
//     ->orWhere('mytxnid', $payid)
//     ->first();

   $report = Report::where('mytxnid', $payid)->first();
    // ->orWhere('apitxnid',$payid)->first();

    if (!$report) {

        $this->paytmLog('REPORT NOT FOUND', [
            'orderId' => $payid
        ]);

        return response()->json([
            'status' => false,
            'message' => 'Report not found'
        ]);
    }
    


    /*
    |--------------------------------------------------------------------------
    | DUPLICATE PROTECTION
    |--------------------------------------------------------------------------
    */

    if ($report->status === 'success') {
        // if ($report->payment_status === 'TXN_SUCCESS'){

        $this->paytmLog('DUPLICATE CALLBACK IGNORED', [
            'orderId' => $payid
        ]);

        return response()->json([
            'status' => true,
            'message' => 'Already processed'
        ]);
    }

    $user = User::find($report->user_id);

    $paytmStatus = $data['status'] ?? null;

    $updateOrder = [
        'apitxnid' => $data['txnId'] ?? null,
        'refno' => $data['bankTxnId'] ?? null,
        'option2' => $data['respMsg'] ?? null,
        'option3' => json_encode($data),
    ];

    if ($paytmStatus === 'TXN_SUCCESS') {

        $updateOrder['status'] = 'success';

    } elseif (
        in_array(
            $paytmStatus,
            [
                'TXN_FAILURE',
                'FAILURE',
                'TXN_FAILED'
            ]
        )
    ) {

        $updateOrder['status'] = 'failed';
        $updateOrder['description']="Payment Failed";
    }

    $report->update($updateOrder);

    $this->paytmLog('REPORT UPDATED', [
        'report_id' => $report->id,
        'update' => $updateOrder
    ]);

    /*
    |--------------------------------------------------------------------------
    | WALLET CREDIT
    |--------------------------------------------------------------------------
    */

    if ($paytmStatus === 'TXN_SUCCESS') {

        $this->paytmLog('User wallet calculation STARTED', [
            'user_id' => $user->id,
            'amount' => $report->amount
        ]);

        $user->rolling_amount =
            ($user->rolling_amount ?? 0)
            + ($report->payin_rolling_amount ?? 0);

        $user->payin_wallet =
            ($user->payin_wallet ?? 0)
            + ($report->payin_amount ?? 0);

        $user->total_charges =
            ($user->total_charges ?? 0)
            + ($report->profit ?? 0);

        $user->save();

        $report->update([
            'description' => 'Payment SUCCESS',
            'option1'=> 'Payment counted'
        ]);

        $this->paytmLog('User wallet calculation COMPLETED', [
            'rolling_amount' => $user->rolling_amount,
            'payin_wallet' => $user->payin_wallet,
            'total_charges' => $user->total_charges,
        ]);
    }

    /*
    |--------------------------------------------------------------------------
    | MERCHANT CALLBACK
    |--------------------------------------------------------------------------
    */

    if (!empty($user->payin_callback)) {

        $this->paytmLog('CALLING MERCHANT CALLBACK', [
            'callbackurl' => $user->payin_callback,
            'status' => $paytmStatus,
            'txnid' => $report->txnid,
            'mytxnid' => $report->mytxnid,
            'amount' => $report->amount,
            'refno' => $report->refno
        ]);

        $this->merchantCallBackResponse(
            $user->payin_callback,
            $paytmStatus,
            $report->txnid,
            $report->mytxnid,
            $report->amount,
            $report->refno,
            now()
        );
    }

    $this->paytmLog('EBOOK PAYTM CALLBACK PROCESSING FINISHED');

    return response()->json([
        'status' => true,
        'message' => 'EBOOK PAYTM CALLBACK PROCESSED'
    ]);
}


////////////////////////////////////////////////////////////////////////////////
    public function nttcallback(Request $request)
    {
        $callbackLogger = \Log::build([
            'driver' => 'single',
            'path' => storage_path('logs/callback/payin/ntt_callback.log'),
        ]);

        $callbackLogger->info('NTT CALLBACK LOG');

        $callbackLogger->info('NTT CALLBACK RECEIVED', [
            'request_array' => $request->all(),
            'raw_content' => $request->getContent(),
        ]);

        DB::table('micro_logs')->insert([
            'product_response' => json_encode($request->all()),
            'product_name' => 'NTT',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $data = $request->all();

        $mytxnid = $data['mytxnid'] ?? null;
        $txnid = $data['txnid'] ?? null;      // Atom Txn Id
        $status = strtolower($data['status'] ?? '');
        $refno = $data['refno'] ?? null;

        if (! $mytxnid) {

            $callbackLogger->warning('NTT TXNID MISSING', [
                'response' => $data,
            ]);

            return response()->json([
                'status' => false,
                'message' => 'TxnId missing',
            ]);
        }

        $report = Report::where('mytxnid', $mytxnid)
            ->where('product', 'UPI')
            ->first();

        if (! $report) {

            $callbackLogger->warning('NTT REPORT NOT FOUND', [
                'mytxnid' => $mytxnid,
            ]);

            return response()->json([
                'status' => false,
                'message' => 'Report not found',
            ]);
        }

        /*
        |--------------------------------------------------------------------------
        | DUPLICATE PROTECTION
        |--------------------------------------------------------------------------
        */

        if ($report->status === 'success') {

            $callbackLogger->info('NTT DUPLICATE CALLBACK IGNORED', [
                'mytxnid' => $mytxnid,
            ]);

            return response()->json([
                'status' => true,
                'message' => 'Already processed',
            ]);
        }

        $user = User::find($report->user_id);

        /*
        |--------------------------------------------------------------------------
        | UPDATE REPORT
        |--------------------------------------------------------------------------
        */

        $updateOrder = [
            'apitxnid' => $txnid,
            'refno' => $refno,
            'option2' => strtoupper($status),

            // SMALL JSON ONLY
            'option3' => json_encode([
                'status' => $status,
                'txnid' => $txnid,
                'refno' => $refno,
                'mytxnid' => $mytxnid,
            ]),
        ];

        if ($status === 'success') {
            $updateOrder['status'] = 'success';
        } elseif ($status === 'failed') {
            $updateOrder['status'] = 'failed';
        }

        $report->update($updateOrder);

        $callbackLogger->info('NTT REPORT UPDATED', [
            'report_id' => $report->id,
            'update' => $updateOrder,
        ]);

        /*
        |--------------------------------------------------------------------------
        | WALLET CREDIT
        |--------------------------------------------------------------------------
        */

        if ($status === 'success') {

            $callbackLogger->info('NTT WALLET CREDIT STARTED', [
                'user_id' => $user->id,
                'amount' => $report->amount,
            ]);

            $user->rolling_amount =
                ($user->rolling_amount ?? 0)
                + ($report->payin_rolling_amount ?? 0);

            $user->payin_wallet =
                ($user->payin_wallet ?? 0)
                + ($report->payin_amount ?? 0);

            $user->total_charges =
                ($user->total_charges ?? 0)
                + ($report->profit ?? 0);

            $user->save();

            $report->update([
                'description' => 'Payment counted',
            ]);

            $callbackLogger->info('NTT WALLET CREDIT COMPLETED', [
                'rolling_amount' => $user->rolling_amount,
                'payin_wallet' => $user->payin_wallet,
                'total_charges' => $user->total_charges,
            ]);
        }

        /*
        |--------------------------------------------------------------------------
        | MERCHANT CALLBACK
        |--------------------------------------------------------------------------
        */

        if (! empty($user->payin_callback)) {

            $callbackLogger->info('NTT MERCHANT CALLBACK STARTED', [
                'callbackurl' => $user->payin_callback,
                'status' => strtoupper($status),
                'txnid' => $report->txnid,
                'mytxnid' => $report->mytxnid,
                'amount' => $report->amount,
                'refno' => $refno,
            ]);

            $this->merchantCallBackResponse(
                $user->payin_callback,
                strtoupper($status),
                $report->txnid,
                $report->mytxnid,
                $report->amount,
                $refno,
                now()
            );

            $callbackLogger->info('NTT MERCHANT CALLBACK COMPLETED', [
                'callbackurl' => $user->payin_callback,
            ]);
        }

        $callbackLogger->info('NTT CALLBACK PROCESSING FINISHED');

        return response()->json([
            'status' => true,
            'message' => 'NTT CALLBACK PROCESSED',
        ]);
    }

////////////////////////////////////////////////////////////////////////////////
    public function razorpayCallback(Request $request)
    {
        $callbackLogger = \Log::build([
            'driver' => 'single',
            'path' => storage_path('logs/callback/payin/razorpay_callback.log'),
        ]);
        
        $callbackLogger->info('==================RAZORPAY CALLBACK RECEIVED=======================', [
            'request' => $request->all(),
        ]);
    
        $data = $request->all();
    
        $orderId = $data['order_id'] ?? null;
    
        if (!$orderId) {
    
            return response()->json([
                'status' => false,
                'message' => 'Order ID missing',
            ]);
        }
    
        $report = Report::where(
            'apitxnid',
            $orderId
        )->first();
    
        if (!$report) {
    
            $callbackLogger->warning('RAZORPAY REPORT NOT FOUND', [
                'order_id' => $orderId,
            ]);
    
            return response()->json([
                'status' => false,
                'message' => 'Report not found',
            ]);
        }
    
        /*
        |--------------------------------------------------------------------------
        | DUPLICATE PROTECTION
        |--------------------------------------------------------------------------
        */
    
       if ($report->status === 'success') {

            $callbackLogger->info('RAZORPAY DUPLICATE CALLBACK IGNORED', [
                'order_id' => $orderId,
            ]);
        
            return response()->json([
                'status' => true,
                'message' => 'Already processed',
            ]);
        }
    
        $user = User::find($report->user_id);
    
        $paymentStatus = $data['status'] ?? 'failed';
    
        $updateOrder = [
            'refno'     => $data['rrn'] ?? null,
            'payid'     => $data['payment_id'] ?? null,
            'payer_vpa' => $data['vpa'] ?? null,
            'option2'   => strtoupper($paymentStatus),
            'option3'   => json_encode($data),
            'status'    =>
                $paymentStatus === 'success'
                    ? 'success'
                    : 'failed',
        ];
    
        $report->update($updateOrder);
    
        $callbackLogger->info('RAZORPAY REPORT UPDATED', [
            'report_id' => $report->id,
            'update' => $updateOrder,
        ]);
    
        /*
        |--------------------------------------------------------------------------
        | WALLET CREDIT
        |--------------------------------------------------------------------------
        */
    
        if ($paymentStatus === 'success') {
    
            $user->rolling_amount =
                ($user->rolling_amount ?? 0)
                + ($report->payin_rolling_amount ?? 0);
    
            $user->payin_wallet =
                ($user->payin_wallet ?? 0)
                + ($report->payin_amount ?? 0);
    
            $user->total_charges =
                ($user->total_charges ?? 0)
                + ($report->profit ?? 0);
    
            $user->save();
            
            $callbackLogger->info('RAZORPAY WALLET CREDIT COMPLETED', [
                'user_id' => $user->id,
                'rolling_amount' => $user->rolling_amount,
                'payin_wallet' => $user->payin_wallet,
                'total_charges' => $user->total_charges,
            ]);
    
            $report->update([
                'description' => 'Payment counted',
            ]);
    
            /*
            |--------------------------------------------------------------------------
            | MERCHANT CALLBACK
            |--------------------------------------------------------------------------
            */
            
    
            if (!empty($user->payin_callback)) {
    
                $callbackLogger->info('RAZORPAY MERCHANT CALLBACK STARTED', [
                    'callbackurl' => $user->payin_callback,
                    'txnid' => $report->txnid,
                    'mytxnid' => $report->mytxnid,
                    'txnid' => $report->amount,
                    'mytxnid' => $report->refno,
                    'timestamp' => $report->updated_at,
                ]);
    
                $this->merchantCallBackResponse(
                    $user->payin_callback,
                    'success',
                    $report->txnid,
                    $report->mytxnid,
                    $report->amount,
                    $report->refno,
                    now()
                );
            }
        }
    
        $callbackLogger->info('======================Razorpay callback processing finished======================');
    
        return response()->json([
            'status' => true,
            'message' => 'RAZORPAY CALLBACK PROCESSED',
        ]);
    }
    
////////////////////////////////////////////////////////////////////////////////
    
    public function riseXcallbkp(Request $request)
    {
        $this->riseXLog("========== RISEXPAY WEBHOOK START ==========");
    
        /*
        |--------------------------------------------------------------------------
        | STEP 1: RAW BODY
        |--------------------------------------------------------------------------
        */
        $rawBody = $request->getContent();
    
        $this->riseXLog("STEP 1 - RAW BODY", ['body' => $rawBody]);
    
        /*
        |--------------------------------------------------------------------------
        | STEP 2: JSON DECODE
        |--------------------------------------------------------------------------
        */
        $data = json_decode($rawBody, true);
    
        if (json_last_error() !== JSON_ERROR_NONE) {
            return response()->json([
                'status' => false,
                'message' => 'Invalid JSON'
            ], 400);
        }
    
        $this->riseXLog("STEP 2 - PARSED DATA", $data);
    
        /*
        |--------------------------------------------------------------------------
        | STEP 3: FIELD EXTRACTION
        |--------------------------------------------------------------------------
        */
        $transactionId = $data['Txn_ID'] ?? null;
        $status        = strtoupper(trim($data['TXN_Status'] ?? ''));
        $utr           = $data['UTR'] ?? null;
        $amount        = $data['TXN_amount'] ?? null;
        $txnDate       = $data['TXN_date'] ?? null;
    
        if (!$transactionId) {
            $this->riseXLog("MISSING Txn_ID");
    
            return response()->json([
                'status' => false,
                'message' => 'Txn_ID Missing'
            ], 400);
        }
    
        $this->riseXLog("STEP 3 - EXTRACTED", [
            'transactionId' => $transactionId,
            'status'        => $status,
            'utr'           => $utr,
            'amount'        => $amount,
            'txnDate'       => $txnDate,
        ]);
    
        /*
        |--------------------------------------------------------------------------
        | STEP 4: FIND REPORT
        |--------------------------------------------------------------------------
        */
        $report = Report::where('apitxnid', $transactionId)
            ->where('product', 'UPI')
            ->first();
    
        if (!$report) {
    
            $this->riseXLog("REPORT NOT FOUND", ['apitxnid' => '$report->apitxnid', 'transactionId' => $transactionId]);

            return response()->json(['status' => true]);
        }
    
        /*
        |--------------------------------------------------------------------------
        | STEP 5: IDEMPOTENCY
        |--------------------------------------------------------------------------
        */
        if (in_array($report->status, ['success', 'refunded'])) {
    
            $this->riseXLog("ALREADY PROCESSED", ['status' => $report->status]);
    
            return response()->json([
                'status' => true,
                'message' => 'Already processed'
            ]);
        }
    
        /*
        |--------------------------------------------------------------------------
        | STEP 6: USER
        |--------------------------------------------------------------------------
        */
        $user = User::find($report->user_id);
    
        if (!$user) {
    
            $this->riseXLog("USER NOT FOUND");
    
            return response()->json(['status' => false], 404);
        }
    
        /*
        |--------------------------------------------------------------------------
        | STEP 7: SUCCESS CHECK
        |--------------------------------------------------------------------------
        */
        $isSuccess = ($status === 'SUCCESS');
    
        $this->riseXLog("STEP 7 - SUCCESS CHECK", ['isSuccess' => $isSuccess]);
    
        /*
        |--------------------------------------------------------------------------
        | STEP 8: UPDATE REPORT
        |--------------------------------------------------------------------------
        */
        if ($isSuccess) {
    
            $report->update([
                'status'      => 'success',
                'refno'       =>  $utr,
                'option2'     => 'Payment Successful via Callback',
            ]);
    
            $user->rolling_amount += $report->payin_rolling_amount ?? 0;
            $user->payin_wallet += $report->payin_amount ?? 0;
            $user->total_charges += $report->profit ?? 0;
            $user->save();
            
            $report->update(['description' => 'Payment counted']);
    
            $this->riseXLog("SUCCESS UPDATED", [
                'user_id' => $user->id,
                'rolling_amount' => $user->rolling_amount,
                'payin_wallet' => $user->payin_wallet,
                'total_charges' => $user->total_charges
            ]);
    
        } else {
    
            $report->update([
                'status'      => 'failed',
                'refno'       => $utr,
                'option2'     => 'Payment Failed via Callback',
            ]);
    
            $this->riseXLog("PAYMENT FAILED", [
                'transactionId' => $transactionId
            ]);
        }
    
        /*
        |--------------------------------------------------------------------------
        | STEP 9: MERCHANT CALLBACK
        |--------------------------------------------------------------------------
        */
        try {
    
            if (!empty($user->payin_callback)) {
    
                $this->merchantCallBackResponse(
                    $user->payin_callback,
                    $isSuccess ? 'SUCCESS' : 'FAILED',
                    $report->txnid,
                    $transactionId,
                    $report->amount,
                    $utr,
                    now()
                );
            }
    
        } catch (\Throwable $e) {
    
            $this->riseXLog("CALLBACK FAILED", [
                'error' => $e->getMessage()
            ]);
        }
        
        $this->riseXLog("========== RISEXPAY WEBHOOK PROCESSING FINISHED  ==========");
    
        /*
        |--------------------------------------------------------------------------
        | STEP 10: RESPONSE
        |--------------------------------------------------------------------------
        */
        return response()->json([
            'status' => true,
            'message' => 'Webhook processed'
        ]);
    }
    
////////////////////////////////////////////////////////////////////////////////
    
    public function Shaymavenuecallbkp(Request $request)
    {
        $this->shaymavenueLog(
            "========== SHAYMAVENUE WEBHOOK START =========="
        );
    
        /*
        |--------------------------------------------------------------------------
        | STEP 1: RAW BODY
        |--------------------------------------------------------------------------
        */
    
        $rawBody = $request->getContent();
    
        $this->shaymavenueLog(
            "STEP 1 - RAW BODY",
            [
                'body' => $rawBody
            ]
        );
    
        /*
        |--------------------------------------------------------------------------
        | STEP 2: JSON DECODE
        |--------------------------------------------------------------------------
        */
    
        $data = json_decode($rawBody, true);
    
        if (json_last_error() !== JSON_ERROR_NONE) {
    
            $this->shaymavenueLog(
                "STEP 2 - INVALID JSON",
                [
                    'error' => json_last_error_msg()
                ]
            );
    
            return response()->json([
                'status'  => false,
                'message' => 'Invalid JSON'
            ], 400);
        }
    
        $this->shaymavenueLog(
            "STEP 2 - PARSED DATA",
            $data
        );
    
        /*
        |--------------------------------------------------------------------------
        | STEP 3: VALIDATE ARRAY
        |--------------------------------------------------------------------------
        */
    
        if (!is_array($data) || empty($data)) {
    
            $this->shaymavenueLog(
                "INVALID / EMPTY WEBHOOK DATA"
            );
    
            return response()->json([
                'status'  => false,
                'message' => 'Invalid webhook payload'
            ], 400);
        }
    
        /*
        |--------------------------------------------------------------------------
        | STEP 4: PROCESS TRANSACTIONS
        |--------------------------------------------------------------------------
        */
    
        foreach ($data as $transaction) {
    
            if (!is_array($transaction)) {
    
                $this->shaymavenueLog(
                    "INVALID TRANSACTION DATA",
                    [
                        'transaction' => $transaction
                    ]
                );
    
                continue;
            }
    
            /*
            |--------------------------------------------------------------------------
            | STEP 5: FIELD EXTRACTION
            |--------------------------------------------------------------------------
            */
    
            $transactionId = $transaction['Txn_ID'] ?? null;
    
            $status = strtoupper(
                trim($transaction['TXN_Status'] ?? '')
            );
    
            $utr = $transaction['UTR'] ?? null;
    
            $amount = $transaction['TXN_amount'] ?? null;
    
            $txnDate = $transaction['TXN_date'] ?? null;
    
            $this->shaymavenueLog(
                "STEP 5 - EXTRACTED TRANSACTION",
                [
                    'transactionId' => $transactionId,
                    'status'        => $status,
                    'utr'           => $utr,
                    'amount'        => $amount,
                    'txnDate'       => $txnDate,
                ]
            );
    
            /*
            |--------------------------------------------------------------------------
            | STEP 6: Txn_ID VALIDATION
            |--------------------------------------------------------------------------
            */
    
            if (!$transactionId) {
    
                $this->shaymavenueLog(
                    "MISSING Txn_ID"
                );
    
                continue;
            }
    
            /*
            |--------------------------------------------------------------------------
            | STEP 7: STATUS VALIDATION
            |--------------------------------------------------------------------------
            */
    
            if (!in_array($status, ['SUCCESS', 'FAILED'])) {
    
                $this->shaymavenueLog(
                    "INVALID TRANSACTION STATUS",
                    [
                        'transactionId' => $transactionId,
                        'status'        => $status
                    ]
                );
    
                continue;
            }
    
            /*
            |--------------------------------------------------------------------------
            | STEP 8: FIND REPORT
            |--------------------------------------------------------------------------
            */
    
            $report = Report::where('apitxnid', $transactionId)
                ->where('product', 'UPI')
                ->first();
    
            if (!$report) {
    
                $this->shaymavenueLog(
                    "REPORT NOT FOUND",
                    [
                        'apitxnid'      => $transactionId,
                        'transactionId' => $transactionId
                    ]
                );
    
                continue;
            }
    
            $this->shaymavenueLog(
                "STEP 8 - REPORT FOUND",
                [
                    'report_id' => $report->id,
                    'apitxnid'  => $report->apitxnid,
                    'status'    => $report->status,
                    'amount'    => $report->amount,
                    'user_id'   => $report->user_id
                ]
            );
    
            /*
            |--------------------------------------------------------------------------
            | STEP 9: DUPLICATE PROTECTION
            |--------------------------------------------------------------------------
            */
    
            if (in_array($report->status, ['success', 'refunded'])) {
    
                $this->shaymavenueLog(
                    "ALREADY PROCESSED",
                    [
                        'transactionId' => $transactionId,
                        'status'        => $report->status
                    ]
                );
    
                continue;
            }
    
            /*
            |--------------------------------------------------------------------------
            | STEP 10: USER
            |--------------------------------------------------------------------------
            */
    
            $user = User::find($report->user_id);
    
            if (!$user) {
    
                $this->shaymavenueLog(
                    "USER NOT FOUND",
                    [
                        'user_id' => $report->user_id
                    ]
                );
    
                continue;
            }
    
            /*
            |--------------------------------------------------------------------------
            | STEP 11: AMOUNT VALIDATION
            |--------------------------------------------------------------------------
            */
    
            if ($amount === null || $amount === '') {
    
                $this->shaymavenueLog(
                    "AMOUNT MISSING",
                    [
                        'transactionId' => $transactionId
                    ]
                );
    
                continue;
            }
    
            if ((float) $report->amount !== (float) $amount) {
    
                $this->shaymavenueLog(
                    "AMOUNT MISMATCH",
                    [
                        'transactionId'    => $transactionId,
                        'report_amount'    => $report->amount,
                        'webhook_amount'   => $amount
                    ]
                );
    
                continue;
            }
    
            /*
            |--------------------------------------------------------------------------
            | STEP 12: SUCCESS CHECK
            |--------------------------------------------------------------------------
            */
    
            $isSuccess = ($status === 'SUCCESS');
    
            $this->shaymavenueLog(
                "STEP 12 - SUCCESS CHECK",
                [
                    'isSuccess' => $isSuccess
                ]
            );
    
            /*
            |--------------------------------------------------------------------------
            | STEP 13: UPDATE REPORT
            |--------------------------------------------------------------------------
            */
    
            if ($isSuccess) {
    
                $report->update([
                    'status'  => 'success',
                    'refno'   => $utr,
                    'option2' => 'Payment Successful via Shaymavenue',
                ]);
    
                /*
                |--------------------------------------------------------------------------
                | STEP 14: WALLET CREDIT
                |--------------------------------------------------------------------------
                */
    
                $user->rolling_amount =
                    ($user->rolling_amount ?? 0)
                    + ($report->payin_rolling_amount ?? 0);
    
                $user->payin_wallet =
                    ($user->payin_wallet ?? 0)
                    + ($report->payin_amount ?? 0);
    
                $user->total_charges =
                    ($user->total_charges ?? 0)
                    + ($report->profit ?? 0);
    
                $user->save();
    
                $report->update([
                    'description' => 'Payment counted'
                ]);
    
                $this->shaymavenueLog(
                    "SUCCESS UPDATED + WALLET CREDITED",
                    [
                        'transactionId'    => $transactionId,
                        'user_id'          => $user->id,
                        'amount'           => $report->amount,
                        'utr'              => $utr,
                        'rolling_amount'  => $user->rolling_amount,
                        'payin_wallet'    => $user->payin_wallet,
                        'total_charges'   => $user->total_charges
                    ]
                );
    
            } else {
    
                /*
                |--------------------------------------------------------------------------
                | STEP 15: FAILED TRANSACTION
                |--------------------------------------------------------------------------
                */
    
                $report->update([
                    'status'      => 'failed',
                    'refno'       => $utr,
                    'option2'     => 'Payment Failed via Shaymavenue',
                    'description' => 'Payment Failed'
                ]);
    
                $this->shaymavenueLog(
                    "PAYMENT FAILED",
                    [
                        'transactionId' => $transactionId,
                        'amount'        => $amount,
                        'utr'           => $utr
                    ]
                );
            }
    
            /*
            |--------------------------------------------------------------------------
            | STEP 16: MERCHANT CALLBACK
            |--------------------------------------------------------------------------
            */
    
            try {
    
                if (!empty($user->payin_callback)) {
    
                    $this->shaymavenueLog(
                        "CALLING MERCHANT CALLBACK",
                        [
                            'callbackurl' => $user->payin_callback,
                            'status'      => $isSuccess ? 'SUCCESS' : 'FAILED',
                            'txnid'       => $report->txnid,
                            'mytxnid'     => $report->mytxnid,
                            'amount'      => $report->amount,
                            'refno'       => $utr
                        ]
                    );
    
                    $this->merchantCallBackResponse(
                        $user->payin_callback,
                        $isSuccess ? 'SUCCESS' : 'FAILED',
                        $report->txnid,
                        $report->mytxnid,
                        $report->amount,
                        $utr,
                        now()
                    );
    
                    $this->shaymavenueLog(
                        "MERCHANT CALLBACK COMPLETED"
                    );
                }
    
            } catch (\Throwable $e) {
    
                $this->shaymavenueLog(
                    "CALLBACK FAILED",
                    [
                        'error' => $e->getMessage()
                    ]
                );
            }
        }
    
        /*
        |--------------------------------------------------------------------------
        | STEP 17: FINISHED
        |--------------------------------------------------------------------------
        */
    
        $this->shaymavenueLog(
            "========== SHAYMAVENUE WEBHOOK PROCESSING FINISHED =========="
        );
    
        /*
        |--------------------------------------------------------------------------
        | STEP 18: RESPONSE
        |--------------------------------------------------------------------------
        */
    
        return response()->json([
            'status'  => true,
            'message' => 'Webhook processed'
        ], 200);
    }
    
////////////////////////////////////////////////////////////////////////////////
}