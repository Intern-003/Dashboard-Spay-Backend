<?php

namespace App\Services\PayoutProviders;

class NxtProvider implements PayoutProviderInterface
{
    public function send(array $payload)
    {
 
        $amount = $payload['amount'];
        $orderid = $payload['orderid'];
        $timestamp = time();
     
        // 2️⃣ Request body (ARRAY)
        $bodyArray = [
            "amount" => $amount,
            "transaction_id" => $orderid,
            "account_holder_name" => $payload['beneficiary_name']  ,
            "account_number" =>  $payload['bank_account_number'],
            "ifsc_code" =>  $payload['bank_ifsc'],
            "mode"=> "IMPS",
            "mobile" =>  $payload['beneficiary_phone'],
            "remarks" => "Test transaction",
            "latitude" => "28.704",
            "longitude" => "77.1025",
            "purpose" => "Product Purchase",
    
        ];
        // dump($bodyArray);

        // 3️⃣ Convert to COMPACT JSON
        $body = json_encode($bodyArray, JSON_UNESCAPED_SLASHES);
        // dump($body);
        
        // 4️⃣ Build payload for signature
        $method = "POST";
        $endpoint = "/api/v1/payout/initiate";
        $secretKey = "sk_b2f5d411f8e62d2146bf9b23e72418ab9a5812d597ded6498635db7504da630a";
    
        $payload1  = $method . "|" . $endpoint . "|" . $timestamp . "|" . $body;
        // dump("payload : ".$payload1);
        
        // 5️⃣ Generate signature
        $signature = hash_hmac("sha256", $payload1,$secretKey);

        // 6️⃣ Headers (SPACE after colon is IMPORTANT)
        $headers = [
            'Content-Type: application/json',
            'X-Client-ID: client_e9527ce27a4c3cf06b52ceeb4302339a0f39f77a50450318',
            'X-Signature: ' . $signature,
            'X-Timestamp: ' . $timestamp,
            'X-Request-ID: ' . $timestamp . rand(100,999),
            'X-API-Key: your_api_key_here'
        ];
        //   dd($body);
        
        // 7️⃣ cURL (SEND JSON STRING)
        $curl = curl_init();
    
        curl_setopt_array($curl, [
            CURLOPT_URL => 'https://nxtbanking.ai/api/v1/payout/initiate',
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => $body, // ✅ JSON STRING
            CURLOPT_HTTPHEADER => $headers,
        ]);

        $response = curl_exec($curl);
        curl_close($curl);
        $data = json_decode($response,true);
        // dump($response);
        return $data;
        // dd($data);
        // dd($data['data']['upi_intent']);
    
    }
}
