<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Validator;
use App\Models\BankVerificationLog;
use Carbon\Carbon;

class BankVerificationController extends Controller
{
    public function verifyAdvance(Request $request)
    {
        try {

            // ✅ Validation
            $validator = Validator::make($request->all(), [
                'account_number' => 'required|string',
                'ifsc'           => 'required|string',
                'name_to_match'  => 'required|string',
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'status' => 'failed',
                    'message' => $validator->errors()->first()
                ], 422);
            }

            $url = env('NEXTBIGBOX_BASE_URL') . 'bank/account/verification/advance';

            $payload = [
                "account_number" => $request->account_number,
                "ifsc"           => $request->ifsc,
                "name_to_match"  => $request->name_to_match,
            ];

            $response = Http::withHeaders([
                'app-key'    => env('NEXTBIGBOX_APP_KEY'),
                'app-secret' => env('NEXTBIGBOX_APP_SECRET'),
                'Accept'     => 'application/json',
            ])->post($url, $payload);

            $body = $response->json() ?? [];
            $resp = $body['response'] ?? [];

            // ✅ Store in DB
            $record = BankVerificationLog::create([
                'account_number'   => $request->account_number,
                'ifsc'             => $request->ifsc,
                'name_to_match'    => $request->name_to_match,

                'request_id'       => $resp['request_id'] ?? null,
                'task_id'          => $resp['task_id'] ?? null,
                'group_id'         => $resp['group_id'] ?? null,

                'success'          => (bool)($resp['success'] ?? false),
                'response_code'    => $resp['response_code'] ?? null,
                'response_message' => $resp['response_message'] ?? null,

                'billable'         => data_get($resp, 'metadata.billable'),
                'reason_code'      => data_get($resp, 'metadata.reason_code'),
                'reason_message'   => data_get($resp, 'metadata.reason_message'),

                'beneficiary_name' => data_get($resp, 'result.beneficiary_name'),
                'verification_status' => data_get($resp, 'result.verification_status'),
                'name_match_score' => data_get($resp, 'result.name_match_score'),
                'is_penny_drop'    => data_get($resp, 'result.is_penny_drop'),

                'request_timestamp'  => isset($resp['request_timestamp'])
                    ? Carbon::parse($resp['request_timestamp']) : null,

                'response_timestamp' => isset($resp['response_timestamp'])
                    ? Carbon::parse($resp['response_timestamp']) : null,

                'http_status' => $response->status(),
                'raw_response' => $body,
            ]);

            return response()->json([
                'status' => $response->successful() ? 'success' : 'failed',
                'message' => 'Bank verification stored successfully',
                'db_id' => $record->id,
                'api_response' => $body
            ], $response->status());

        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => $e->getMessage()
            ], 500);
        }
    }
}