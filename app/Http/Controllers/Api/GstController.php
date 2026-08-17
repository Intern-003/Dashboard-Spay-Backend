<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Validator;
use App\Models\GstAdvanceVerification;
use Carbon\Carbon;
use Illuminate\Support\Facades\Log;

class GstController extends Controller
{
    public function advanceVerify(Request $request)
    {
        try {
            // ✅ 1) Validation
            $validator = Validator::make($request->all(), [
                'business_gstin_number' => 'required|string',
                'financial_year'        => 'required|string',
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'status'  => 'failed',
                    'message' => $validator->errors()->first(),
                ], 422);
            }

            // ✅ 2) Build URL safely
            $base = rtrim((string) env('NEXTBIGBOX_BASE_URL'), '/');
            $url  = $base . '/advance/gst/verify';

            // ✅ 3) Call External API
            $response = Http::withHeaders([
                'app-key'    => (string) env('NEXTBIGBOX_APP_KEY'),
                'app-secret' => (string) env('NEXTBIGBOX_APP_SECRET'),
                'Accept'     => 'application/json',
            ])->post($url, [
                "business_gstin_number" => $request->business_gstin_number,
                "financial_year"        => $request->financial_year,
            ]);

            // ✅ 4) Read JSON safely (avoid null / invalid json issues)
            $body = $response->json();
            if (!is_array($body)) {
                $body = [
                    'raw' => $response->body(),
                ];
            }

            $resp = (is_array($body) && isset($body['response']) && is_array($body['response']))
                ? $body['response']
                : [];

            // ✅ 5) IMPORTANT FIX:
            // NextBigBox may return HTTP 200 but success=false
            $apiSuccess = (bool) ($resp['success'] ?? false);

            // ✅ 6) Store in Database (store ALWAYS, success or failed)
            $record = GstAdvanceVerification::create([
                'gstin'            => $request->business_gstin_number,
                'financial_year'   => $request->financial_year,

                'request_id'       => $resp['request_id'] ?? null,
                'task_id'          => $resp['task_id'] ?? null,
                'group_id'         => $resp['group_id'] ?? null,

                'success'          => $apiSuccess,
                'response_code'    => $resp['response_code'] ?? null,
                'response_message' => $resp['response_message'] ?? null,

                'billable'         => $resp['metadata']['billable'] ?? null,

                'request_timestamp'  => !empty($resp['request_timestamp'])
                    ? Carbon::parse($resp['request_timestamp'])
                    : null,

                'response_timestamp' => !empty($resp['response_timestamp'])
                    ? Carbon::parse($resp['response_timestamp'])
                    : null,

                'raw_response'     => $body,              // make sure this column is JSON/longText
                'http_status'      => $response->status() // store actual HTTP status
            ]);

            // ✅ 7) Decide final status + message for YOUR API
            $statusText = $apiSuccess ? 'success' : 'failed';

            $message = $apiSuccess
                ? 'GST Advance Verification Stored Successfully'
                : ($resp['response_message'] ?? 'GST verification failed');

            /**
             * ✅ OPTIONAL BUT RECOMMENDED:
             * If API success=false return 422 (or 400) to frontend
             * If you want to keep same HTTP status from vendor, set $httpCode = $response->status()
             */
            $httpCode = $apiSuccess ? 200 : 422;

            // ✅ 8) Return Response
            return response()->json([
                'status'       => $statusText,
                'message'      => $message,
                'db_id'        => $record->id,
                'api_response' => $body,
            ], $httpCode);

        } catch (\Throwable $e) {

            Log::error('GST Advance Verify Error', [
                'error' => $e->getMessage(),
                'line'  => $e->getLine(),
                'file'  => $e->getFile(),
            ]);

            return response()->json([
                'status'  => 'error',
                'message' => 'Server error: ' . $e->getMessage(),
            ], 500);
        }
    }
}