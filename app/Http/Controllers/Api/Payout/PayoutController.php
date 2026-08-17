<?php


namespace App\Http\Controllers\Api\Payout;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Validator;
use App\Http\Controllers\Controller;

class PayoutController extends Controller
{
    public function payhaltPayout(Request $request)
    {
        // ✅ Validate incoming request (same fields as Payhalt)
        $validator = Validator::make($request->all(), [
            'merchant_id'             => 'required|string',
            'externalTransactionId'   => 'required|string',
            'beneficiaryName'         => 'required|string',
            'beneficiaryAccountNo'    => 'required|string',
            'beneficiaryIFSCCode'     => 'required|string',
            'beneficiaryMobileNumber' => 'required|digits_between:8,15',
            'amount'                  => 'required|numeric|min:1',
            'transferMode'            => 'required|string|in:IMPS,NEFT,RTGS,UPI',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status'  => 'failed',
                'message' => 'Validation error',
                'errors'  => $validator->errors(),
            ], 422);
        }

        // ✅ Payload exactly like cURL (keep types safe)
        $payload = [
            "merchant_id"             => (string) $request->merchant_id,
            "externalTransactionId"   => (string) $request->externalTransactionId,
            "beneficiaryName"         => (string) $request->beneficiaryName,
            "beneficiaryAccountNo"    => (string) $request->beneficiaryAccountNo,
            "beneficiaryIFSCCode"     => (string) $request->beneficiaryIFSCCode,
            "beneficiaryMobileNumber" => (string) $request->beneficiaryMobileNumber, // send as string
            "amount"                  => number_format((float)$request->amount, 2, '.', ''),
            "transferMode"            => (string) $request->transferMode,
        ];

        try {
            $url = "https://payhalt.com/gateway/merchant/payout/api/api_payout.php";

            // ✅ Call Payhalt
            $res = Http::withHeaders([
                    'Content-Type' => 'application/json',
                    'Accept'       => 'application/json',
                ])
                ->timeout(30)
                ->post($url, $payload);

            // ✅ Response handling
            $httpCode = $res->status();
            $body = $res->json(); // null if not JSON

            return response()->json([
                'status'     => $res->successful() ? 'success' : 'failed',
                'http_code'  => $httpCode,
                'request'    => $payload,
                'response'   => $body ?? $res->body(), // fallback if not JSON
            ], $res->successful() ? 200 : 400);

        } catch (\Throwable $e) {
            return response()->json([
                'status'  => 'failed',
                'message' => 'Server error while calling Payhalt',
                'error'   => $e->getMessage(),
            ], 500);
        }
    }
}