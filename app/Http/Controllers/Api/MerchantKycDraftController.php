<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Models\VideoKycSession;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;

class MerchantKycDraftController extends Controller
{
    /**
     * Steps handled by this endpoint. Step 5 (final submit) is handled by
     * the existing POST /kyc-merchant endpoint, not here.
     */
    private const STEP_BUSINESS  = 1;
    private const STEP_BANK      = 2;
    private const STEP_DIRECTOR  = 3;
    private const STEP_VIDEO_KYC = 4;

    /**
     * POST /merchant/kyc/save-step
     *
     * Called on every "Next" click. Validates and persists only the fields
     * relevant to the given step, then advances current_step.
     */
    public function saveStep(Request $request): JsonResponse
    {
        $request->validate([
            'id'   => 'required|exists:users,id',
            'step' => 'required|integer|between:1,4',
        ]);
    
        $merchant = User::findOrFail($request->id);
    
        $validator = Validator::make($request->all(), [
            'step' => 'required|integer|between:1,4',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Invalid step.',
                'errors'  => $validator->errors(),
            ], 422);
        }

        $step = (int) $request->input('step');

        try {
            $result = DB::transaction(function () use ($request, $merchant, $step) {
                switch ($step) {
                    case self::STEP_BUSINESS:
                        $this->saveBusinessStep($request, $merchant);
                        break;

                    case self::STEP_BANK:
                        $this->saveBankStep($request, $merchant);
                        break;

                    case self::STEP_DIRECTOR:
                        $this->saveDirectorStep($request, $merchant);
                        break;

                    case self::STEP_VIDEO_KYC:
                        $this->saveVideoKycStep($request, $merchant);
                        break;
                }

                // Only ever move current_step forward, never backward, so
                // that re-visiting an earlier step for edits doesn't regress
                // where the resume flow drops the merchant.
                $merchant->current_step = max(
                    (int)$merchant->current_step,
                    $step + 1
                );
                $merchant->save();

                return $merchant->fresh();
            });

            return response()->json([
                'success'      => true,
                'message'      => 'Step ' . $step . ' saved.',
                'current_step' => $result->current_step,
                'data'         => $this->formatMerchantData($result),
            ]);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed.',
                'errors'  => $e->errors(),
            ], 422);
        } catch (\Throwable $e) {
            report($e);

            return response()->json([
                'success' => false,
                'message' => 'Could not save step. Please try again.',
            ], 500);
        }
    }

    /**
     * GET /merchant/kyc/details
     *
     * Returns the logged-in merchant's saved KYC draft, including
     * current_step, so the frontend can resume exactly where they left off.
     */
    public function getDetails(Request $request): JsonResponse
    {
        $request->validate([
            'id' => 'required|exists:users,id',
        ]);
    
        $merchant = User::findOrFail($request->id);

        return response()->json([
            'success' => true,
            'data'    => $this->formatMerchantData($merchant),
        ]);
    }

    /* -----------------------------------------------------------------
     | Step handlers
     * -----------------------------------------------------------------
     */

    /**
     * Step 1 — Business details (company info + address).
     */
    private function saveBusinessStep(Request $request, User $merchant): void
    {
        $validated = $request->validate([
            'company_type' => 'required|string|max:100',
            'company_gst_no'     => 'required|string|max:20',
            'company_gst_no_doc' => 'nullable|file|mimes:pdf,jpg,jpeg,png|max:5120',
            'address'            => 'required|string|max:255',
            'city'               => 'required|string|max:100',
            'district'           => 'required|string|max:100',
            'state'              => 'required|string|max:100',
            'pin_code'           => 'required|string|max:10',
        ]);

        // Doc is required only if it was never uploaded before. If it's
        // already stored from a previous save, re-saving this step without
        // a new file is fine.
        if (!$request->hasFile('company_gst_no_doc') && empty($merchant->company_gst_no_doc)) {
            throw \Illuminate\Validation\ValidationException::withMessages([
                'company_gst_no_doc' => ['The company gst no doc field is required.'],
            ]);
        }

        // Plain fields
        $merchant->fill(collect($validated)
            ->except(['company_pan_no_doc', 'company_gst_no_doc'])
            ->toArray());

        // Files: only overwrite if a new file was actually uploaded,
        // so re-saving this step without re-uploading doesn't wipe
        // a previously stored document.
        if ($request->hasFile('company_pan_no_doc')) {
            $merchant->company_pan_no_doc = $this->storeMerchantFile(
                $request->file('company_pan_no_doc'),
                $merchant->id,
                'company_pan'
            );
        }

        if ($request->hasFile('company_gst_no_doc')) {
            $merchant->company_gst_no_doc = $this->storeMerchantFile(
                $request->file('company_gst_no_doc'),
                $merchant->id,
                'company_gst'
            );
        }

        $merchant->save();
    }

    /**
     * Step 2 — Bank details.
     */
    private function saveBankStep(Request $request, User $merchant): void
    {
        $validated = $request->validate([
            'business_mcc'          => 'required|string|max:50',
            'company_pan_no'        => 'required|string|max:20',
            'company_pan_no_doc'    => 'nullable|file|mimes:pdf,jpg,jpeg,png|max:5120',
            'cin_llpin'             => 'required|string|max:30',
            'date_of_incorporation' => 'required|date',
            'website_url'           => 'nullable|url|max:255',
        
            'account_holder_name'   => 'required|string|max:150',
            'bank_account_no'       => 'required|string|max:30',
            'ifsc_code'             => 'required|string|max:11',
            'cancel_cheque_doc'     => 'nullable|file|mimes:pdf,jpg,jpeg,png|max:5120',
        ]);

        // Same "required only on first save" logic as business step.
        if (!$request->hasFile('company_pan_no_doc') && empty($merchant->company_pan_no_doc)) {
            throw \Illuminate\Validation\ValidationException::withMessages([
                'company_pan_no_doc' => ['The company pan no doc field is required.'],
            ]);
        }

        if (!$request->hasFile('cancel_cheque_doc') && empty($merchant->cancel_cheque_doc)) {
            throw \Illuminate\Validation\ValidationException::withMessages([
                'cancel_cheque_doc' => ['The cancel cheque doc field is required.'],
            ]);
        }

        // $merchant->fill(collect($validated)->except('cancel_cheque_doc')->toArray());

        // if ($request->hasFile('cancel_cheque_doc')) {
        //     $merchant->cancel_cheque_doc = $this->storeMerchantFile(
        //         $request->file('cancel_cheque_doc'),
        //         $merchant->id,
        //         'cancel_cheque'
        //     );
        // }

        // $merchant->save();
        
        
        /////////////////////////////////
        $merchant->fill(
            collect($validated)
                ->except([
                    'company_pan_no_doc',
                    'cancel_cheque_doc',
                ])
                ->toArray()
        );
        
        // Company PAN Document
        if ($request->hasFile('company_pan_no_doc')) {
            $merchant->company_pan_no_doc = $this->storeMerchantFile(
                $request->file('company_pan_no_doc'),
                $merchant->id,
                'company_pan'
            );
        }
        
        // Cancel Cheque Document
        if ($request->hasFile('cancel_cheque_doc')) {
            $merchant->cancel_cheque_doc = $this->storeMerchantFile(
                $request->file('cancel_cheque_doc'),
                $merchant->id,
                'cancel_cheque'
            );
        }
        
        $merchant->save();
    }

    /**
     * Step 3 — Director info (supports multiple directors, each with
     * their own PAN and Aadhaar documents).
     *
     * Expected payload shape (multipart/form-data):
     *   director_info[0][name]        = "John Doe"
     *   director_info[0][pan_no]      = "ABCDE1234F"
     *   director_info[0][aadhaar_no]  = "1234 5678 9012"
     *   director_info[0][pan_doc]     = <file>   (optional on resave)
     *   director_info[0][aadhaar_doc] = <file>   (optional on resave)
     *   director_info[1][...] ...
     */
    private function saveDirectorStep(Request $request, User $merchant): void
    {
        $request->validate([
            'director_info'                      => 'required|array|min:1',
            'director_info.*.director_name'      => 'required|string|max:150',
            'director_info.*.director_pan_no'    => 'required|string|max:20',
            'director_info.*.director_aadhar_no' => 'required|string|max:20',
            'director_info.*.director_gender'    => 'nullable|string|max:20',
            'director_info.*.director_dob'       => 'nullable|date',
            'director_info.*.pan_doc'            => 'nullable|file|mimes:pdf,jpg,jpeg,png|max:5120',
            'director_info.*.aadhaar_doc'        => 'nullable|file|mimes:pdf,jpg,jpeg,png|max:5120',
        ]);

        // Merge new uploads on top of whatever was already stored for each
        // director, keyed by index, so partial re-saves don't lose docs.
        $existingDirectors = $this->decodeDirectorInfo($merchant->director_info);

        $incoming = $request->input('director_info', []);
        $files    = $request->file('director_info', []);

        $directors = [];

        foreach ($incoming as $index => $directorData) {
            $existing = $existingDirectors[$index] ?? [];

            $panDocPath      = $existing['pan_doc'] ?? null;
            $aadhaarDocPath  = $existing['aadhaar_doc'] ?? null;

            if (isset($files[$index]['pan_doc']) && $files[$index]['pan_doc']) {
                $panDocPath = $this->storeMerchantFile(
                    $files[$index]['pan_doc'],
                    $merchant->id,
                    'director_pan_' . $index
                );
            }

            if (isset($files[$index]['aadhaar_doc']) && $files[$index]['aadhaar_doc']) {
                $aadhaarDocPath = $this->storeMerchantFile(
                    $files[$index]['aadhaar_doc'],
                    $merchant->id,
                    'director_aadhaar_' . $index
                );
            }

            $directors[] = [
                'director_name'      => $directorData['director_name'],
                'director_pan_no'    => $directorData['director_pan_no'],
                'director_aadhar_no' => $directorData['director_aadhar_no'],
                'director_gender'    => $directorData['director_gender'] ?? null,
                'director_dob'       => $directorData['director_dob'] ?? null,
                'pan_doc'            => $panDocPath,
                'aadhaar_doc'        => $aadhaarDocPath,
            ];
        }

        $merchant->director_info = $directors;
        $merchant->save();
    }

    /**
     * Step 4 — Video KYC. Video capture/upload itself is already handled
     * elsewhere (VideoKycSession). Here we just link the latest usable
     * session's video to the merchant's users.video_kyc column.
     */
    private function saveVideoKycStep(Request $request, User $merchant): void
    {
        $request->validate([
            'video_kyc_session_id' => 'nullable|integer|exists:video_kyc_sessions,id',
        ]);

        $sessionQuery = VideoKycSession::query()
            ->where('user_id', $merchant->id)
            ->whereIn('status', ['uploaded', 'verified']);

        if ($request->filled('video_kyc_session_id')) {
            $sessionQuery->where('id', $request->input('video_kyc_session_id'));
        }

        $session = $sessionQuery->latest('id')->first();

        if (! $session || empty($session->video_path)) {
            throw \Illuminate\Validation\ValidationException::withMessages([
                'video_kyc' => ['No uploaded/verified video KYC session was found for this merchant.'],
            ]);
        }

        $merchant->video_kyc = $session->video_path;
        $merchant->save();
    }

    /* -----------------------------------------------------------------
     | Helpers
     * -----------------------------------------------------------------
     */


    /**
     * Store an uploaded file under storage/app/public/kyc/{merchant_id}/
     * and return its relative (public disk) path for saving to the DB.
     */
    private function storeMerchantFile($file, int $merchantId, string $prefix): string
    {
        $directory = 'kyc/' . $merchantId;
        $filename  = $prefix . '_' . time() . '_' . uniqid() . '.' . $file->getClientOriginalExtension();

        return $file->storeAs($directory, $filename, 'public');
    }

    /**
     * Safely decode the director_info JSON column into an indexed array.
     */
    private function decodeDirectorInfo($raw): array
    {
        if (empty($raw)) {
            return [];
        }

        // Cast already gives us a PHP array in the normal case — use it directly.
        if (is_array($raw)) {
            return $raw;
        }

        $decoded = $raw;
        $attempts = 0;

        while (is_string($decoded) && $attempts < 3) {
            $next = json_decode($decoded, true);
            $attempts++;

            if ($next === null && json_last_error() !== JSON_ERROR_NONE) {
                break;
            }

            $decoded = $next;
        }

        return is_array($decoded) ? $decoded : [];
    }

    /**
     * Shape the merchant model into the response payload expected by the
     * React frontend's setMemberFormData() / setCurrentStep() calls.
     */
    private function formatMerchantData(User $merchant): array
    {
        return [
            'current_step'           => (int) $merchant->current_step,
            'pre_kyc'                => (int) $merchant->pre_kyc,
            'kyc'                    => $merchant->kyc,
            'kyc_rejected'           => $merchant->kyc_rejected,

            // Step 1 — Business
            'business_mcc'           => $merchant->business_mcc,
            'company_type'           => $merchant->company_type,
            'company_pan_no'         => $merchant->company_pan_no,
            'company_pan_no_doc'     => $merchant->company_pan_no_doc,
            'company_gst_no'         => $merchant->company_gst_no,
            'company_gst_no_doc'     => $merchant->company_gst_no_doc,
            'cin_llpin'              => $merchant->cin_llpin,
            'date_of_incorporation'  => $merchant->date_of_incorporation,
            'address'                => $merchant->address,
            'city'                   => $merchant->city,
            'district'               => $merchant->district,
            'state'                  => $merchant->state,
            'pin_code'               => $merchant->pin_code,
            'website_url'            => $merchant->website_url,

            // Step 2 — Bank
            'account_holder_name'    => $merchant->account_holder_name,
            'bank_account_no'        => $merchant->bank_account_no,
            'ifsc_code'              => $merchant->ifsc_code,
            'cancel_cheque_doc'      => $merchant->cancel_cheque_doc,

            // Step 3 — Director
            'director_info'          => $this->decodeDirectorInfo($merchant->director_info),

            // Step 4 — Video KYC
            'video_kyc'              => $merchant->video_kyc,
        ];
    }
}