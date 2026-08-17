<?php

namespace App\Services\PayoutProviders;

use Illuminate\Support\Facades\Log;

class BridgMoneyProvider implements PayoutProviderInterface
{
    public function send(array $payload)
    {
        try {
            // dd("Hello");

            $body = json_encode([
                'accountNumber' => $payload['bank_account_number'],
                'ifsc'          => $payload['bank_ifsc'],
                'amount'        => $payload['amount'],
                'email'         => $payload['beneficiary_email'],
                'phoneNumber'   => $payload['beneficiary_phone'],
                'name'          => $payload['beneficiary_name'],
            ], JSON_UNESCAPED_SLASHES);

            $curl = curl_init();

            curl_setopt_array($curl, [
                CURLOPT_URL            => 'https://spay.live/spayliveBackend/api/BM/payout/request',
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_FOLLOWLOCATION => true,
                CURLOPT_MAXREDIRS      => 5,
                CURLOPT_TIMEOUT        => 60,
                CURLOPT_CUSTOMREQUEST  => 'POST',
                CURLOPT_POSTFIELDS     => $body,
                CURLOPT_HTTPHEADER     => [
                    'Content-Type: application/json',
                    'Accept: application/json',
                ],
            ]);

            $response  = curl_exec($curl);
            $curlError = curl_error($curl);
            $httpCode  = curl_getinfo($curl, CURLINFO_HTTP_CODE);

            curl_close($curl);

            if ($response === false) {
                return [
                    'status'  => 'failed',
                    'message' => $curlError ?: 'Curl Error',
                    'refno'   => null,
                    'raw'     => null,
                ];
            }

            $data = json_decode($response, true);

            if (json_last_error() !== JSON_ERROR_NONE) {
                return [
                    'status'  => 'failed',
                    'message' => 'Invalid JSON response from provider',
                    'refno'   => null,
                    'raw'     => $response,
                ];
            }

            Log::info('BridgMoney Response', [
                'http_code' => $httpCode,
                'request'   => json_decode($body, true),
                'response'  => $data,
            ]);
            
            $bridg = $data['payout']['response']['data'];

            // Beneficiary Details
            $beneficiaryStatus = $data['beneficiary']['status'] ?? false;
            $beneficiaryId     = $data['beneficiary']['response']['data']['beneficiaryId'] ?? null;
            $beneficiaryMsg    = $data['beneficiary']['response']['message'] ?? null;

            // Payout Details
            $payoutStatus      = $data['payout']['status'] ?? false;
            $payoutHttpCode    = $data['payout']['httpCode'] ?? null;
            $payoutResponse    = $data['payout']['response'] ?? [];

            $payoutError       = $data['payout']['response']['error'] ?? null;
            $payoutMessage     = $data['payout']['response']['message'] ?? null;
            $requestId         = $data['payout']['response']['requestId'] ?? null;
            
            $payoutTxnId       = $payoutResponse['data']['payoutTransactionId'] ?? $bridg['payoutTransactionId'];
            $transactionId     = $payoutResponse['data']['transactionId'] ?? $bridg['transactionId'];
            $amount            = $payoutResponse['data']['amount'] ?? null;
            $payoutTxnStatus   = $payoutResponse['data']['status'] ?? null;

            // Final Status
            $status = 'failed';

            if ($payoutStatus === true) {
                $status = 'success';
            } elseif (
                isset($data['payout']['response']['status']) &&
                strtolower((string) $data['payout']['response']['status']) === 'pending'
            ) {
                $status = 'pending';
            }
            
            // dump($beneficiaryStatus);
            // dump($beneficiaryId);
            // dump($beneficiaryMsg);
            // dump($payoutStatus);
            // dump($payoutHttpCode);
            // dump($payoutResponse);
            // dump($payoutError);
            // dump($payoutMessage);
            // dump($requestId);
            // dump($payoutTxnId);
            // dump($transactionId);
            // dump($amount);
            // dump($payoutTxnStatus);
            // dD($status);

            return [
                'status' => $status,

                // Beneficiary
                'beneficiary_status'  => $beneficiaryStatus,
                'beneficiary_id'      => $beneficiaryId,
                'beneficiary_message' => $beneficiaryMsg,

                // Payout
                'payout_status' => $payoutStatus,
                'http_code'     => $payoutHttpCode,
                // 'payoutResponse'=> $payoutResponse,
                'error'         => $payoutError,
                'message'       => $payoutMessage,
                'request_id'    => $requestId,
                
                'payoutTxnId'   => $payoutTxnId,
                'transactionId' => $transactionId,
                'amount'        => $amount,
                'payoutTxnStatus'=> $payoutTxnStatus,

                // Reference
                // 'refno' => $data['transactionReference'] ?? null,

                // 'raw' => $data,
            ];

        } catch (\Throwable $e) {

            Log::error('BridgMoney Exception', [
                'error' => $e->getMessage(),
                'line'  => $e->getLine(),
                'file'  => $e->getFile(),
            ]);

            return [
                'status'  => 'failed',
                'message' => $e->getMessage(),
                'refno'   => null,
                'raw'     => null,
            ];
        }
    }
}
            
//             $response  = curl_exec($curl);
//             // dd($response);
//             $curlError = curl_error($curl);
//             $httpCode  = curl_getinfo($curl, CURLINFO_HTTP_CODE);

//             curl_close($curl);
            

//             if ($response === false) {
//                 return [
//                     'status'  => 'failed',
//                     'message' => $curlError ?: 'Curl error',
//                     'refno'   => null,
//                     'raw'     => null
//                 ];
//             }
            
//             $data = json_decode($response, true);

//             if (json_last_error() !== JSON_ERROR_NONE) {
//                 return [
//                     'status'  => 'failed',
//                     'message' => 'Invalid JSON response from provider',
//                     'refno'   => null,
//                     'raw'     => $response,
//                 ];
//             }
            
//             Log::info('BridgMoney Response', [
//                 'http_code' => $httpCode,
//                 'request'   => json_decode($body, true),
//                 'response'  => $data,
//                 'raw'       => $response,
//             ]);
            
//             $status = 'failed';

//             if (
//                 isset($data['status']) &&
//                 (
//                     $data['status'] === true ||
//                     $data['status'] === 1 ||
//                     $data['status'] === '1' ||
//                     strtolower((string)$data['status']) === 'success'
//                 )
//             ) {
//                 $status = 'success';
//             }

        
//             if (
//                 isset($data['status']) &&
//                 (
//                     strtolower((string)$data['status']) === 'pending' ||
//                     $data['status'] === 2
//                 )
//             ) {
//                 $status = 'pending';
//             }

//             return [
//                 'status'  => $status,
//                 'message' => $data['message'] ?? 'No message received',
//                 'refno'   => $data['trxId']
//                     ?? $data['transactionId']
//                     ?? $data['referenceId']
//                     ?? null,
//                 'raw'     => $data,
//             ];

//         } catch (\Exception $e) {

//             Log::error('BridgMoney Exception', [
//                 'error' => $e->getMessage(),
//                 'line'  => $e->getLine(),
//                 'file'  => $e->getFile(),
//             ]);

//             return [
//                 'status'  => 'failed',
//                 'message' => $e->getMessage(),
//                 'refno'   => null,
//                 'raw'     => null,
//             ];
//         }
//     }
// }