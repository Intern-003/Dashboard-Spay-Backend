<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Validator;
use App\Models\DigilockerAadhaarPanLog;
use Carbon\Carbon;
use App\Models\DigilockerAadhaarFetchLog;
use App\Models\DigilockerPanFetchLog;
use App\Models\DigilockerTransactionLog;

class DigilockerController extends Controller
{
 public function initAadhaarPan()
{
    try {

        $purpose      = env('NEXTBIGBOX_PURPOSE');
        $responseUrl  = env('NEXTBIGBOX_RESPONSE_URL');
        $redirectUrl  = env('NEXTBIGBOX_REDIRECT_URL');

        if (!$purpose || !$responseUrl || !$redirectUrl) {
            return response()->json([
                'status' => 'failed',
                'message' => 'Missing required NEXTBIGBOX environment variables'
            ], 500);
        }

        // HTTPS check
        if (!str_starts_with($responseUrl, 'https://')) {
            return response()->json([
                'status' => 'failed',
                'message' => 'response_url must be https'
            ], 422);
        }

        if (!str_starts_with($redirectUrl, 'https://')) {
            return response()->json([
                'status' => 'failed',
                'message' => 'redirect_url must be https'
            ], 422);
        }

        $url = rtrim(env('NEXTBIGBOX_BASE_URL'), '/') . '/digilocker/init/initAadhaarPan';

        $payload = [
            "purpose"      => $purpose,
            "response_url" => $responseUrl,
            "redirect_url" => $redirectUrl
        ];

        $response = Http::timeout(30)->withHeaders([
            'app-key'    => env('NEXTBIGBOX_APP_KEY'),
            'app-secret' => env('NEXTBIGBOX_APP_SECRET'),
            'Accept'     => 'application/json',
        ])->post($url, $payload);

        $body = $response->json() ?? [];

        $success    = (bool)($body['success'] ?? false);
        $apiMessage = $body['message'] ?? null;

        $requestId  = data_get($body, 'data.request_id');
        $webhookKey = data_get($body, 'data.webhook_security_key');
        $expiresAt  = data_get($body, 'data.expires_at');
        $sdkUrl     = data_get($body, 'data.sdk_url');

        $log = DigilockerAadhaarPanLog::create([
            'purpose'      => $purpose,
            'response_url' => $responseUrl,
            'redirect_url' => $redirectUrl,

            'success'      => $success,
            'api_message'  => $apiMessage,

            'request_id'           => $requestId,
            'webhook_security_key' => $webhookKey,
            'expires_at'           => $expiresAt ? Carbon::parse($expiresAt) : null,
            'sdk_url'              => $sdkUrl,

            'http_status'  => $response->status(),
            'raw_response' => $body,
        ]);

        return response()->json([
            'status'  => ($response->successful() && $success) ? 'success' : 'failed',
            'message' => 'Digilocker Aadhaar PAN init successful',
            'db_id'   => $log->id,
            'data'    => [
                'request_id' => $requestId,
                'expires_at' => $expiresAt,
                'sdk_url'    => $sdkUrl
            ],
            'api' => $body
        ], $response->status());

    } catch (\Exception $e) {
        return response()->json([
            'status' => 'error',
            'message' => $e->getMessage()
        ], 500);
    }
}
	
	public function fetchAadhaar(Request $request)
{
    try {
        $validator = \Validator::make($request->all(), [
            'request_id' => 'required|string',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status'  => 'failed',
                'message' => $validator->errors()->first(),
            ], 422);
        }

        $requestId = trim((string)$request->request_id);

        $url = rtrim(env('NEXTBIGBOX_BASE_URL'), '/') . "/digilocker/fetch/{$requestId}/aadhaar";

        $response = \Http::withHeaders([
            'Accept'     => 'application/json',
        ])->get($url);

        $body = $response->json();

        // ✅ If response is not JSON, capture raw
        if (!is_array($body)) {
            $raw = $response->body();
            return response()->json([
                'status'      => 'failed',
                'message'     => 'Non-JSON response from provider',
                'http_status' => $response->status(),
                'provider_raw'=> $raw,
            ], 502);
        }

        /**
         * ✅ Handle both shapes:
         * A) { "success": true, "data": {...} }
         * B) { "response": { "success": true, "data": {...} } }
         */
        $root = $body['response'] ?? $body;

        $success = (bool)($root['success'] ?? false);
        $apiMsg  = $root['message'] ?? ($body['message'] ?? null);

        $data = $root['data'] ?? []; // <== this is your main object

        // Extract common fields safely
        $aadhaarData = $data['aadhaar_data'] ?? [];
        $personal    = $aadhaarData['personal_info'] ?? [];

        // ✅ Store DB (ONLY if you want even for failed, keep it)
        $log = \App\Models\DigilockerAadhaarFetchLog::create([
            'request_id'     => $requestId,
            'success'        => $success,
            'status'         => $data['status'] ?? null,
            'document_name'  => $data['document_name'] ?? null,
            'issuer'         => $data['issuer'] ?? null,
            'issue_date'     => !empty($data['issue_date']) ? \Carbon\Carbon::parse($data['issue_date']) : null,
            'fetched_at'     => !empty($data['fetched_at']) ? \Carbon\Carbon::parse($data['fetched_at']) : null,
            'name'           => $personal['name'] ?? null,
            'dob'            => $personal['dob'] ?? null,
            'gender'         => $personal['gender'] ?? null,
            'uid'            => $aadhaarData['uid'] ?? null,
            'photo_base64'   => $aadhaarData['photo_base64'] ?? null,
            'raw_response'   => $body,
            'http_status'    => $response->status(),
        ]);

        // ✅ Decide message + status properly
        $finalStatus = ($response->successful() && $success) ? 'success' : 'failed';
        $finalMessage = $apiMsg ?: (($finalStatus === 'success') ? 'Aadhaar fetched successfully' : 'Aadhaar fetch failed');

        return response()->json([
            'status'      => $finalStatus,
            'message'     => $finalMessage,
            'db_id'       => $log->id,
            'http_status' => $response->status(),

            // ✅ Return provider response for debugging (remove later in production)
            'provider'    => $body,

            // ✅ Return parsed data also
            'data'        => $data,
        ], $response->status());

    } catch (\Exception $e) {
        return response()->json([
            'status'  => 'error',
            'message' => $e->getMessage(),
        ], 500);
    }


}

 public function fetchPan(Request $request)
    {
        try {
            $validator = Validator::make($request->all(), [
                'request_id' => 'required|string',
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'status'  => 'failed',
                    'message' => $validator->errors()->first(),
                ], 422);
            }

            $requestId = trim((string) $request->request_id);

            $url = rtrim(env('NEXTBIGBOX_BASE_URL'), '/') . "/digilocker/fetch/{$requestId}/pan";

            $response = Http::withHeaders([
                'Accept' => 'application/json',
            ])->get($url);

            $body = $response->json();

            // If provider response is not JSON
            if (!is_array($body)) {
                $raw = $response->body();

                return response()->json([
                    'status'       => 'failed',
                    'message'      => 'Non-JSON response from provider',
                    'http_status'  => $response->status(),
                    'provider_raw' => $raw,
                ], 502);
            }

            /**
             * Handle both response types:
             * A) { "success": true, "data": {...} }
             * B) { "response": { "success": true, "data": {...} } }
             */
            $root = $body['response'] ?? $body;

            $success = (bool) ($root['success'] ?? false);
            $apiMsg  = $root['message'] ?? ($body['message'] ?? null);

            $data = $root['data'] ?? [];
            $panData = $data['pancard_data'] ?? [];

            $log = DigilockerPanFetchLog::create([
                'request_id'         => $data['request_id'] ?? $requestId,
                'success'            => $success,
                'status'             => $data['status'] ?? null,
                'document_name'      => $data['document_name'] ?? null,
                'issuer'             => $data['issuer'] ?? null,
                'issue_date'         => !empty($data['issue_date']) ? Carbon::parse($data['issue_date']) : null,
                'fetched_at'         => !empty($data['fetched_at']) ? Carbon::parse($data['fetched_at']) : null,
                'certificate_number' => $panData['certificate_number'] ?? null,
                'pan_number'         => $panData['pan_number'] ?? null,
                'holder_name'        => $panData['holder_name'] ?? null,
                'holder_dob'         => $panData['holder_dob'] ?? null,
                'pdf_base64'         => $data['pdf_base64'] ?? null,
                'raw_response'       => $body,
                'http_status'        => $response->status(),
            ]);

            $finalStatus  = ($response->successful() && $success) ? 'success' : 'failed';
            $finalMessage = $apiMsg ?: (($finalStatus === 'success') ? 'PAN fetched successfully' : 'PAN fetch failed');

            return response()->json([
                'status'      => $finalStatus,
                'message'     => $finalMessage,
                'db_id'       => $log->id,
                'http_status' => $response->status(),
                'provider'    => $body,
                'data'        => $data,
            ], $response->status());

        } catch (\Exception $e) {
            return response()->json([
                'status'  => 'error',
                'message' => $e->getMessage(),
            ], 500);
        }
    }


public function fetchDocuments(Request $request)
    {
        try {
            $validator = Validator::make($request->all(), [
                'request_id' => 'required|string',
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'status'  => 'failed',
                    'message' => $validator->errors()->first(),
                ], 422);
            }

            $requestId = trim((string) $request->request_id);

            $url = rtrim(env('NEXTBIGBOX_BASE_URL'), '/') . "/digilocker/fetch/{$requestId}";

            $response = Http::withHeaders([
                'Accept' => 'application/json',
            ])->get($url);

            $body = $response->json();

            if (!is_array($body)) {
                return response()->json([
                    'status'       => 'failed',
                    'message'      => 'Non-JSON response from provider',
                    'http_status'  => $response->status(),
                    'provider_raw' => $response->body(),
                ], 502);
            }

            $success   = (bool)($body['success'] ?? false);
            $data      = $body['data'] ?? [];
            $txn       = $data['transaction'] ?? [];
            $documents = $data['documents'] ?? [];

            $aadhaar = $documents['aadhaar'] ?? [];
            $pan     = $documents['pan'] ?? [];

            /*
            |--------------------------------------------------------------------------
            | Save Transaction Log
            |--------------------------------------------------------------------------
            */
            $txnLog = DigilockerTransactionLog::create([
                'request_id'          => $txn['request_id'] ?? $requestId,
                'status'              => $txn['status'] ?? null,
                'response_code'       => $txn['response_code'] ?? null,
                'response_message'    => $txn['response_message'] ?? null,
                'billable'            => $txn['billable'] ?? null,
                'provider_created_at' => !empty($txn['created_at']) ? Carbon::parse($txn['created_at']) : null,
                'provider_updated_at' => !empty($txn['updated_at']) ? Carbon::parse($txn['updated_at']) : null,
                'raw_response'        => $txn,
                'http_status'         => $response->status(),
            ]);

            /*
            |--------------------------------------------------------------------------
            | Save Aadhaar Log
            |--------------------------------------------------------------------------
            */
            $aadhaarLog = null;

            if (!empty($aadhaar)) {
                $aadhaarData = $aadhaar['aadhaar_data'] ?? [];
                $personal    = $aadhaarData['personal_info'] ?? [];

                $aadhaarLog = DigilockerAadhaarFetchLog::create([
                    'request_id'   => $aadhaar['request_id'] ?? $requestId,
                    'success'      => $success,
                    'status'       => $aadhaar['status'] ?? null,
                    'document_name'=> $aadhaar['document_name'] ?? null,
                    'issuer'       => $aadhaar['issuer'] ?? null,
                    'issue_date'   => !empty($aadhaar['issue_date']) ? Carbon::parse($aadhaar['issue_date']) : null,
                    'fetched_at'   => !empty($aadhaar['fetched_at']) ? Carbon::parse($aadhaar['fetched_at']) : null,

                    'name'         => $personal['name'] ?? null,
                    'dob'          => $personal['dob'] ?? null,
                    'gender'       => $personal['gender'] ?? null,
                    'uid'          => $aadhaarData['uid'] ?? null,
                    'photo_base64' => $aadhaarData['photo_base64'] ?? null,

                    'address'      => $aadhaarData['address'] ?? null,
                    'local_info'   => $aadhaarData['local_info'] ?? null,
                    'kyc_metadata' => $aadhaarData['kyc_metadata'] ?? null,

                    'xml_data'     => $aadhaar['xml_data'] ?? null,
                    'pdf_base64'   => $aadhaar['pdf_base64'] ?? null,

                    'raw_response' => $aadhaar,
                    'http_status'  => $response->status(),
                ]);
            }

            /*
            |--------------------------------------------------------------------------
            | Save PAN Log
            |--------------------------------------------------------------------------
            */
            $panLog = null;

            if (!empty($pan)) {
                $panData = $pan['pancard_data'] ?? [];

                $panLog = DigilockerPanFetchLog::create([
                    'request_id'         => $pan['request_id'] ?? $requestId,
                    'success'            => $success,
                    'status'             => $pan['status'] ?? null,
                    'document_name'      => $pan['document_name'] ?? null,
                    'issuer'             => $pan['issuer'] ?? null,
                    'issue_date'         => !empty($pan['issue_date']) ? Carbon::parse($pan['issue_date']) : null,
                    'fetched_at'         => !empty($pan['fetched_at']) ? Carbon::parse($pan['fetched_at']) : null,

                    'certificate_number' => $panData['certificate_number'] ?? null,
                    'pan_number'         => $panData['pan_number'] ?? null,
                    'holder_name'        => $panData['holder_name'] ?? null,
                    'holder_dob'         => $panData['holder_dob'] ?? null,

                    'pdf_base64'         => $pan['pdf_base64'] ?? null,

                    'raw_response'       => $pan,
                    'http_status'        => $response->status(),
                ]);
            }

            $finalStatus = ($response->successful() && $success) ? 'success' : 'failed';
            $finalMessage = $txn['response_message'] ?? (($finalStatus === 'success')
                ? 'Documents fetched successfully'
                : 'Documents fetch failed');

            return response()->json([
                'status'      => $finalStatus,
                'message'     => $finalMessage,
                'http_status' => $response->status(),

                'transaction_log_id' => $txnLog?->id,
                'aadhaar_log_id'     => $aadhaarLog?->id,
                'pan_log_id'         => $panLog?->id,

                'data' => [
                    'transaction' => $txn,
                    'aadhaar'     => $aadhaar,
                    'pan'         => $pan,
                ],
            ], $response->status());

        } catch (\Exception $e) {
            return response()->json([
                'status'  => 'error',
                'message' => $e->getMessage(),
            ], 500);
        }
    }
}