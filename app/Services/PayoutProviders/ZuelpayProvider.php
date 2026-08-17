<?php

namespace App\Services\PayoutProviders;

use Illuminate\Support\Facades\Log;

class ZuelpayProvider implements PayoutProviderInterface
{
    public function send(array $payload)
    {
        dd("Hi YUVRAJ MISHRA");
        try {

            // ✅ Required fields
            $amount  = $payload['amount'];
            $orderid = $payload['orderid'];

            // ✅ Request body
            $bodyArray = [
                'account'      => $payload['bank_account_number'],
                'ifsc'         => $payload['bank_ifsc'],
                'amount'       => $amount,
                'email'        => $payload['beneficiary_email'],
                'mobile'       => $payload['beneficiary_phone'],
                'account_name' => $payload['beneficiary_name'],
                'agent_id'     => $orderid,
                'remark'       => 'Payout',
                'payment_type' => 'IMPS',
            ];
            $body = json_encode($bodyArray, JSON_UNESCAPED_SLASHES);


            // ✅ Headers
            $headers = [
                'Content-Type: application/json',
                'Accept: application/json',
                'Token: ZKEY4twUF1SCd2PdQJF7E99FxkH1r',
                'checkSum: b45c82e1d117490f760c3a516bfca95541771663f4e4d18bce8f99d0cba2bf0b'
            ];

            // ✅ cURL
            $curl = curl_init();

            curl_setopt_array($curl, [
            CURLOPT_URL            => 'https://api.zuelpay.in/finance/payout/payment',
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_MAXREDIRS      => 5,
            CURLOPT_TIMEOUT        => 60,
            CURLOPT_CUSTOMREQUEST  => 'POST',
            CURLOPT_POSTFIELDS     => $body,
            CURLOPT_HTTPHEADER     => $headers,
        ]);

            // curl_setopt_array($curl, [
            //     CURLOPT_URL            => 'http://marketingllp.in/api/Payout/Payment',
            //     CURLOPT_RETURNTRANSFER => true,
            //     CURLOPT_TIMEOUT        => 60,
            //     CURLOPT_CUSTOMREQUEST  => 'POST',
            //     CURLOPT_POSTFIELDS     => $body,
            //     CURLOPT_HTTPHEADER     => $headers,
            // ]);

            $response  = curl_exec($curl);
            // dd($response);
            $curlError = curl_error($curl);
            $httpCode  = curl_getinfo($curl, CURLINFO_HTTP_CODE);

            curl_close($curl);

            // ✅ Curl error
            if ($response === false) {
                return [
                    'status'  => 'failed',
                    'message' => $curlError ?: 'Curl error',
                    'refno'   => null,
                    'raw'     => null
                ];
            }

            // ✅ Decode JSON (double decode safe)
            $data = json_decode($response, true);

            if (is_string($data)) {
                $data = json_decode($data, true);
            }

            Log::debug("E2Pay Response", [
                'http_code' => $httpCode,
                'raw'       => $response,
                'decoded'   => $data
            ]);

            if (!is_array($data)) {
                return [
                    'status'  => 'failed',
                    'message' => 'Invalid JSON response from provider',
                    'refno'   => null,
                    'raw'     => $response
                ];
            }

            /**
             * =====================================================
             * 🔥 STATUS NORMALIZATION (VERY IMPORTANT)
             * =====================================================
             * E2Pay returns:
             * status = 2 → success
             * status = 1 → pending
             * status = 0 → failed
             */

            $statusNumber = $data['data']['status'] ?? 0;

            if ($statusNumber == 2) {
                $status = 'success';
            } elseif ($statusNumber == 1) {
                $status = 'pending';
            } else {
                $status = 'failed';
            }

            return [
                'status'  => $status,
                'message' => $data['message'] ?? 'No message',
                'refno'   => $data['data']['trxId'] ?? null,
                'raw'     => $data
            ];

        } catch (\Exception $e) {

            Log::error("E2Pay Exception", [
                'error' => $e->getMessage()
            ]);

            return [
                'status'  => 'failed',
                'message' => $e->getMessage(),
                'refno'   => null,
                'raw'     => null
            ];
        }
    }
}