<?php

namespace App\Http\Controllers\Api;

use Illuminate\Http\Request;
use App\Http\Controllers\Controller; 
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\DB;
use App\Models\MerchantKycDocument;
use App\Models\User;

class MerchantKycController extends Controller
{
    private $documentKeys = [
        'company_pan'       => 'Company PAN',
        'gst_certificate'   => 'GST Certificate',
        'cancelled_cheque'  => 'Cancelled Cheque',
        'video_kyc'         => 'Video KYC',
        'director_pan'      => 'Director PAN',
        'director_aadhaar'  => 'Director Aadhaar',
    ];

    // GET /merchant-kyc-documents/{user_id}
    public function listDocuments($userId)
    {
        try {
            $user = User::findOrFail($userId);

            $existing = MerchantKycDocument::where('user_id', $userId)
                ->get()
                ->keyBy('document_key');

            // Agar documents abhi tak initialize nahi hue, to on-the-fly bana do
            $documents = collect($this->documentKeys)->map(function ($name, $key) use ($existing, $userId) {
                if ($existing->has($key)) {
                    return $existing[$key];
                }
                return MerchantKycDocument::create([
                    'user_id' => $userId,
                    'document_key' => $key,
                    'document_name' => $name,
                    'status' => 0,
                ]);
            });

            return response()->json([
                'status' => 'success',
                'data' => $documents->values(),
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => $e->getMessage(),
            ], 500);
        }
    }

    // POST /document-approve
    public function documentApprove(Request $request)
    {
        DB::beginTransaction();
        try {
            $validator = Validator::make($request->all(), [
                'user_id' => 'required|exists:users,id',
                'document_key' => 'required|string',
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'status' => 'failed',
                    'message' => $validator->errors()->first(),
                ], 422);
            }

            $document = MerchantKycDocument::firstOrCreate(
                ['user_id' => $request->user_id, 'document_key' => $request->document_key],
                ['document_name' => $this->documentKeys[$request->document_key] ?? $request->document_key, 'status' => 0]
            );

            $document->status = 1;
            $document->remarks = null;
            $document->verified_at = now();
            $document->verified_by = auth()->id();
            $document->save();

            $this->updateMerchantKycStatus($request->user_id);

            DB::commit();

            return response()->json([
                'status' => 'success',
                'message' => $document->document_name . ' approved successfully.',
                'data' => $document,
            ]);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'status' => 'error',
                'message' => $e->getMessage(),
            ], 500);
        }
    }

    // POST /document-reject
    public function documentReject(Request $request)
    {
        DB::beginTransaction();
        try {
            $validator = Validator::make($request->all(), [
                'user_id' => 'required|exists:users,id',
                'document_key' => 'required|string',
                'remarks' => 'required|string|max:500',
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'status' => 'failed',
                    'message' => $validator->errors()->first(),
                ], 422);
            }

            $document = MerchantKycDocument::firstOrCreate(
                ['user_id' => $request->user_id, 'document_key' => $request->document_key],
                ['document_name' => $this->documentKeys[$request->document_key] ?? $request->document_key, 'status' => 0]
            );

            $document->status = 2;
            $document->remarks = $request->remarks;
            $document->verified_at = now();
            $document->verified_by = auth()->id();
            $document->save();

            $this->updateMerchantKycStatus($request->user_id);

            DB::commit();

            return response()->json([
                'status' => 'success',
                'message' => $document->document_name . ' rejected successfully.',
                'data' => $document,
            ]);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'status' => 'error',
                'message' => $e->getMessage(),
            ], 500);
        }
    }

    // Overall merchant KYC status ko documents ke basis pe update karta hai
    private function updateMerchantKycStatus($userId)
    {
        $user = User::find($userId);
        if (!$user) return;

        $documents = MerchantKycDocument::where('user_id', $userId)->get();

        // Koi bhi document rejected hai
        if ($documents->where('status', 2)->count() > 0) {
            $user->kyc = 0;
            $user->kyc_rejected = 1;
            $user->save();
            return;
        }

        // Koi document abhi pending hai
        if ($documents->where('status', 0)->count() > 0) {
            $user->kyc = 0;
            $user->kyc_rejected = 0;
            $user->save();
            return;
        }

        // Sab approved
        $user->kyc = 1;
        $user->kyc_rejected = 0;
        $user->save();
    }
    
    public function reuploadDocument(Request $request)
    {
        DB::beginTransaction();
    
        try {
    
            $validator = Validator::make($request->all(), [
                'user_id'      => 'required|exists:users,id',
                'document_key' => 'required|in:company_pan,gst_certificate,cancelled_cheque',
                'document'     => 'required|file|mimes:jpg,jpeg,png,pdf|max:5120',
            ]);
    
            if ($validator->fails()) {
                return response()->json([
                    'status' => 'failed',
                    'message' => $validator->errors()->first(),
                ], 422);
            }
    
            $user = User::findOrFail($request->user_id);
    
            $map = [
                'company_pan' => [
                    'folder' => 'company_pan_docs',
                    'column' => 'company_pan_no_doc'
                ],
                'gst_certificate' => [
                    'folder' => 'company_gst_docs',
                    'column' => 'company_gst_no_doc'
                ],
                'cancelled_cheque' => [
                    'folder' => 'cancel_cheque_docs',
                    'column' => 'cancel_cheque_doc'
                ],
            ];
    
            $config = $map[$request->document_key];
    
            $path = $request->file('document')->store($config['folder'], 'public');
    
            $user->{$config['column']} = $path;
            $user->save();
    
            $doc = MerchantKycDocument::where('user_id', $user->id)
                ->where('document_key', $request->document_key)
                ->first();
    
            $doc->status = 0;
            $doc->remarks = null;
            $doc->verified_at = null;
            $doc->verified_by = null;
            $doc->save();
    
            $this->updateMerchantKycStatus($user->id);
    
            DB::commit();
    
            return response()->json([
                'status' => 'success',
                'message' => 'Document uploaded successfully.',
                'data' => $doc
            ]);
    
        } catch (\Exception $e) {
    
            DB::rollBack();
    
            return response()->json([
                'status'=>'error',
                'message'=>$e->getMessage()
            ],500);
        }
    }
    
    // public function reuploadVideoKyc(Request $request)
    // {
    //     DB::beginTransaction();
    
    //     try {
    
    //         $validator = Validator::make($request->all(),[
    //             'user_id'=>'required|exists:users,id',
    //             'video_session_id'=>'required|string'
    //         ]);
    
    //         if($validator->fails()){
    //             return response()->json([
    //                 'status'=>'failed',
    //                 'message'=>$validator->errors()->first()
    //             ],422);
    //         }
    
    //         $user = User::findOrFail($request->user_id);
    
    //         $user->video_kyc = $request->video_session_id;
    //         $user->save();
    
    //         $doc = MerchantKycDocument::where('user_id',$user->id)
    //             ->where('document_key','video_kyc')
    //             ->first();
    
    //         $doc->status=0;
    //         $doc->remarks=null;
    //         $doc->verified_at=null;
    //         $doc->verified_by=null;
    //         $doc->save();
    
    //         $this->updateMerchantKycStatus($user->id);
    
    //         DB::commit();
    
    //         return response()->json([
    //             'status'=>'success',
    //             'message'=>'Video KYC submitted successfully.'
    //         ]);
    
    //     }catch(\Exception $e){
    
    //         DB::rollBack();
    
    //         return response()->json([
    //             'status'=>'error',
    //             'message'=>$e->getMessage()
    //         ],500);
    
    //     }
    // }
    
    public function reuploadVideoKyc(Request $request)
    {
        DB::beginTransaction();
    
        try {
    
            $validator = Validator::make($request->all(),[
                'user_id'=>'required|exists:users,id',
                'video_session_id'=>'required|string'
            ]);
    
            if($validator->fails()){
                return response()->json([
                    'status'=>'failed',
                    'message'=>$validator->errors()->first()
                ],422);
            }
    
            $user = User::findOrFail($request->user_id);
    
            // ---- FIX: session folder ke andar actual file dhoondo ----
            $folder = 'vkyc/' . $request->video_session_id;
    
            $files = \Storage::disk('public')->files($folder);
    
            if (empty($files)) {
                DB::rollBack();
                return response()->json([
                    'status' => 'failed',
                    'message' => 'Video KYC file not found for this session. Please complete the video KYC first.',
                ], 422);
            }
    
            // Agar ek se zyada file ho, sabse latest (last modified) uthao
            $latestFile = collect($files)->sortByDesc(function ($file) {
                return \Storage::disk('public')->lastModified($file);
            })->first();
    
            $user->video_kyc = $latestFile; // e.g. vkyc/{session_id}/vkyc_20260812_131755_xxxx.webm
            // ---------------------------------------------------------
    
            $user->save();
    
            $doc = MerchantKycDocument::where('user_id',$user->id)
                ->where('document_key','video_kyc')
                ->first();
    
            $doc->status=0;
            $doc->remarks=null;
            $doc->verified_at=null;
            $doc->verified_by=null;
            $doc->save();
    
            $this->updateMerchantKycStatus($user->id);
    
            DB::commit();
    
            return response()->json([
                'status'=>'success',
                'message'=>'Video KYC submitted successfully.'
            ]);
    
        }catch(\Exception $e){
    
            DB::rollBack();
    
            return response()->json([
                'status'=>'error',
                'message'=>$e->getMessage()
            ],500);
    
        }
    }
    
    public function reuploadDigilocker(Request $request)
    {
        DB::beginTransaction();
    
        try {
    
            $validator = Validator::make($request->all(),[
                'user_id'=>'required|exists:users,id'
            ]);
    
            if($validator->fails()){
                return response()->json([
                    'status'=>'failed',
                    'message'=>$validator->errors()->first()
                ],422);
            }
    
            $user = User::findOrFail($request->user_id);
    
            MerchantKycDocument::where('user_id',$user->id)
                ->whereIn('document_key',['director_pan','director_aadhaar'])
                ->update([
                    'status'=>0,
                    'remarks'=>null,
                    'verified_at'=>null,
                    'verified_by'=>null
                ]);
    
            $this->updateMerchantKycStatus($user->id);
    
            DB::commit();
    
            return response()->json([
                'status'=>'success',
                'message'=>'DigiLocker verification restarted.'
            ]);
    
        }catch(\Exception $e){
    
            DB::rollBack();
    
            return response()->json([
                'status'=>'error',
                'message'=>$e->getMessage()
            ],500);
    
        }
    }
}