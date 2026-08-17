<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\User;
use App\Models\Report;
use App\Models\Credential;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;
use Illuminate\Support\Facades\Session;
use App\Models\Otp;
use App\Models\VideoKycSession;


class MerchantController extends Controller
{
    function createMerchant(Request $request)
    {
    try {
        // Validate main merchant fields
        $validatedData = $request->validate([
            'name'                  => 'required|string|max:255',
            'email'                 => 'required|email|unique:users,email',
            'mobile_no'             => 'required|string|max:15|unique:users,mobile_no',
            'password'              => 'nullable|string|min:6',
        ]);
        // Check if email & mobile are verified
        $emailVerified = Otp::where('email', $validatedData['email'])
            ->whereNotNull('verified_at')
            ->exists();

        $mobileVerified = Otp::where('mobile', $validatedData['mobile_no'])
            ->whereNotNull('verified_at')
            ->exists();

        if (!$emailVerified || !$mobileVerified) {
            return response()->json([
                'message' => 'Both email and mobile must be verified before creating a merchant.'
            ], 403);
        }
        // Encrypt password if not provided
        $validatedData['password'] = bcrypt($validatedData['password'] ?? $validatedData['mobile_no']);


        // Create merchant
        $merchant = User::create($validatedData);
        // dd($merchant);
        return response()->json([
            'message'  => 'Merchant onboarded successfully',
            'merchant' => $merchant,
        ], 201);

    } catch (\Illuminate\Validation\ValidationException $e) {
        return response()->json([
            'error_code' => 422,
            'message'    => 'Validation failed',
            'errors'     => $e->errors()
        ], 422);
    }
}
  
  function sendOTP(Request $request){
         $request->validate([
        'email'  => 'nullable|email',
        'mobile' => 'nullable|string|min:10|max:15',
    ]);

    // 🚫 If mobile OTP requested → check email verified
    if ($request->mobile) {
        $emailVerified = Otp::where('email', $request->email)
            ->whereNotNull('verified_at')
            ->exists();

        if (!$emailVerified) {
            return response()->json([
                'message' => 'Please verify email first'
            ], 403);
        }
    }

    $otp = rand(100000, 999999);

    Otp::where(function ($q) use ($request) {
        if ($request->email) {
            $q->where('email', $request->email);
        }
        if ($request->mobile) {
            $q->where('mobile', $request->mobile);
        }
    })->delete();


    Otp::create([
        'email'      => $request->email,
        'mobile'     => $request->mobile,
        'otp'        => $otp,
        'expires_at' => Carbon::now()->addMinutes(5),
    ]);

    return response()->json([
        'message' => 'OTP sent',
        'otp' => $otp // remove in prod
    ]);
  }
  
        public function verifyEmailOtp(Request $request)
         {
     $request->validate([
        'email'  => 'nullable|email',
        'mobile' => 'nullable|string',
        'otp'    => 'required|string',
    ]);

    $otpRow = Otp::where('otp', $request->otp)
        ->where(function ($q) use ($request) {
            if ($request->email) {
                $q->where('email', $request->email);
            }
            if ($request->mobile) {
                $q->where('mobile', $request->mobile);
            }
        })
        ->first();

    if (!$otpRow) {
        return response()->json(['message' => 'Invalid OTP'], 400);
    }

    if (Carbon::now()->gt($otpRow->expires_at)) {
        $otpRow->delete();
        return response()->json(['message' => 'OTP expired'], 400);
    }

    // ✅ mark verified
    $otpRow->verified_at = Carbon::now();
    $otpRow->save();

    return response()->json([
        'message' => 'OTP verified successfully',
        'type'    => $request->email ? 'email' : 'mobile'
    ]);
  }
  

//   function  merchantKyc(Request $request)
//  {
//     try {
//         // Validate main merchant fields
//         $validatedData = $request->validate([
            
//             'id'                => 'required|exists:users,id',
//             'business_mcc'          => 'nullable|string|max:50',
//             'company_type'          => 'nullable|string',
//             'company_pan_no'        => 'nullable|string|max:20',
//             'company_pan_no_doc'    => 'nullable|file',
//             'company_gst_no_doc'    => 'nullable|file',
//             'cancel_cheque_doc'     => 'nullable|file',
//             'company_gst_no'        => 'nullable|string|max:20',
//             'cin_llpin'             => 'nullable|string|max:50',
//             'date_of_incorporation' => 'nullable|date',
//             'account_holder_name'   => 'nullable|string|max:255',
//             'bank_account_no'       => 'nullable|string|max:50',
//             'ifsc_code'             => 'nullable|string|max:20',
//             'address'               => 'nullable|string|max:255',
//             'city'                  => 'nullable|string|max:100',
//             'district'              => 'nullable|string|max:100',
//             'state'                 => 'nullable|string|max:100',
//             'pin_code'              => 'nullable|string|max:10',
//             'website_url'           => 'nullable|url|max:255',
//             'director_info'         => 'nullable|array',
//             'director_info.*.director_name'     => 'nullable:director_info|string|max:255',
//             'director_info.*.director_pan_no'   => 'nullable:director_info|string|max:20',
//             'director_info.*.director_aadhar_no'=> 'nullable:director_info|string|max:20',
//             'director_info.*.director_gender'   => 'nullable:director_info|string|in:male,female,other',
//             'director_info.*.director_dob'      => 'nullable:director_info|date',
//             'director_info.*.user_pan_doc'      => 'nullable|file',
//             'director_info.*.user_addhar_doc'   => 'nullable|file',
//           'video_kyc' => 'nullable|file|mimetypes:video/*', 
//             // max 100MB (adjust as needed)

//         ]);

//         $merchant = User::findOrFail($validatedData['id']);
//         // Handle main merchant file uploads
//         $fileFields = [
//             'company_pan_no_doc' => 'company_pan_docs',
//             'company_gst_no_doc' => 'company_gst_docs',
//             'cancel_cheque_doc'  => 'cancel_cheque_docs',
//         ];

//         foreach ($fileFields as $field => $folder) {
//             if ($request->hasFile($field)) {
//                 $path = $request->file($field)->store($folder, 'public');
//                 $validatedData[$field] = storage_path('app/public/' . $path);
//             } else {
//                 $validatedData[$field] = null;
//             }
//         }

//             // Handle video KYC file
//                 // if ($request->hasFile('video_kyc')) {
//                 //     $video = $request->file('video_kyc');
//                 //     // Optional: generate unique filename
//                 //     $filename = time() . '_' . $video->getClientOriginalName();
                
//                 //     // Move to public/videokyc
//                 //     $video->move(public_path('videokyc'), $filename);
                
//                 //     // Save path in DB (relative to public)
//                 //     $validatedData['video_kyc'] = 'videokyc/' . $filename;
//                 // } else {
//                 //     $validatedData['video_kyc'] = null;
//                 // }



//         // Handle director files
//         $directors = [];
//         if (!empty($validatedData['director_info'])) {
//             foreach ($validatedData['director_info'] as $index => $director) {
//                 // For each director, store files if uploaded
//                 if ($request->hasFile("director_info.$index.user_pan_doc")) {
//                     $director['user_pan_doc'] = storage_path(
//                         'app/public/' . $request->file("director_info.$index.user_pan_doc")->store('director_pan_docs', 'public')
//                     );
//                 }

//                 if ($request->hasFile("director_info.$index.user_addhar_doc")) {
//                     $director['user_addhar_doc'] = storage_path(
//                         'app/public/' . $request->file("director_info.$index.user_addhar_doc")->store('director_aadhar_docs', 'public')
//                     );
//                 }

//                 $directors[] = $director;
//             }
//         }

//         $validatedData['director_info'] = $directors;
//         $vkycPath = null;

//         // ✅ Auto fetch latest completed VKYC session
//         $vkycSession = \App\Models\VideoKycSession::where('user_id', $validatedData['id'])
//             ->whereIn('status', ['uploaded', 'verified']) // only completed
//             ->latest()
//             ->first();
        
//         if ($vkycSession && !empty($vkycSession->video_path)) {
//             $validatedData['video_kyc'] = $vkycSession->video_path;
//         }

//          unset($validatedData['id']);

//     // update merchant
//       $merchant->update($validatedData);
//       $merchant->pre_kyc = 1;
//       $merchant->save();

//         return response()->json([
//             'message'  => 'KYC completed',
//             'merchant' => $merchant,
//         ], 201);

//     } catch (\Illuminate\Validation\ValidationException $e) {
//         return response()->json([
//             'error_code' => 422,
//             'message'    => 'Validation failed',
//             'errors'     => $e->errors()
//         ], 422);
//     }
// }

function merchantKyc(Request $request)
{
    try {
        $validatedData = $request->validate([
            'id' => 'required|exists:users,id',
        ]);

        $merchant = User::findOrFail($validatedData['id']);

        // All actual KYC data (business, bank, director, video) was already
        // persisted step-by-step via MerchantKycDraftController::saveStep().
        // Final submit just verifies everything required is present, then
        // flips pre_kyc = 1 so the record is marked complete.
        $requiredFields = [
            'company_gst_no',
            'company_gst_no_doc',
            'address',
            'city',
            'district',
            'state',
            'pin_code',
            'business_mcc',
            'company_pan_no',
            'company_pan_no_doc',
            'cin_llpin',
            'company_type',
            'cancel_cheque_doc',
            'account_holder_name',
            'bank_account_no',
            'ifsc_code',
            'date_of_incorporation',
            'director_info',
            'video_kyc',
        ];

        $missing = [];
        foreach ($requiredFields as $field) {
            if (empty($merchant->{$field})) {
                $missing[] = $field;
            }
        }

        if (!empty($missing)) {
            return response()->json([
                'error_code' => 422,
                'message'    => 'Please complete all previous steps before submitting.',
                'errors'     => ['missing_fields' => $missing],
            ], 422);
        }

        $merchant->pre_kyc      = 1;
        $merchant->current_step = 5;
        $merchant->save();

        return response()->json([
            'message'  => 'KYC completed',
            'merchant' => $merchant,
        ], 201);

    } catch (\Illuminate\Validation\ValidationException $e) {
        return response()->json([
            'error_code' => 422,
            'message'    => 'Validation failed',
            'errors'     => $e->errors(),
        ], 422);
    }
}


function updateMerchantKyc(Request $request)
{
    try {
        $validatedData = $request->validate([
            'id'                => 'required|exists:users,id',
            'scheme_id'         => 'nullable|integer',
            'credentials_id'    => 'nullable|integer',
            'payin_at_onboard'  => 'nullable|string',
            'payout_at_onboard' => 'nullable|string',
            'reject'            => 'nullable|boolean', // NEW: Reject flag
        ]);

        // Fetch merchant
        $merchant = User::findOrFail($validatedData['id']);

        // ❌ Stop if pre-KYC is not completed
        if ((int) $merchant->pre_kyc !== 1) {
            return response()->json([
                'message' => 'Pre-KYC not completed. Cannot approve/reject KYC.',
            ], 400);
        }

        // Remove id and reject from validated data so it doesn't get updated
        unset($validatedData['id'], $validatedData['reject']);

        // Update merchant fields
        $merchant->update($validatedData);

        // ✅ Handle approve or reject
        if (!empty($request->reject) && $request->reject == true) {
            $merchant->kyc = 0;          // Not approved
            $merchant->kyc_rejected = 1;   // Mark as rejected
            $merchant->save();

            return response()->json([
                'message'  => 'KYC rejected successfully',
                'merchant' => $merchant,
            ], 200);
        } else {
            $merchant->kyc = 1;          // Approve
            $merchant->kyc_rejected = 0;   // Clear rejected flag if any
            $merchant->save();

            return response()->json([
                'message'  => 'KYC approved successfully',
                'merchant' => $merchant,
            ], 200);
        }

    } catch (\Illuminate\Validation\ValidationException $e) {
        return response()->json([
            'error_code' => 422,
            'message'    => 'Validation failed',
            'errors'     => $e->errors(),
        ], 422);
    }
}




public function storeMerchant(Request $request)
{
    try {
        // ✅ Validation (combined)
        $validatedData = $request->validate([
            'name'                  => 'required|string|max:255',
            'email'                 => 'required|email|unique:users,email',
            'mobile_no'             => 'required|string|max:15|unique:users,mobile_no',
            'password'              => 'nullable|string|min:6',

            'business_mcc'          => 'nullable|string|max:50',
            'company_type'          => 'nullable|string',
            'company_pan_no'        => 'nullable|string|max:20',
            'company_gst_no'        => 'nullable|string|max:20',
            'cin_llpin'             => 'nullable|string|max:50',
            'date_of_incorporation' => 'nullable|date',

            'account_holder_name'   => 'nullable|string|max:255',
            'bank_account_no'       => 'nullable|string|max:50',
            'ifsc_code'             => 'nullable|string|max:20',

            'address'               => 'nullable|string|max:255',
            'city'                  => 'nullable|string|max:100',
            'district'              => 'nullable|string|max:100',
            'state'                 => 'nullable|string|max:100',
            'pin_code'              => 'nullable|string|max:10',
            'website_url'           => 'nullable|url|max:255',

            // Files
            'company_pan_no_doc'    => 'nullable|file',
            'company_gst_no_doc'    => 'nullable|file',
            'cancel_cheque_doc'     => 'nullable|file',
            'video_kyc'             => 'nullable|file|mimetypes:video/*',

            // Directors
            'director_info'         => 'nullable|array',
            'director_info.*.director_name'      => 'nullable:director_info|string|max:255',
            'director_info.*.director_pan_no'    => 'nullable:director_info|string|max:20',
            'director_info.*.director_aadhar_no' => 'nullable:director_info|string|max:20',
            'director_info.*.director_gender'    => 'nullable:director_info|in:male,female,other',
            'director_info.*.director_dob'       => 'nullable:director_info|date',
            'director_info.*.user_pan_doc'       => 'nullable|file',
            'director_info.*.user_addhar_doc'    => 'nullable|file',
        ]);

        // ✅ Password default = mobile_no
        $validatedData['password'] = bcrypt(
            $validatedData['password'] ?? $validatedData['mobile_no']
        );

        // ✅ Handle main file uploads
        $fileFields = [
            'company_pan_no_doc' => 'company_pan_docs',
            'company_gst_no_doc' => 'company_gst_docs',
            'cancel_cheque_doc'  => 'cancel_cheque_docs',
        ];

        foreach ($fileFields as $field => $folder) {
            if ($request->hasFile($field)) {
                $path = $request->file($field)->store($folder, 'public');
                $validatedData[$field] = $path;
            }
        }

        // ✅ Handle video KYC
        if ($request->hasFile('video_kyc')) {
            $validatedData['video_kyc'] = $request->file('video_kyc')
                ->store('video_kyc', 'public');
        }

        // ✅ Handle directors
        $directors = [];
        if (!empty($validatedData['director_info'])) {
            foreach ($validatedData['director_info'] as $index => $director) {

                if ($request->hasFile("director_info.$index.user_pan_doc")) {
                    $director['user_pan_doc'] = $request->file("director_info.$index.user_pan_doc")
                        ->store('director_pan_docs', 'public');
                }

                if ($request->hasFile("director_info.$index.user_addhar_doc")) {
                    $director['user_addhar_doc'] = $request->file("director_info.$index.user_addhar_doc")
                        ->store('director_aadhar_docs', 'public');
                }

                $directors[] = $director;
            }
        }

        $validatedData['director_info'] = $directors;

        // ✅ Create merchant
        $merchant = User::create($validatedData);

        // ✅ Mark KYC done
        $merchant->pre_kyc = 1;
        $merchant->save();

        return response()->json([
            'status'  => 'success',
            'message'  => 'Merchant created + KYC completed',
            'merchant' => $merchant
        ], 201);

    } catch (\Illuminate\Validation\ValidationException $e) {
        return response()->json([
            'error_code' => 422,
            'message'    => 'Validation failed',
            'errors'     => $e->errors()
        ], 422);
    }
}

  
  
  }
  

