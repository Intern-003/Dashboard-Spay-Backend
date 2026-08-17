<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Validator;
use App\Models\EmailOtpLog;
use App\Models\Otp;
use Carbon\Carbon;

class EmailOtpController extends Controller
{
    public function sendEmailOtp(Request $request)
    {
        try {
            // ✅ Validate only email (name optional)
            $validator = Validator::make($request->all(), [
                'email' => 'required|email',
                'name'  => 'nullable|string|max:100',
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'status'  => 'failed',
                    'message' => $validator->errors()->first(),
                ], 422);
            }

            // ✅ Generate OTP (4 digit)
            $otp = rand(100000, 999999);

            $name = $request->name ?? 'User';
            $subject = env('EMAIL_OTP_SUBJECT', 'Your OTP for SPAY Verification');

            // ✅ Email HTML message
            $html = "
                <div style='font-family:Arial,sans-serif'>
                    <h2>SPAY Email Verification</h2>
                    <p>Hi {$name},</p>
                    <p>Your OTP is:</p>
                    <h1 style='letter-spacing:4px'>{$otp}</h1>
                    <p>This OTP is valid for 10 minutes.</p>
                    <p>Thanks,<br/>Spay Fintech Pvt Ltd</p>
                </div>
            ";

            // ✅ NextBigBox API URL
            $url = env('NEXTBIGBOX_BASE_URL') . 'send/dynamic/email';

            // ✅ Payload for dynamic email API
            $payload = [
                "to" => [
                    [
                        "email" => $request->email,
                        "name"  => $name,
                    ]
                ],
                "subject" => $subject,
                "message" => $html,
            ];

            // ✅ Call API with required headers
            $response = Http::withHeaders([
                'app-key'    => env('NEXTBIGBOX_APP_KEY'),
                'app-secret' => env('NEXTBIGBOX_APP_SECRET'),
                'Accept'     => 'application/json',
            ])->post($url, $payload);

            $body = $response->json() ?? [];

            // ✅ Extract response
            $status = (bool)($body['status'] ?? false);
            $apiMessage = $body['message'] ?? null;

            $providerStatus  = data_get($body, 'data.status');
            $providerMessage = data_get($body, 'data.message');
            $messageId       = data_get($body, 'data.data.message_id');

            // ✅ Save DB
            $log = EmailOtpLog::create([
                'email'            => $request->email,
                'name'             => $name,
                'otp'              => (string)$otp,
                'otp_status'       => 'active',

                'subject'          => $subject,
                'message'          => $html,

                'status'           => $status,
                'api_message'      => $apiMessage,
                'provider_status'  => $providerStatus,
                'provider_message' => $providerMessage,
                'message_id'       => $messageId,

                'http_status'      => $response->status(),
                'raw_response'     => $body,
            ]);
            
 Otp::create([
        'email'      => $request->email,
        'mobile'     => $request->mobile,
        'otp'        => $otp,
        'expires_at' => Carbon::now()->addMinutes(5),
    ]);

            return response()->json([
                'status'  => ($response->successful() && $status) ? 'success' : 'failed',
                'message' => 'Email OTP sent and stored successfully',
                'db_id'   => $log->id,

                // ⚠ remove in production
                // 'otp_for_testing' => $otp,

                'api'     => $body,
            ], $response->status());

        } catch (\Exception $e) {
            return response()->json([
                'status'  => 'error',
                'message' => $e->getMessage(),
            ], 500);
        }
    }
}