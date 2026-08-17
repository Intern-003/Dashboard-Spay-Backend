<?php

namespace App\Services\PayoutProviders;

use Illuminate\Support\Facades\Log;

class ShaymavenueProvider implements PayoutProviderInterface
{
    public function send(array $payload)
    {
        try {
            // dd("Hello");


            $body = json_encode([
                'mid'             => 'SHYAM5184179346',
                'apikey'          => 'Q8@vL2#Rx7!Mp4$Zk',
                'client_txn_id'   => $payload['orderid'],
                'account_number'  => $payload['bank_account_number'],
                'ifsc'            => $payload['bank_ifsc'],
                'amount'          => $payload['amount'],
                'email'           => $payload['beneficiary_email'],
                'customer_mobile' => $payload['beneficiary_phone'],
                'customer_name'   => $payload['beneficiary_name'],
            ], JSON_UNESCAPED_SLASHES);

            // dd($body);

            $curl = curl_init();

            curl_setopt_array($curl, [
                CURLOPT_URL            => 'https://shaymavenue.in/api/v1/transfer',
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
                Log::error('Transfer Curl Error', [
                    'http_code' => $httpCode,
                    'error'     => $curlError,
                ]);

                return [
                    'status'  => 'failed',
                    'message' => $curlError ?: 'Curl Error',
                    'refno'   => null,
                    'utr'     => null,
                    'txn_id'  => null,
                    'amount'  => null,
                    'fees'    => null,
                    'raw'     => null,
                ];
            }


            $data = json_decode($response, true);

            if (json_last_error() !== JSON_ERROR_NONE) {
                Log::error('Transfer Invalid JSON Response', [
                    'http_code' => $httpCode,
                    'response'  => $response,
                ]);

                return [
                    'status'  => 'failed',
                    'message' => 'Invalid JSON response from provider',
                    'refno'   => null,
                    'utr'      => null,
                    'txn_id'  => null,
                    'amount'  => null,
                    'fees'    => null,
                    'raw'     => $response,
                ];
            }

            Log::info('Shaymavenue Response', [
                'http_code' => $httpCode,
                'request'   => json_decode($body, true),
                'response'  => $data,
            ]);
     
    
            // TOP LEVEL RESPONSE
            $statusCode = $data['statuscode'] ?? null;
            $message    = $data['msg'] ?? null;
            $transfer   = $data['data'] ?? [];

            // TRANSACTION DETAILS
            $txnTime = $transfer['TXN_Time'] ?? null;
            $txnId   = $transfer['TXN_ID'] ?? null;
            $amount  = $transfer['Amount'] ?? null;
            $fees    = $transfer['Fees'] ?? null;
            $utr     = $transfer['UTR'] ?? null;


            $providerStatus = strtolower(
                (string) ($transfer['status'] ?? '')
            );

            
            if (
                $statusCode === 'TXN' &&
                $providerStatus === 'success'
            ) {
                $status = 'success';
            } elseif (
                in_array($providerStatus, ['pending', 'processing'], true)
            ) {
                $status = 'pending';
            } else {
                $status = 'failed';
            }

            return [
                'status'  => $status,
                'message' => $message,

                'txn_id'  => $txnId,
                'txn_time' => $txnTime,

                'amount'  => $amount,
                'utr'     => $utr,
                'refno'   => $utr,

                'statuscode'      => $statusCode,
                'provider_status' => $providerStatus,
                'http_code'       => $httpCode,

                'raw' => $data,
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
                'utr'     => null,
                'txn_id'  => null,
                'amount'  => null,
                'fees'    => null,
                'raw'     => null,
            ];

        
        }
    }
}
