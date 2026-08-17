<?php

namespace App\Services\PayoutProviders;

use Illuminate\Support\Facades\Log;

class E2payVanProvider implements PayoutProviderInterface
{
    public function send(array $payload)
    {
        try {

            // ✅ Required fields
            $amount  = $payload['amount'];
            $orderid = $payload['orderid'];

            // ✅ Request body
            $bodyArray = [
                'number'    => $payload['bank_account_number'],
                'ifsc'         => $payload['bank_ifsc'],
                'amount'            => $amount,
                'mobile'     => $payload['beneficiary_phone'],
                'name'              => $payload['beneficiary_name'],
                'orderid' => $orderid,
                'userid'           => 'E2PAY126',
                'token'             => '$2y$10$U//XeZIPxpAf.3eEqEK3o.NSeNPUhjjZ/o1/G0nLvF1gYE10FImee',
            ];

            $body = json_encode($bodyArray, JSON_UNESCAPED_SLASHES);

            // ✅ Headers
            $headers = [
                'Content-Type: application/json',
                // 'Accept: application/json',
            ];

            // ✅ cURL
            $curl = curl_init();

            curl_setopt_array($curl, [
            CURLOPT_URL            => 'https://merchant.e2pay.store/api/payout/initiate',
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_ENCODING => '',
            CURLOPT_MAXREDIRS => 10,
            CURLOPT_TIMEOUT => 0,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
            CURLOPT_CUSTOMREQUEST => 'POST',
            CURLOPT_POSTFIELDS     => $body,
            CURLOPT_HTTPHEADER     => $headers,
        ]);

            $response  = curl_exec($curl);
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

            $statusNumber = $data['dataByBank']['status'] ?? 0;

            if ($statusNumber == 'Accepted') {
                $status = 'success';
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