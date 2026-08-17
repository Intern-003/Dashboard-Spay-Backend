<?php

namespace App\Http\Controllers\Api;

use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use App\Models\Report;
use App\Http\Controllers\Controller;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Response;
use Illuminate\Support\LazyCollection;
use Illuminate\Support\Facades\Http;
use App\Models\PayinOnboardedBank;


class ReportController extends Controller
{
    public function createReport(Request $request)
    {
        try {
            // Validate the request
            $validatedData = $request->validate([
                'user_id'           => 'required|exists:users,id',
                'mobile'            => 'nullable|string|max:10',
                'amount'            => 'nullable|numeric',
                'charge'            => 'nullable|numeric',
                'profit'            => 'nullable|numeric',
                'gst'               => 'nullable|numeric',
                'tds'               => 'nullable|numeric',
                'apitxnid'          => 'nullable|string|max:255',
                'txnid'             => 'nullable|string|max:255',
                'payid'             => 'nullable|string|max:255',
                'refno'             => 'nullable|string|max:255',
                'description'       => 'nullable|string',
                'remark'            => 'nullable|string',
                'option1'           => 'nullable|string|max:255',
                'option2'           => 'nullable|string|max:255',
                'option3'           => 'nullable|string|max:255',
                'option4'           => 'nullable|string|max:255',
                'status'            => 'nullable|in:pending,success,failed,reversed,refunded,complete,initiated',
                'payment_platform'  => 'nullable|in:api,portal,app',
                'payout_amount'     => 'nullable|numeric',
                'payin_amount'      => 'nullable|numeric',
                'transaction_type'    => 'nullable|in:credit,debit,none',
                'product'           => 'nullable|in:fund_loadwallet,UPI,payout,upicollect,fund_transfer',
                'mytxnid'           => 'nullable|string|max:255',
                'aepstype'          => 'nullable|string|max:255',
                'payee_vpa'         => 'nullable|string|max:255',
                'payer_vpa'         => 'nullable|string|max:255',
                'payer_mobile'      => 'nullable|string|max:20',
                'payer_acc_no'      => 'nullable|string|max:50',
                'payer_ifsc'        => 'nullable|string|max:20',
                'commission_inc_gst'=> 'nullable|numeric',
                'bank_other_charges'=> 'nullable|numeric',
            ], [
                // Custom messages
                'user_id.required'          => 'User ID is required.',
                'user_id.exists'            => 'User ID does not exist in the users table.',
                'amount.numeric'            => 'Amount must be a valid number.',
                'charge.numeric'            => 'Charge must be a valid number.',
                'profit.numeric'            => 'Profit must be a valid number.',
                'gst.numeric'               => 'GST must be a valid number.',
                'tds.numeric'               => 'TDS must be a valid number.',
                'payout_amount.numeric'     => 'Payout amount must be a valid number.',
                'payin_amount.numeric'      => 'Payin amount must be a valid number.',
                'commission_inc_gst.numeric'=> 'Commission including GST must be a valid number.',
                'bank_other_charges.numeric'=> 'Bank other charges must be a valid number.',
                'status.in'                 => 'Invalid status value.',
                'payment_platform.in'       => 'Invalid payment platform.',
                'transaction_type.in'         => 'Invalid transaction type.',
                'product.in'                => 'Invalid product type.',
                'mobile.max'                => 'Mobile number can be at most 10 characters.',
                'payer_ifsc.max'            => 'IFSC code cannot exceed 20 characters.',
                'payer_acc_no.max'          => 'Account number cannot exceed 50 characters.',
                'option1.max'               => 'Option 1 field too long.',
                'option2.max'               => 'Option 2 field too long.',
                'option3.max'               => 'Option 3 field too long.',
                'option4.max'               => 'Option 4 field too long.',
            ]);
    
            // Create the report
            $report = Report::create($validatedData);
    
            // Return success response
            return response()->json([
                'message' => 'Report created successfully',
                'report'  => $report
            ], 201);
    
        } catch (ValidationException $e) {
            return response()->json([
                'error_code' => 422,
                'message'    => 'Validation failed',
                'errors'     => $e->errors()
            ], 422);
        }
    }

    //working
    public function ReportRecordsList(Request $request)
    {
        try {
            $user = Auth::user();
    
            $baseQuery = Report::query();
    
            if ($user->role_type !== 'admin') {
                $baseQuery->where('user_id', $user->id);
            }
    
            $query = clone $baseQuery;
    
            // 🔍 Filters
            if ($request->filled('status') && $request->status !== 'all') {
                $query->where('status', $request->status);
            }
            
            if ($request->filled('chargeback_status') && $request->chargeback_status !== 'all') {
                $query->where('chargeback_status', $request->chargeback_status);
            }
    
            if ($request->filled('user_id') && $request->user_id !== 'all') {
                $query->where('user_id', $request->user_id);
            }
    
            if ($request->filled('searchdata')) {
                $search = $request->searchdata;
    
                $query->where(function ($q) use ($search) {
                    $q->where('id', $search)
                      ->orWhere('txnid', $search)
                      ->orWhere('apitxnid', $search)
                      ->orWhere('mytxnid', $search)
                      ->orWhere('option4', $search)
                      ->orWhere('glide_uiwidget_sessionid', $search);
                });
            }
    
            if ($request->filled('product')) {
                $product = $request->product;
    
                is_array($product)
                    ? $query->whereIn('product', $product)
                    : $query->where('product', $product);
            }
    
            if ($request->filled('from_date')) {
                $query->where('created_at', '>=', Carbon::parse($request->from_date)->startOfDay());
            }
    
            if ($request->filled('to_date')) {
                $query->where('created_at', '<=', Carbon::parse($request->to_date)->endOfDay());
            }
    
            // ✅ SUCCESS AMOUNT
            $successAmount = (clone $query)
                ->where('status', 'success')
                ->sum('amount');
                
            // =========================================================
            // 🟡 EXPORT MODE (NO PAGINATION, JSON ONLY)
            // =========================================================
            // if ($request->has('exportdata') && $request->exportdata == 1) {
    
            //     // ⚠️ Safety limit (change as needed)
            //     $limit = 40000;
    
            //     $reports = $query->with(['user:id,name,email'])
            //         ->orderByDesc('id')
            //         ->limit($limit)
            //         ->get();
    
            //     return response()->json([
            //         'status' => true,
            //         'message' => 'Export data fetched successfully',
            //         'data' => $reports,
            //         // 'success_amount' => $successAmount,
            //         'export' => true,
            //         'limit_applied' => $limit
            //     ]);
            // }
    

            if ($request->has('exportdata') && $request->exportdata == 1) {

    set_time_limit(0);
    ini_set('memory_limit', '-1');

    return response()->stream(function () use ($query) {

        echo '{"status":true,"message":"Export data fetched successfully","data":[';

        $first = true;

        // foreach ($query->with(['user:id,name,email'])
        //             ->orderByDesc('id')
        //             ->cursor() as $row) {

        //     if (!$first) {
        //         echo ',';
        //     }

        //     echo json_encode($row);
        //     $first = false;
        // }
        
        foreach ($query->orderByDesc('id')->cursor() as $row) {

    $user = \App\Models\User::select('id','name','email')
                ->find($row->user_id);

    $row->setRelation('user', $user);

    if (!$first) echo ',';

    echo json_encode($row);
    $first = false;
}

        echo '],"export":true}';

    }, 200, [
        'Content-Type' => 'application/json',
        'Cache-Control' => 'no-cache',
    ]);
}    
    
    
            // 📄 Pagination
            $perPage = $request->input('per_page', 200); // default 10
            $page = $request->input('page', 1);
    
            $reports = $query->with(['user:id,name,email'])
                ->orderByDesc('id')
                ->paginate($perPage, ['*'], 'page', $page);
    
            return response()->json([
                'status' => true,
                'message' => 'Report records fetched successfully',
                'data' => $reports->items(),
                'success_amount' => $successAmount,
    
                // 👇 pagination meta
                'pagination' => [
                    'current_page' => $reports->currentPage(),
                    'last_page' => $reports->lastPage(),
                    'per_page' => $reports->perPage(),
                    'total' => $reports->total(),
                ]
            ]);
    
        } catch (\Exception $e) {
    
            \Log::error("Report fetch error: " . $e->getMessage());
    
            return response()->json([
                'status' => false,
                'message' => 'Something went wrong'
            ], 500);
        }
    }
    

// public function ReportRecordsList(Request $request)
// {
//     try {
//         $user = Auth::user();

//         // =========================================================
//         // 🧱 BASE QUERY (FAST JOIN)
//         // =========================================================
//         $query = Report::query()
//             ->select([
//                 'reports.*',
//                 'users.id as user_id_ref',
//                 'users.name as user_name',
//                 'users.email as user_email'
//             ])
//             ->leftJoin('users', 'users.id', '=', 'reports.user_id')
//             ->where('reports.id', '>', 0);

//         // =========================================================
//         // 🔐 ROLE FILTER
//         // =========================================================
//         if ($user->role_type !== 'admin') {
//             $query->where('reports.user_id', $user->id);
//         }

//         // =========================================================
//         // 🔍 FILTERS
//         // =========================================================
//         if ($request->filled('status') && $request->status !== 'all') {
//             $query->where('reports.status', $request->status);
//         }

//         if ($request->filled('chargeback_status') && $request->chargeback_status !== 'all') {
//             $query->where('reports.chargeback_status', $request->chargeback_status);
//         }

//         if ($request->filled('user_id') && $request->user_id !== 'all') {
//             $query->where('reports.user_id', $request->user_id);
//         }

//         // ⚡ FAST + MULTI FIELD SEARCH
//         if ($request->filled('searchdata')) {
//             $search = trim($request->searchdata);
        
//             $query->where(function ($q) use ($search) {
//                 if (is_numeric($search)) {
//                     $q->where('reports.id', $search);
//                 }
        
//                 $q->orWhere('reports.txnid', $search)
//                   ->orWhere('reports.apitxnid', $search)
//                   ->orWhere('reports.mytxnid', $search)
//                   ->orWhere('reports.option4', $search)
//                   ->orWhere('reports.glide_uiwidget_sessionid', $search);
//             });
//         }

//         // product filter
//         if ($request->filled('product')) {
//             $product = $request->product;

//             is_array($product)
//                 ? $query->whereIn('reports.product', $product)
//                 : $query->where('reports.product', $product);
//         }

//         // date filters
//         if ($request->filled('from_date')) {
//             $query->where('reports.created_at', '>=', Carbon::parse($request->from_date)->startOfDay());
//         }

//         if ($request->filled('to_date')) {
//             $query->where('reports.created_at', '<=', Carbon::parse($request->to_date)->endOfDay());
//         }

//         // =========================================================
//         // 💰 SUCCESS AMOUNT
//         // =========================================================
//         $successAmount = (clone $query)
//             ->where('reports.status', 'success')
//             ->sum('reports.amount');

//         // =========================================================
//         // 🟡 EXPORT MODE
//         // =========================================================
//         if ($request->has('exportdata') && $request->exportdata == 1) {

//             $limit = 20000;

//             $reports = $query
//                 ->orderByDesc('reports.id')
//                 ->limit($limit)
//                 ->get()
//                 ->map(function ($item) {
//                     return $this->formatReport($item);
//                 });

//             return response()->json([
//                 'status' => true,
//                 'message' => 'Export data fetched successfully',
//                 'data' => $reports,
//                 'export' => true,
//                 'limit_applied' => $limit
//             ]);
//         }

//         // =========================================================
//         // 🚀 CURSOR PAGINATION
//         // =========================================================
//         // $perPage = min($request->input('per_page', 100), 200);

//         // $reports = $query
//         //     ->orderByDesc('reports.id')
//         //     ->cursorPaginate($perPage);

//         // $formatted = collect($reports->items())->map(function ($item) {
//         //     return $this->formatReport($item);
//         // });

//         // return response()->json([
//         //     'status' => true,
//         //     'message' => 'Report records fetched successfully',
//         //     'data' => $formatted,
//         //     'success_amount' => $successAmount,
//         //     'pagination' => [
//         //         'next_cursor' => optional($reports->nextCursor())->encode(),
//         //         'prev_cursor' => optional($reports->previousCursor())->encode(),
//         //         'per_page' => $reports->perPage(),
//         //     ]
//         // ]);

//         $perPage = $request->input('per_page', 200); // default 10
//             $page = $request->input('page', 1);
    
//             $reports = $query->with(['user:id,name,email'])
//                 ->orderByDesc('id')
//                 ->paginate($perPage, ['*'], 'page', $page);
    
//             return response()->json([
//                 'status' => true,
//                 'message' => 'Report records fetched successfully',
//                 'data' => $reports->items(),
//                 'success_amount' => $successAmount,
    
//                 // 👇 pagination meta
//                 'pagination' => [
//                     'current_page' => $reports->currentPage(),
//                     'last_page' => $reports->lastPage(),
//                     'per_page' => $reports->perPage(),
//                     'total' => $reports->total(),
//                 ]
//             ]);

//     } catch (\Exception $e) {

//         \Log::error("Report fetch error: " . $e->getMessage());

//         return response()->json([
//             'status' => false,
//             'message' => $e->getMessage(),
//         ], 500);
//     }
// }

private function formatReport($item)
    {
        return [
            'id' => $item->id,
            'user_id' => $item->user_id,
            'mobile' => $item->mobile,
            'amount' => $item->amount,
            'charge' => $item->charge,
            'profit' => $item->profit,
            'gst' => $item->gst,
            'tds' => $item->tds,
            'apitxnid' => $item->apitxnid,
            'glide_uiwidget_sessionid' => $item->glide_uiwidget_sessionid,
            'txnid' => $item->txnid,
            'payid' => $item->payid,
            'refno' => $item->refno,
            'description' => $item->description,
            'remark' => $item->remark,
            'option1' => $item->option1,
            'option2' => $item->option2,
            'option3' => $item->option3,
            'option4' => $item->option4,
            'status' => $item->status,
            'payment_platform' => $item->payment_platform,
            'payout_amount' => $item->payout_amount,
            'payout_opening_balance' => $item->payout_opening_balance,
            'payout_closing_balance' => $item->payout_closing_balance,
            'payout_mode' => $item->payout_mode,
            'payin_opening' => $item->payin_opening,
            'payin_closing' => $item->payin_closing,
            'payin_amount' => $item->payin_amount,
            'transaction_type' => $item->transaction_type,
            'product' => $item->product,
            'mytxnid' => $item->mytxnid,
            'aepstype' => $item->aepstype,
            'payee_vpa' => $item->payee_vpa,
            'payer_vpa' => $item->payer_vpa,
            'payer_mobile' => $item->payer_mobile,
            'payer_acc_no' => $item->payer_acc_no,
            'payer_ifsc' => $item->payer_ifsc,
            'commission_inc_gst' => $item->commission_inc_gst,
            'bank_other_charges' => $item->bank_other_charges,
            'payin_rolling_amount' => $item->payin_rolling_amount,
            'payer_name' => $item->payer_name,
            'payer_email' => $item->payer_email,
            'created_at' => \Carbon\Carbon::parse($item->created_at)->format('d M Y, h:i A'),
            'updated_at' => \Carbon\Carbon::parse($item->updated_at)->format('d M Y, h:i A'),
            'airpay_credential' => $item->airpay_credential,
            'chargeback_status' => $item->chargeback_status,

            'user' => [
                'id' => $item->user_id_ref,
                'name' => $item->user_name,
                'email' => $item->user_email,
            ],
        ];
    }

//single txn
    public function Txnreport(Request $request)
    {
        try {
            $user = Auth::user();
    
            $query = Report::query();
    
            // Role-based restriction
            if ($user->role_type !== 'admin') {
                $query->where('user_id', $user->id);
            }
    
            // 🔍 Search by mytxnid
            if ($request->filled('id')) {
                $query->where('mytxnid', $request->id)
                      ->where('product', 'upi');
            }
    
            // ✅ Get single record
            $report = $query->with(['user:id,name,email'])
                ->orderByDesc('id')
                ->first();
    
            return response()->json([
                'status' => true,
                'message' => $report ? 'Transaction found' : 'Transaction not found',
                'data' => $report,
            ]);
    
        } catch (\Exception $e) {
    
            \Log::error("Report fetch error: " . $e->getMessage());
    
            return response()->json([
                'status' => false,
                'message' => 'Something went wrong'
            ], 500);
        }
    }

   
    public function CollectionRecord(Request $request)
    {
        $user = Auth::user();
        if (!$user) {
            return response()->json(['message' => 'User not authenticated'], 401);
        }
    
        $todayStart = now()->startOfDay();
        $todayEnd   = now()->endOfDay();
    
        $baseQuery = Report::query();
        if ($user->role_type !== 'admin') {
            $baseQuery->where('user_id', $user->id);
        }
    
        $total_payin_amount = (clone $baseQuery)
            ->where('status', 'success')
            ->where('product', 'UPI')
            ->sum('amount');
    
        $total_payout_amount = (clone $baseQuery)
            ->where('status', 'success')
            ->where('product', 'payout')
            ->sum('amount');
    
        $today_payin = (clone $baseQuery)
            ->where('status', 'success')
            ->where('product', 'UPI')
            ->whereBetween('created_at', [$todayStart, $todayEnd])
            ->sum('amount');
    
        $today_payout = (clone $baseQuery)
            ->where('status', 'success')
            ->where('product', 'payout')
            ->whereBetween('created_at', [$todayStart, $todayEnd])
            ->sum('amount');
    
        return response()->json([
            'status' => true,
            'role_type' => $user->role_type,
            'total_payin_amount'  => number_format($total_payin_amount, 2, '.', ''),
            'total_payout_amount' => number_format($total_payout_amount, 2, '.', ''),
            'today_payin'         => number_format($today_payin, 2, '.', ''),
            'today_payout'        => number_format($today_payout, 2, '.', ''),
            'payin_wallet'        => $user->payin_wallet ?? 0,
            'payout_wallet'       => $user->payout_wallet ?? 0,
        ], 200);
    }
    
    
    public function CollectionSummary(Request $request) 
    {
        $user = Auth::user();
        $todayStart = now()->startOfDay();
        $todayEnd   = now()->endOfDay();
    
        $baseQuery = Report::query();
        if($user->role_type!=='admin') $baseQuery->where('user_id', $user->id);
    
        $today_payin  = (clone $baseQuery)->where('status','success')->where('product','UPI')->whereBetween('created_at',[$todayStart,$todayEnd])->sum('amount');
        $today_payout = (clone $baseQuery)->where('status','success')->where('product','payout')->whereBetween('created_at',[$todayStart,$todayEnd])->sum('amount');
        $total_payin  = (clone $baseQuery)->where('status','success')->where('product','UPI')->sum('amount');
        $total_payout = (clone $baseQuery)->where('status','success')->where('product','payout')->sum('amount');
        
            // NEW VARIABLE FOR ADMIN → SPECIFIC USER TODAY PAYIN
     $specific_user_today_payin = [];
    
    if($user->role_type === 'admin' && $request->filled('user_ids')) {
    
        $ids = explode(',', $request->user_ids);
    
        $specific_user_today_payin = Report::whereIn('user_id', $ids)
            ->where('status','success')
            ->where('product','UPI')
            ->whereBetween('created_at',[$todayStart,$todayEnd])
            ->selectRaw('user_id, SUM(amount) as today_payin')
            ->groupBy('user_id')
            ->pluck('today_payin','user_id');
    }
    
        return response()->json([
            'today_payin' => $today_payin,
            'today_payout' => $today_payout,
            'total_payin' => $total_payin,
            'total_payout' => $total_payout,
            'specific_user_today_payin' => $specific_user_today_payin
    
        ]);
    }



    public function CollectionStatusCounts(Request $request) {
        $user = Auth::user();
        $baseQuery = Report::query();
        if($user->role_type!=='admin') $baseQuery->where('user_id', $user->id);
    
        $statusCounts = $baseQuery
            ->select('product','status',DB::raw('COUNT(*) as total'))
            ->groupBy('product','status')
            ->get()
            ->groupBy('product')
            ->map(fn($group) => $group->pluck('total','status'));
    
        return response()->json($statusCounts);
    }
    
    public function CollectionMonthwise(Request $request) {
        $user = Auth::user();
        $baseQuery = Report::query();
        if($user->role_type!=='admin') $baseQuery->where('user_id', $user->id);
    
        $monthWise = $baseQuery
            ->select(
                DB::raw("MAX(DATE_FORMAT(created_at, '%M %Y')) as month_name"),
                DB::raw("SUM(CASE WHEN product='UPI' AND status='success' THEN 1 ELSE 0 END) as payin_count"),
                DB::raw("SUM(CASE WHEN product='UPI' AND status='success' THEN amount ELSE 0 END) as payin_amount"),
                DB::raw("SUM(CASE WHEN product='PAYOUT' AND status='success' THEN 1 ELSE 0 END) as payout_count"),
                DB::raw("SUM(CASE WHEN product='PAYOUT' AND status='success' THEN amount ELSE 0 END) as payout_amount")
            )
            ->groupBy(DB::raw("DATE_FORMAT(created_at,'%Y-%m')"))
            ->orderBy(DB::raw("DATE_FORMAT(created_at,'%Y-%m')"))
            ->get();
    
        return response()->json($monthWise);
    }
/////////////////---------------E2Pay----------------//////////////////
    // public function CashfreeBalance(Request $request) {
    //     $user = Auth::user();
    //     // if($user->role_type!=='admin') return response()->json(null);
    //         // USER → return payout wallet
    //     if ($user->role_type !== 'admin') {
    //         return response()->json([
    //             'payout_balance' => $user->payout_wallet ?? 0
    //         ]);
    //     }
    
    //     // $response = Http::timeout(5)->get('https://soulfuloverseas.com/Cashfree/CFBalance/');
    //       $curl = curl_init();
    
    //         curl_setopt_array($curl, array(
    //           CURLOPT_URL => 'https://marketingllp.in/Api/Balance',
    //           CURLOPT_RETURNTRANSFER => true,
    //           CURLOPT_ENCODING => '',
    //           CURLOPT_MAXREDIRS => 10,
    //           CURLOPT_TIMEOUT => 0,
    //           CURLOPT_FOLLOWLOCATION => true,
    //           CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
    //           CURLOPT_CUSTOMREQUEST => 'POST',
    //           CURLOPT_POSTFIELDS =>'{
    //             "MerchantId" : "MT12996511"
    //         }',
    //           CURLOPT_HTTPHEADER => array(
    //             'Token: k9njUwyaPf4RXyNPmaQVF6SWPIDwz5nO',
    //             'Content-Type: application/json'
    //           ),
    //         ));
            
    //         $response = curl_exec($curl);
    //             curl_close($curl);
                
    //           $apiResponse2 = json_decode($response, true);
    //              $payout_balance = $apiResponse2['data']['balance'];
    //             // dump($payout_balance);
    //     // $balance = $response->json($payout_balance);
    
    //     return response()->json(['payout_balance'=>$payout_balance]);
    // }
    
////////////////////-----------------Bridg-Money---------------/////////////////
    public function CashfreeBalance(Request $request)
    {
        $user = Auth::user();
    
        if ($user->role_type !== 'admin') {
            return response()->json([
                'payout_balance' => $user->payout_wallet ?? 0
            ]);
        }
    
        $baseUrl = "https://api.bridg.money";
        $path = "/v1/wallet";
    
        $apiKey = "pub_live_QSbaVvro61hKfWDECkw-rg";
        $apiSecret = "sk_live_Ye1uuvNIt0E2PvuJL5TVGTSEbdwz2IP42lClUPaLJs0";
    
        $timestamp = (string) round(microtime(true) * 1000);
    
        $canonical = "GET|{$path}|{$timestamp}|";
    
        $signature = hash_hmac(
            'sha256',
            $canonical,
            $apiSecret
        );
    
        $curl = curl_init();
    
        curl_setopt_array($curl, [
            CURLOPT_URL => $baseUrl . $path,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_CUSTOMREQUEST => 'GET',
            CURLOPT_HTTPHEADER => [
                "Accept: application/json",
                "x-api-key: {$apiKey}",
                "x-timestamp: {$timestamp}",
                "x-signature: {$signature}"
            ],
            CURLOPT_TIMEOUT => 30,
        ]);
    
        $response = curl_exec($curl);
    
        if (curl_errno($curl)) {
            curl_close($curl);
    
            return response()->json([
                'status' => false,
                'message' => curl_error($curl)
            ], 500);
        }
    
        curl_close($curl);
    
        $data = json_decode($response, true);
    
        $availablePaise = (float)($data['data']['availablePaise'] ?? 0);
        $spendablePaise = (float)($data['data']['spendablePaise'] ?? 0);
    
        return response()->json([
            'payout_balance' => round($availablePaise / 100, 2), // ₹8284.87
            'spendable_balance' => round($spendablePaise / 100, 2), // usable payout balance
            'currency' => 'INR'
        ]);
    }
    
    


// new MerchantCollection without CRYPTO
    public function MerchantCollection(Request $request)
    {
        try {
            $today = now()->setTimezone('Asia/Kolkata')->toDateString();
    
            $user = Auth::user();
            if (!$user) {
                return response()->json(['message' => 'User not authenticated'], 401);
            }
    
            $todayStart = now()->startOfDay();
            $todayEnd   = now()->endOfDay();
    
            // Determine merchant
            if ($user->role_type === 'admin') {
                $merchantId = $request->merchant_id;
                if (!$merchantId) {
                    return response()->json([
                        'status' => false,
                        'message' => 'merchant_id is required for admin'
                    ], 400);
                }
            } else {
                $merchantId = $user->id;
            }
    
            // Fetch merchant
            $merchant = User::find($merchantId);
            if (!$merchant) {
                return response()->json([
                    'status' => false,
                    'message' => 'Merchant not found'
                ], 404);
            }
    
            // Base query
            $baseQuery = Report::query()->where('user_id', $merchantId);
    
            // ---------------- Totals ----------------
            $total_payin_amount  = (clone $baseQuery)->where('status', 'success')->where('product', 'UPI')->sum('amount');
            $total_payout_amount = (clone $baseQuery)->where('status', 'success')->where('product', 'payout')->sum('amount');
    
            $today_payin  = (clone $baseQuery)->where('status', 'success')->where('product', 'UPI')->whereBetween('created_at', [$todayStart, $todayEnd])->sum('amount');
            $today_payout = (clone $baseQuery)->where('status', 'success')->where('product', 'payout')->whereBetween('created_at', [$todayStart, $todayEnd])->sum('amount');
    
            // ---------------- Payin Summary ----------------
            $payinSums = (clone $baseQuery)
                ->where('status', 'success')
                ->where('product', 'UPI')
                ->where('description', 'Payment initiated')
                ->whereBetween('created_at', [$todayStart, $todayEnd])
                ->selectRaw('SUM(payin_rolling_amount) as rolling, SUM(payin_amount) as payin, SUM(profit) as profit')
                ->first();
    
            $PayinRollingAmount_current = round($payinSums->rolling ?? 0, 2);
            $PayingAmount_current       = round($payinSums->payin ?? 0, 2);
            $PayinProfitAmount_current  = round($payinSums->profit ?? 0, 2);
    
            // ---------------- Status Counts ----------------
            $payinTransactionStatusCounts = (clone $baseQuery)
                ->where('product', 'UPI')
                ->select('status', DB::raw('COUNT(*) as total'))
                ->groupBy('status')
                ->pluck('total', 'status');
    
            $payoutTransactionStatusCounts = (clone $baseQuery)
                ->where('product', 'payout')
                ->select('status', DB::raw('COUNT(*) as total'))
                ->groupBy('status')
                ->pluck('total', 'status');
    
            $todayPayinStatusCounts = (clone $baseQuery)
                ->where('product', 'UPI')
                ->whereDate('created_at', $today)
                ->select('status', DB::raw('COUNT(*) as total'))
                ->groupBy('status')
                ->pluck('total', 'status');
    
            $todayPayoutStatusCounts = (clone $baseQuery)
                ->where('product', 'payout')
                ->whereDate('created_at', $today)
                ->select('status', DB::raw('COUNT(*) as total'))
                ->groupBy('status')
                ->pluck('total', 'status');
    
                $todayPayingAmount = (clone $baseQuery)
                ->where('status', 'success')
                ->where('product', 'UPI')
                ->whereDate('created_at', Carbon::today())
                ->selectRaw('SUM(amount - profit) as total')
                ->value('total');

                    
   // Fetch total charges grouped by user_id and product
        $total_profit  = (clone $baseQuery)->where('status', 'success')->where('product', 'payout')->sum('charge');
                    
                    //todays payout charges 
                 $todayPayoutgAmount = (clone $baseQuery)
                    ->where('status', 'success')
                    ->where('product', 'payout')
                    ->whereDate('created_at', Carbon::today())
                    ->sum('charge');            
    
            $transactionStatusCounts = (clone $baseQuery)
                ->select('status', DB::raw('COUNT(*) as total'))
                ->groupBy('status')
                ->pluck('total', 'status');
    
            // ---------------- Month-wise ----------------
            $monthWiseStatusCounts = (clone $baseQuery)
                ->select(
                    DB::raw("MAX(DATE_FORMAT(created_at, '%M %Y')) as month_name"),
                    DB::raw('COUNT(*) as total')
                )
                ->groupBy(DB::raw("DATE_FORMAT(created_at, '%Y-%m')"))
                ->orderBy(DB::raw("DATE_FORMAT(created_at, '%Y-%m')"), 'asc')
                ->get();
    
            // ---------------- Refund ----------------
            // $refund_amount = (clone $baseQuery)
            //     ->where('status', 'refunded')
            //     ->where('product', 'payout')
            //     ->sum('amount');
    
    
    $bankName = \App\Models\PayinOnboardedBank::where('id', $merchant->payin_bank)->value('onboard_payin_bank');
            // ---------------- Response ----------------
            $responseData = [
                'total_payin_amount'         => number_format($total_payin_amount, 2, '.', ''),
                'total_payout_amount'        => number_format($total_payout_amount, 2, '.', ''),
                'today_payin'                => number_format($today_payin, 2, '.', ''),
                'today_payout'               => number_format($today_payout, 2, '.', ''),
                'payout_wallet'              => number_format($merchant->payout_wallet ?? 0, 2, '.', ''),
                'transactionStatusCounts'    => $transactionStatusCounts,
                'payinTransactionStatusCounts'  => $payinTransactionStatusCounts,
                'payoutTransactionStatusCounts' => $payoutTransactionStatusCounts,
                'todayPayinStatusCounts'     => $todayPayinStatusCounts,
                'todayPayoutStatusCounts'    => $todayPayoutStatusCounts,
                'monthWiseStatusCounts'      => $monthWiseStatusCounts,
                'PayinRollingAmount'         => number_format($merchant->rolling_amount ?? 0, 2, '.', ''),
                'PayingAmount'               => number_format($merchant->payin_wallet ?? 0, 2, '.', ''),
                'todayPayingAmount'          => number_format((float) $todayPayingAmount, 2, '.', ''),
                // 'todayPayoutgAmount'          => $todayPayoutgAmount,  
                'todayPayoutgAmount' => number_format((float) $todayPayoutgAmount, 2, '.', ''),
                'total_profit'               => number_format($total_profit, 2, '.', ''),
                'PayinProfitAmount'          => $merchant->total_charges ?? 0,
                'PayinRollingAmount_current' => number_format($PayinRollingAmount_current, 2, '.', ''),
                'PayingAmount_current'       => number_format($PayingAmount_current, 2, '.', ''),
                'PayinProfitAmount_current'  => number_format($PayinProfitAmount_current, 2, '.', ''),
                'payin_bank' => $bankName,
                // 'refund_amount'              => number_format($refund_amount ?? 0, 2, '.', ''),
                
            ];
    
            return response()->json(array_merge([
                'status' => true,
                'message' => 'Merchant collection summary fetched successfully',
                'role_type' => $user->role_type,
                'merchant_id' => $merchantId
            ], $responseData), 200);
    
        } catch (\Exception $e) {
            Log::error("MerchantCollection error for user {$user->id}: " . $e->getMessage());
            return response()->json([
                'status'  => false,
                'message' => 'Something went wrong while fetching merchant collection summary',
                'error'   => $e->getMessage(),
            ], 500);
        }
    }

    public function MerchantRecords(Request $request)
    {
        $user = auth()::user();
        if (!$user) {
        return response()->json(['message' => 'User not authenticated'], 401);
    }
    if ($user->role_type === 'admin') {
        $merchantId = $request->merchant_id;
        if (!$merchantId) {
            return response()->json([
                'status' => false,
                'message' => 'merchant_id is required for admin'
            ], 400);
        }
    } else {
        $merchantId = $user->id;
    }

    // Fetch merchant
    $merchant = User::find($merchantId);
    if (!$merchant) {
        return response()->json([
            'status' => false,
            'message' => 'Merchant not found'
        ], 404);
    }      
       $transactions = \App\Models\Report::where('user_id', $merchantId)
                            ->orderBy('created_at', 'desc');
    
        // Calculate summaries
        $summary = [
            'today' => [
                'success' => (clone $transactions)->where('status', 'success')->whereDate('created_at', today())->count(),
                'failed' => (clone $transactions)->where('status', 'failed')->whereDate('created_at', today())->count(),
                'pending' => (clone $transactions)->where('status', 'pending')->whereDate('created_at', today())->count(),
            ],
            'week' => [
                'success' => (clone $transactions)->where('status', 'success')->whereBetween('created_at', [now()->startOfWeek(), now()->endOfWeek()])->count(),
                'failed' => (clone $transactions)->where('status', 'failed')->whereBetween('created_at', [now()->startOfWeek(), now()->endOfWeek()])->count(),
                'pending' => (clone $transactions)->where('status', 'pending')->whereBetween('created_at', [now()->startOfWeek(), now()->endOfWeek()])->count(),
            ],
            'month' => [
                'success' => (clone $transactions)->where('status', 'success')->whereMonth('created_at', now()->month)->count(),
                'failed' => (clone $transactions)->where('status', 'failed')->whereMonth('created_at', now()->month)->count(),
                'pending' => (clone $transactions)->where('status', 'pending')->whereMonth('created_at', now()->month)->count(),
            ],
            'year' => [
                'success' => (clone $transactions)->where('status', 'success')->whereYear('created_at', now()->year)->count(),
                'failed' => (clone $transactions)->where('status', 'failed')->whereYear('created_at', now()->year)->count(),
                'pending' => (clone $transactions)->where('status', 'pending')->whereYear('created_at', now()->year)->count(),
            ],
        ];
    
        return response()->json([
            'status' => true,
            'merchant' => $merchant,
            'summary' => $summary,
        ]);        

}
    
    

}
