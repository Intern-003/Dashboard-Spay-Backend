<?php

namespace App\Http\Controllers\Api\Payin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Services\Payin\PayinService;

class ShaymavenueStatusPayinController extends Controller
{
    public function __construct(private PayinService $payinService)
    {
    }

    public function Shaymavenue_payin_status(Request $request)
    {
        $url = "https://shaymavenue.in/api/v1/check_status";

        $payload = [
            'mid'           => 'SHYAM5184179346',
            'apikey'        => 'Q8@vL2#Rx7!Mp4$Zk',
            'client_txn_id' => $request->client_txn_id,
            'route' => 1,
        ];

        $response = $this->payinService->commonCurl($url, $payload, ['Content-Type: application/json']);

        return response()->json($response);
    }
}