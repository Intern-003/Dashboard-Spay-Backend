<?php

namespace App\Services\PayoutProviders;

class IdfcProvider implements PayoutProviderInterface
{
    public function send(array $payload)
    {
		// dd("idfc");
		// ✅ Required fields (from your common payload style)
        $amount  = $payload['amount'];
        $orderid = $payload['orderid'];
		
        // ✅ Build request payload (same as your case)
        $bodyArray = [
            'beneficiary_account_number'      => $payload['bank_account_number'],
            'beneficiary_ifsc_code'           => $payload['bank_ifsc'],
            'amount'              => $amount,
            'beneficiary_mobile'       => $payload['beneficiary_phone'],
            'beneficiary_name'                => $payload['beneficiary_name'],
            'merchant_txn_id'   => $orderid,
            'beneficiary_bank_name'            => 'kotak Bank',
        ];
    //    dump("bodyarray",$bodyArray);
        $body = json_encode($bodyArray, JSON_UNESCAPED_SLASHES);
        //  dump("body",$body);
        $curl = curl_init();

        curl_setopt_array($curl, array(
        CURLOPT_URL => 'https://omishajewels.com/Backend/api/hdfcpayout',
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_ENCODING => '',
        CURLOPT_MAXREDIRS => 10,
        CURLOPT_TIMEOUT => 0,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
        CURLOPT_CUSTOMREQUEST => 'POST',
        CURLOPT_POSTFIELDS => $body,
        CURLOPT_HTTPHEADER => array(
            'Content-Type: application/json'
        ),
        ));

        $response = curl_exec($curl);
       dump($response);
        curl_close($curl);
        echo $response;


        // ✅ 4) cURL call
        // $curl = curl_init();

        // curl_setopt_array($curl, [
        //     CURLOPT_URL            => $payload['url'] ?? 'http://marketingllp.in/api/Payout/Payment',
        //     CURLOPT_RETURNTRANSFER => true,
        //     CURLOPT_FOLLOWLOCATION => true,
        //     CURLOPT_MAXREDIRS      => 10,
        //     CURLOPT_TIMEOUT        => 60,
        //     CURLOPT_HTTP_VERSION   => CURL_HTTP_VERSION_1_1,
        //     CURLOPT_CUSTOMREQUEST  => 'POST',
        //     CURLOPT_POSTFIELDS     => $body,
        //     CURLOPT_HTTPHEADER     => $headers,
        // ]);

        // $response  = curl_exec($curl);
        // $curlError = curl_error($curl);
        // $httpCode  = curl_getinfo($curl, CURLINFO_HTTP_CODE);
        // curl_close($curl);

        // ✅ 5) Curl error handling
        if ($response === false) {
            return [
                'status'     => 'failed',
                'statuscode' => 500,
                'message'    => $curlError ?: 'Curl error',
                'http_code'  => $httpCode,
            ];
        }

        // ✅ 6) Decode JSON
        $data = json_decode($response, true);
     

        if (!is_array($data)) {
            return [
                'status'     => 'failed',
                'statuscode' => 500,
                'message'    => 'Invalid JSON response from E2Pay',
                'http_code'  => $httpCode,
                'raw'        => $response,
            ];
        }


        return $data;
    }
}