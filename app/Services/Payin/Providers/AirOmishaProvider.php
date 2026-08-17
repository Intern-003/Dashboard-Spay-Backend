<?php

namespace App\Services\Payin\Providers;

use App\Models\User;
use App\Models\Report;
use App\Services\Payin\Contracts\PayinProviderInterface;
use App\Services\Payin\PayinService;

class AirOmishaProvider implements PayinProviderInterface
{
    public function __construct(private PayinService $payinService) {}

    public function generateUpiQr(array $payload, User $user, Report $report): array
    {
        // $url = "https://omishajewels.com/Backend/api/generateQR";

        $desc = $this->payinService->getCredentialDescription($user);
        // dump($desc);

            $access_token = $this->getAccessToken($user);
// dd($access_token);
            if (!is_string($access_token)) {
                return $access_token;
            }
            
            
              $data = [
                'orderid' => $payload['orderid'],
                'amount' => $payload['amount'],
                'buyer_email' => $payload['email'],
                'buyer_phone' => $payload['phone'],
                'call_type' => 'upiqr',
                'mer_dom' => base64_encode("https://omishajewels.com"),
                'customer_consent' => 'Y'
            ];

            $privatekey = hash('sha256', $desc['secret'] . '@' . $desc['username'] . ':|:' . $desc['password']);

            $encdata  = $this->encrypt(json_encode($data), $desc['secretKey']);
            $checksum = $this->checksum($data);

            $finalpayload = [
                'merchant_id' => $desc['merchant_id'],
                'encdata'     => $encdata,
                'checksum'    => $checksum,
                'privatekey'  => $privatekey
            ];
            
// dump($payload);

            $url = "https://kraken.airpay.co.in/airpay/pay/v4/api/generateorder/?token=" . $access_token;
// dump($url);
            $curl = curl_init();
            curl_setopt_array($curl, [
                CURLOPT_URL => $url,
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_CUSTOMREQUEST => 'POST',
                CURLOPT_POSTFIELDS => $finalpayload,
                CURLOPT_TIMEOUT => 30
            ]);

                        $result = curl_exec($curl);
            $curlError = curl_error($curl);
            curl_close($curl);

            if ($curlError) {
                return response()->json([
                    'status' => 'error',
                    'status_code' => 500,
                    'message' => 'CURL Error: ' . $curlError
                ], 500);
            }

            $response = json_decode($result, true);
// dump($response);
            if (!isset($response['response'])) {
                return response()->json([
                    'status' => 'error',
                    'status_code' => 400,
                    'message' => 'Invalid QR response from Airpay',
                    'raw_response' => $result
                ], 400);
            }

            $decrypted = $this->decrypt($response['response'], $desc['secretKey']);
            $decoded = json_decode($decrypted, true);
//   dd($decoded);

        if (!is_array($decoded) || ($decoded['status'] ?? '') !== 'success') {
            return [
                'http_code' => 500,
                'body' => [
                    'status' => 'failed',
                    'message' => 'QR API did not return success',
                    'raw' => $decoded
                ]
            ];
        }

        $data = $decoded['data'] ?? [];
//   dd($data);
        $report->update([
            'apitxnid' => $data['ap_transactionid'] ?? null,
            'option4'  => $desc['merchant_id'] ?? null,
        ]);

        return [
            'http_code' => 200,
            'body' => [
                'status_code' => $decoded['status_code'] ?? null,
                'status'      => $decoded['status'] ?? null,
                'data'        => [
                    'qrcode_string' => $data['qrcode_string'] ?? null,
                    'orderid'       => $payload['orderid'],
                    'txnid'         => $report->txnid,
                ],
            ]
        ];
    }
    
        public function getAccessToken(User $user)
    {
                $desc = $this->payinService->getCredentialDescription($user);
                // dump($desc);
        $data = [
            'client_id'     => $desc['client_id'],
            'client_secret' => $desc['client_secret'],
            'merchant_id'   => $desc['merchant_id'],
            'grant_type'    => 'client_credentials'
        ];
        // dd($data);

        $encdata  = $this->encrypt(json_encode($data), $desc['secretKey']);
        $checksum = $this->checksum($data);

        $payload = [
            'merchant_id' => $desc['merchant_id'],
            'encdata'     => $encdata,
            'checksum'    => $checksum
        ];

        $curl = curl_init();
        curl_setopt_array($curl, [
            CURLOPT_URL => 'https://kraken.airpay.co.in/airpay/pay/v4/api/oauth2/',
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_CUSTOMREQUEST  => 'POST',
            CURLOPT_POSTFIELDS     => $payload
        ]);

        $result = curl_exec($curl);
        curl_close($curl);

        if (!$result) {
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to connect to Airpay token API'
            ], 500);
        }

        $response = json_decode($result)->response ?? null;

        $access_token_data = $this->decrypt($response, $desc['secretKey']);
        $resp = json_decode($access_token_data, true);

        if (isset($resp['data']['access_token'])) {
            return $resp['data']['access_token'];
        }

        return response()->json([
            'status' => 'error',
            'message' => 'Failed to get token',
            'response' => $resp
        ], 400);
    }

    // =========================
    // GENERATE QR
    // =========================
//     public function generateUpiQr(array $payload, User $user, Report $report)
//     {
//         dd("function called");
//         try {
  
//             $postedData = $request->all();

//             $dynamicFields = ['orderid', 'amount', 'buyer_email', 'buyer_phone'];

//             foreach ($dynamicFields as $field) {
//                 if (empty($postedData[$field])) {
//                     return response()->json([
//                         'status' => 'error',
//                         'status_code' => 400,
//                         'message' => "Missing required field: $field"
//                     ], 400);
//                 }
//             }

//             $access_token = $this->getAccessToken();

//             if (!is_string($access_token)) {
//                 return $access_token;
//             }


//             $data = [
//                 'orderid' => $postedData['orderid'],
//                 'amount' => $postedData['amount'],
//                 'buyer_email' => $postedData['buyer_email'],
//                 'buyer_phone' => $postedData['buyer_phone'],
//                 'call_type' => 'upiqr',
//                 'mer_dom' => base64_encode("https://omishajewels.com"),
//                 'customer_consent' => 'Y'
//             ];

//             $privatekey = hash('sha256', $this->secret . '@' . $this->username . ':|:' . $this->password);

//             $encdata  = $this->encrypt(json_encode($data), $this->secretKey);
//             $checksum = $this->checksum($data);

//             $payload = [
//                 'merchant_id' => $this->merchant_id,
//                 'encdata'     => $encdata,
//                 'checksum'    => $checksum,
//                 'privatekey'  => $privatekey
//             ];

//             $url = "https://kraken.airpay.co.in/airpay/pay/v4/api/generateorder/?token=" . $access_token;

//             $curl = curl_init();
//             curl_setopt_array($curl, [
//                 CURLOPT_URL => $url,
//                 CURLOPT_RETURNTRANSFER => true,
//                 CURLOPT_CUSTOMREQUEST => 'POST',
//                 CURLOPT_POSTFIELDS => $payload,
//                 CURLOPT_TIMEOUT => 30
//             ]);

//             $result = curl_exec($curl);
//             $curlError = curl_error($curl);
//             curl_close($curl);

//             if ($curlError) {
//                 return response()->json([
//                     'status' => 'error',
//                     'status_code' => 500,
//                     'message' => 'CURL Error: ' . $curlError
//                 ], 500);
//             }

//             $response = json_decode($result, true);

//             if (!isset($response['response'])) {
//                 return response()->json([
//                     'status' => 'error',
//                     'status_code' => 400,
//                     'message' => 'Invalid QR response from Airpay',
//                     'raw_response' => $result
//                 ], 400);
//             }

//             $decrypted = $this->decrypt($response['response'], $this->secretKey);
//             $decodedResponse = json_decode($decrypted, true);

//             return response()->json([
//                 'response' => $decodedResponse
//             ]);

//         } catch (Exception $e) {

//             return response()->json([
//                 'status' => 'error',
//                 'status_code' => 500,
//                 'message' => 'Exception: ' . $e->getMessage()
//             ], 500);
//         }
//     }

    // =========================
    // HELPER FUNCTIONS
    // =========================

    private function encrypt($data, $encryptionkey)
    {
        $iv = bin2hex(openssl_random_pseudo_bytes(8));
        $raw = openssl_encrypt($data, 'AES-256-CBC', $encryptionkey, OPENSSL_RAW_DATA, $iv);
        return $iv . base64_encode($raw);
    }

    private function decrypt($response, $encryptionkey)
    {
        $iv = substr($response, 0, 16);
        $encryptedData = substr($response, 16);
        return openssl_decrypt(base64_decode($encryptedData), 'AES-256-CBC', $encryptionkey, OPENSSL_RAW_DATA, $iv);
    }

    private function checksum($data)
    {
        ksort($data);
        $checksumdata = '';
        foreach ($data as $value) {
            $checksumdata .= $value;
        }
        return hash('SHA256', $checksumdata . date('Y-m-d'));
    }
    
    
}