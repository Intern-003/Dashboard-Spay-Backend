<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\VideoKycSession;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Storage;
use App\Models\User;

class VideoKycController extends Controller
{
    /**
     * ✅ Create VKYC Session + Generate Link
     */
   public function createSession(Request $request)
{
    try {

        $sessionId = (string) \Str::uuid();

        // ✅ Set all values manually
        $purpose      = "Aadhaar and PAN verification for Video KYC";
        $response_url = "https://omishajewels.co.in/api/digilocker/webhook";
         $redirect_url = "https://uatdashboard.spay.live/";

        $customer_name   = "Test User";
        $customer_email  = "test@gmail.com";
        $customer_mobile = "7894561230";
       $user_id = $request->user_id;

        $session = VideoKycSession::create([
            'session_id'      => $sessionId,
            'user_id'         => $user_id,
            'purpose'         => $purpose,
            'response_url'    => $response_url,
            'redirect_url'    => $redirect_url,

            'customer_name'   => $customer_name,
            'customer_email'  => $customer_email,
            'customer_mobile' => $customer_mobile,

            'status'          => 'created',
            'meta'            => [
                'ip' => $request->ip(),
                'ua' => $request->userAgent(),
            ],
        ]);

        $link = url("/vkyc/{$sessionId}");

        return response()->json([
            'status'  => 'success',
            'message' => 'VKYC link generated',
            'data'    => [
                'session_id'   => $session->session_id,
                'purpose'      => $session->purpose,
                'response_url' => $session->response_url,
                'redirect_url' => $session->redirect_url,
                'link'         => $link,
            ],
        ]);

    } catch (\Exception $e) {

        return response()->json([
            'status'  => 'error',
            'message' => $e->getMessage(),
        ], 500);
    }
}

    /**
     * ✅ Mark Started (One-time protection)
     */
    public function markStarted(Request $request, string $session_id)
    {
        try {
            $session = VideoKycSession::where('session_id', $session_id)->firstOrFail();

            if (in_array($session->status, ['uploaded', 'verified', 'rejected'])) {
                return response()->json([
                    'status'  => 'failed',
                    'message' => 'VKYC already completed. Link cannot be used again.',
                ], 403);
            }

            if ($session->status === 'started') {
                return response()->json([
                    'status'  => 'success',
                    'message' => 'Session already started',
                ]);
            }

            $session->update([
                'status'     => 'started',
                'started_at' => now(),
            ]);

            return response()->json([
                'status'  => 'success',
                'message' => 'Session started',
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'status'  => 'error',
                'message' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * ✅ Upload Video + Save + Return completed_url immediately (FAST)
     * ✅ Webhook moved to queue job (NO WAIT)
     */
    public function uploadVideo(Request $request, string $session_id)
    {
        try {
                $session = VideoKycSession::where('session_id', $session_id)->firstOrFail();

            // ✅ Block re-upload / one-time only
            if (in_array($session->status, ['uploaded', 'verified', 'rejected'])) {
                return response()->json([
                    'status'  => 'failed',
                    'message' => 'VKYC already completed. Upload not allowed again.',
                ], 409);
            }

            // ✅ Optional: upload only if session started
            if (!in_array($session->status, ['started', 'created'])) {
                return response()->json([
                    'status'  => 'failed',
                    'message' => 'Invalid session state for upload.',
                ], 400);
            }

            $validator = Validator::make($request->all(), [
                'video' => 'required|file|max:51200|mimetypes:video/webm,video/mp4,video/quicktime,video/x-matroska,application/octet-stream',
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'status'  => 'failed',
                    'message' => $validator->errors()->first(),
                ], 422);
            }

            // $file = $request->file('video');

            // // ✅ Unique filename
            // $ext = $file->getClientOriginalExtension() ?: 'webm';
            // $filename = 'vkyc_' . now()->format('Ymd_His') . '_' . Str::random(8) . '.' . $ext;

            // $path = $file->storeAs("vkyc/{$session_id}", $filename, 'public');
            // $videoUrl = Storage::disk('public')->url($path);

            if ($request->hasFile('video')) {
                $file = $request->file('video');
            
                $ext = $file->getClientOriginalExtension() ?: 'webm';
                $filename = 'vkyc_' . now()->format('Ymd_His') . '_' . Str::random(8) . '.' . $ext;
            
                $path = $file->storeAs("vkyc/{$session_id}", $filename, 'public');
            
                if (!$path) {
                    throw new \Exception("File upload failed");
                }
            
                $videoUrl = Storage::disk('public')->url($path);
            } else {
                throw new \Exception("No video file uploaded");
            }

            // ✅ Update DB instantly
            $session->update([
                'status'     => 'uploaded',
                'ended_at'   => now(),
                'video_path' => $path,
                'video_mime' => $file->getMimeType(),
                'video_size' => $file->getSize(),
            ]);

            // ✅ webhook async (NO DELAY)
            if (!empty($session->response_url)) {
                try {
                    dispatch(new \App\Jobs\SendVkycWebhookJob($session->session_id));
                } catch (\Exception $e) {
                    // If queue not configured, ignore (still return fast)
                }
            }

            return response()->json([
                'status'  => 'success',
                'message' => 'Video uploaded & saved',
                'data'    => [
                    'video_url'     => $videoUrl,
                    'redirect_url'  => $session->redirect_url,
                    'completed_url' => url("/vkyc/{$session_id}/completed"),
                ],
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'status'  => 'error',
                'message' => $e->getMessage(),
            ], 500);
        }
    }
    
    public function getStatus(string $session_id)
    {
        try {
    
            $session = VideoKycSession::where(
                'session_id',
                $session_id
            )->firstOrFail();
    
            return response()->json([
                'status' => 'success',
                'data' => [
                    'session_status' => $session->status
                ]
            ]);
    
        } catch (\Exception $e) {
    
            return response()->json([
                'status' => 'error',
                'message' => $e->getMessage()
            ],500);
    
        }
    }
}