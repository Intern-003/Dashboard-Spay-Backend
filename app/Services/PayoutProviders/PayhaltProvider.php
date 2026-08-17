<?php

namespace App\Services\PayoutProviders;

class PayhaltProvider implements PayoutProviderInterface
{
    public function send(array $payload)
    {
        // Required from common controller payload
        $amount  = $payload['amount'] ?? null;
        $orderid = $payload['orderid'] ?? null;

        // Config
        $url        = 'https://payhalt.com/gateway/merchant/payout/api/api_payout.php';
        $merchantId = '4exnGJ94aU';
      

        // ✅ Same payload as your CASE code
        $payload1 = [
            'merchant_id'             => $merchantId,
            'externalTransactionId'   => $orderid,
            'beneficiaryName'         => $payload['beneficiary_name'] ?? '',
            'beneficiaryAccountNo'    => $payload['bank_account_number'] ?? '',
            'beneficiaryIFSCCode'     => $payload['bank_ifsc'] ?? '',
            'beneficiaryMobileNumber' => $payload['beneficiary_phone'] ?? '',
            'amount'                  => $amount,
            'transferMode'            => $payload['transferMode'] ?? 'IMPS',
        ];

        $jsonBody = json_encode($payload1, JSON_UNESCAPED_SLASHES);

        // ✅ Headers (IMPORTANT: token header fix)
        $headers = [
            'Content-Type: application/json',
            'Accept: application/json',
        ];

        if (!empty($token)) {
            if ($authMode === 'bearer') {
                $headers[] = 'Authorization: Bearer ' . $token;
            } else {
                $headers[] = 'token: ' . $token;
            }
        }

        // ✅ cURL
        $curl = curl_init();

        curl_setopt_array($curl, [
            CURLOPT_URL            => $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_ENCODING       => '',
            CURLOPT_MAXREDIRS      => 10,
            CURLOPT_TIMEOUT        => 60,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_HTTP_VERSION   => CURL_HTTP_VERSION_1_1,
            CURLOPT_CUSTOMREQUEST  => 'POST',
            CURLOPT_POSTFIELDS     => $jsonBody,
            CURLOPT_HTTPHEADER     => $headers,
        ]);

        $response  = curl_exec($curl);
        $curlError = curl_error($curl);
        $httpCode  = (int) curl_getinfo($curl, CURLINFO_HTTP_CODE);
        curl_close($curl);

        // ✅ Curl error
        if ($response === false) {
            return [
                'status'     => 'failed',
                'statuscode' => 500,
                'message'    => $curlError ?: 'Curl error',
                '_http_code' => $httpCode,
            ];
        }

        // ✅ JSON decode
        $jsonDecode = json_decode($response, true);

        if (!is_array($jsonDecode)) {
            return [
                'status'     => 'failed',
                'statuscode' => 500,
                'message'    => 'Invalid JSON response from PayHalt',
                '_http_code' => $httpCode,
                'raw'        => $response,
            ];
        }

        // ✅ Map fields like your CASE
        $apiStatus = strtolower((string) ($jsonDecode['status'] ?? 'failed'));

        // Payhalt sometimes returns message inside data
        $inner = (is_array($jsonDecode['data'] ?? null)) ? $jsonDecode['data'] : [];

        $providerMsg =
            $jsonDecode['message']
            ?? $jsonDecode['msg']
            ?? $inner['message']
            ?? 'Transfer Initiated';

        $transactionId =
            $jsonDecode['transactionId']
            ?? $jsonDecode['refno']
            ?? $jsonDecode['rrn']
            ?? $jsonDecode['utr']
            ?? $inner['transactionId']
            ?? $inner['refno']
            ?? $inner['rrn']
            ?? $inner['utr']
            ?? null;

        // ✅ Same logic as your controller CASE:
        // if Payhalt success => pending else failed
        // plus: if http >= 400 => failed (token missing etc.)
        if ($httpCode >= 400) {
            $finalStatus = 'failed';
        } elseif ($apiStatus === 'success') {
            $finalStatus = 'pending';
        } else {
            $finalStatus = 'failed';
        }

        // ✅ Return structure that your CommanPayoutController expects
        return [
            'status'        => $finalStatus,
            'statuscode'    => ($finalStatus === 'failed') ? 400 : 200,
            'message'       => $providerMsg,
            'client_ref_no' => $transactionId ?? $orderid,
            'refno'         => $transactionId,
            'rrn'           => $transactionId,
            'utr'           => $transactionId,
            'txn_date'      => now()->format('Y-m-d H:i:s'),
            '_http_code'    => $httpCode,
            'raw_response'  => $jsonDecode,
        ];
    }
}