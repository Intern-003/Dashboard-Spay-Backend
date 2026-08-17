<?php

namespace App\Http\Controllers\Api\Payin;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use App\Services\Payin\PayinFactory;
use App\Services\Payin\PayinService;

class CommonPayinController extends Controller
{
    public function __construct(
        private PayinService $payinService,
        private PayinFactory $payinFactory
    ){}

    public function generateUpiQr(Request $request)
    {
        $rules = [
            'token'   => 'required',
            'orderid' => 'required|alpha_num|min:8|max:20|unique:reports,mytxnid',
            'email'   => 'required|email',
            'phone'   => 'required|digits_between:10,15',
            'amount'  => 'required|numeric|min:10',
            'redirect_url' => 'nullable|url',
        ];

        $validator = Validator::make($request->all(), $rules);
        if ($validator->fails()) {
            return response()->json([
                'statuscode' => 422,
                'message'    => $validator->errors()->first(),
            ], 422);
        }

        $apiToken = $this->payinService->authByToken($request->token);
        if (!$apiToken) {
            return response()->json([
                'statuscode' => 401,
                'message'    => "User not authenticated",
            ], 401);
        }

        $user = User::find($apiToken->user_id);
        if (!$user || (int) $user->payin_status !== 1) {
            return response()->json([
                'status'     => 'failed',
                'statuscode' => 403,
                'message'    => 'Your PayIN account is deactivated. Please contact admin.',
            ], 403);
        }


        $payinBank = $user->payinBank;

        if (!$payinBank) {
            return response()->json([
                'status'  => 'FAILED',
                'message' => 'Payin bank not found'
            ], 400);
        }

        $providerName = trim((string) $payinBank->onboard_payin_bank);
        if ($providerName === '') {
            return response()->json([
                'status'  => 'FAILED',
                'message' => 'Provider name not found for this user'
            ], 400);
        }
        

        // If Airpay_all -> need active MID
        $activeMid = null;
        if ($providerName === 'Airpay_all') {
            $activeMid = $this->payinService->getActiveMid((float)$request->amount);
            if (!$activeMid) {
                return response()->json([
                    'statuscode' => 400,
                    'message'    => 'All MIDs have reached their limits',
                ], 400);
            }
        }
        
        // Create report (same as AIRpay_create)
        try {
            $report = $this->payinService->createReportForUpiQr(
                $request->all(),
                $user,
                $providerName,
                $activeMid
            );
        } catch (\Throwable $e) {
            return response()->json([
                'status'  => 'failed',
                'message' => $e->getMessage(),
            ], 400);
        }


        // Provider call
        try {
            $provider = $this->payinFactory->make($providerName);

            $payload = [
                'orderid' => $request->orderid,
                'amount'  => $request->amount,
                'email'   => $request->email,
                'phone'   => $request->phone,
                'redirect_url' => $request->redirect_url,
                'active_mid_credentials' => $activeMid['credentials'] ?? [],
            ];
            
            
            
            $result = $provider->generateUpiQr($payload, $user, $report);

if (($result['http_code'] ?? 500) !== 200) {
    return response()->json(
        $result['body'],
        $result['http_code']
    );
}

return response()->json([
    'status' => 'success',
    // 'payment_type' => strtolower($providerName),
    'data' => $result['body']['data']
], 200);
            // $result = $provider->generateUpiQr($payload, $user, $report);
            
            // return response()->json([
            //     'status' => 'success',
            //     'payment_type' => strtolower($providerName),
            //     'data' => $result['body']['data']
            // ], $result['http_code'] ?? 200);
        } catch (\Throwable $e) {
            return response()->json([
                'status'  => 'failed',
                'message' => $e->getMessage(),
            ], 500);
        }
    }
}