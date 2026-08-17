<?php

namespace App\Http\Controllers\Api\Payin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Services\Payin\PayinService;

class RiseXpayStatusPayinController extends Controller
{
    public function __construct(private PayinService $payinService)
    {
    }

    public function RiseXpay_payin_status(Request $request)
    {
        $url = "https://risexpay.in/api/v1/check_order_status";

        $payload = [
            'mid'           => 'RPAYZ8062131448',
            'apikey'        => 'uahqlqh799dulm68',
            'route'         => 1,
            'client_txn_id' => $request->client_txn_id,
        ];

        $response = $this->payinService->commonCurl($url, $payload, ['Content-Type: application/json']);

        return response()->json($response);
    }
}