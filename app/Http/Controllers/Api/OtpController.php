<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Validator;
use App\Models\OtpCustomMobileLog;
use App\Models\Otp;
use Carbon\Carbon;

class OtpController extends Controller
{
    public function sendCustomMobileOtp(Request $request)
    {
        try {

            // ✅ 1. Validate only mobile
            $validator = Validator::make($request->all(), [
                'mobile' => 'required|digits_between:10,15',
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'status'  => 'failed',
                    'message' => $validator->errors()->first(),
                ], 422);
            }

            // ✅ 2. Auto Generate 4 Digit OTP
            $otp = rand(100000, 999999);

            // ✅ 3. Build Message
            $message = "Use {$otp} to verify your phone number. This OTP is valid for 10 minutes. Spay Fintech Pvt Ltd";

            // ✅ 4. External API URL
            $url = env('NEXTBIGBOX_BASE_URL') . 'send/custom/mobile/otp';

            // ✅ 5. Payload
            $payload = [
                "user"       => env('NBB_OTP_USER', 'spay'),
                "authkey"    => env('NBB_OTP_AUTHKEY'),
                "sender"     => env('NBB_OTP_SENDER', 'SPFTCH'),
                "mobile"     => (string)$request->mobile,
                "templateid" => env('NBB_OTP_TEMPLATEID'),
                "message"    => $message,
            ];

            // ✅ 6. API Call WITH app-key & app-secret headers
            $response = Http::withHeaders([
                'app-key'    => env('NEXTBIGBOX_APP_KEY'),
                'app-secret' => env('NEXTBIGBOX_APP_SECRET'),
                'Accept'     => 'application/json',
            ])->post($url, $payload);

            $body = $response->json() ?? [];

            // ✅ 7. Extract Response
            $status     = (bool)($body['status'] ?? false);
            $apiMessage = $body['message'] ?? null;

            $code      = data_get($body, 'data.RESPONSE.CODE');
            $info      = data_get($body, 'data.RESPONSE.INFO');
            $uid       = data_get($body, 'data.RESPONSE.UID');
            $apiStatus = data_get($body, 'data.STATUS');

            // ✅ 8. Store in DB
            $log = OtpCustomMobileLog::create([
                'user'        => $payload['user'],
                'sender'      => $payload['sender'],
                'templateid'  => $payload['templateid'],
                'mobile'      => (string)$request->mobile,
				'otp'         => $otp,                 // ✅ store OTP
				'otp_status'  => 'active',             // ✅ default active
                'message'     => $message,

                'status'      => $status,
                'api_message' => $apiMessage,

                'code'        => $code,
                'info'        => $info,
                'uid'         => $uid,
                'api_status'  => $apiStatus,

                'http_status' => $response->status(),
                'raw_response'=> $body,
            ]);
            
            // Save OTP
Otp::create([
    'mobile'     => $request->mobile,
    'otp'        => $otp,
    'expires_at' => Carbon::now()->addMinutes(5),
]);

            // ✅ 9. Return Response
            return response()->json([
                'status'   => $response->successful() && $status ? 'success' : 'failed',
                'message'  => 'OTP Sent Successfully',
                // 'otp_for_testing' => $otp, // ⚠ remove in production
                'db_id'    => $log->id,
                'api'      => $body,
            ], $response->status());

        } catch (\Exception $e) {
            return response()->json([
                'status'  => 'error',
                'message' => $e->getMessage(),
            ], 500);
        }
    }
}