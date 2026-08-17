<?php

namespace App\Http\Controllers\Api\Payin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\User;
use App\Models\Credential;
use App\Models\Scheme;
use App\Models\AuthToken;
use App\Models\Report;
use Validator;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Http;


class VPAtoINTENTController extends Controller
{
    
    public function vpaToIntent(Request $request)
    {
        try {
            $amount = '100';//$request->amount ?? 1;
    
            // $vpa = "akashkasar444-1@oksbi";
            $vpa = "spayfintechpriv352568@ypbiz";
            $payeeName = "YUVRAJ";
    
            $orderId = "ORD" . time();
            $token = substr(md5(uniqid()), 0, 16);
            $tid = "TXN" . time();
    
            $note = "Payment for Order ID: " . $orderId;
    
            $upiLink = "upi://pay?pa={$vpa}&pn=" . urlencode($payeeName) .
                "&am=" . number_format($amount, 2, '.', '') .
                "&cu=INR&tn=" . urlencode($note) .
                "&tid={$tid}";
    
            // 🔥 QR IMAGE URL (NO LIBRARY)
            // $qrImage = "https://chart.googleapis.com/chart?chs=250x250&cht=qr&chl=" . urlencode($upiLink);
            $qrImage = "https://quickchart.io/qr?size=250&text=" . urlencode($upiLink);
    
            return response()->json([
                'status' => true,
                'data' => [
                    'order_id' => $orderId,
                    'token' => $token,
                    'amount' => $amount,
                    'upi_link' => $upiLink,
                    'qr' => $qrImage, // 👈 IMPORTANT
                    'callback_url' => "https://uatfintech.spay.live/api/callback/update/prod/e2paycallbkp"
                ]
            ]);
    
        } catch (\Exception $e) {
            return response()->json([
                'status' => false,
                'message' => $e->getMessage()
            ]);
        }
    }
}