<?php

namespace App\Http\Controllers\Api\Payout;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\User;
use App\Models\Credential;
use App\Models\Scheme;
use App\Models\AuthToken;
use App\Models\Report;
use Validator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Carbon\Carbon;



class PayoutBalanceController extends Controller
{
    public function payout_balance()
    {
        
        $curl = curl_init();
        
        curl_setopt_array($curl, array(
          CURLOPT_URL => 'https://api.bulkpe.in/client/fetchBalance',
          CURLOPT_RETURNTRANSFER => true,
          CURLOPT_ENCODING => '',
          CURLOPT_MAXREDIRS => 10,
          CURLOPT_TIMEOUT => 30,
          CURLOPT_FOLLOWLOCATION => true,
          CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
          CURLOPT_CUSTOMREQUEST => 'GET',
          CURLOPT_HTTPHEADER => array(
            'Authorization: Bearer aWSVQNyt+z3IiJHV+YX9UnzVgAaqZeAgGbnhPBnfin7IDjWy9i6OSc4Kd1Zpmwdeb1RWzfVG2rIx3kSzNv9vlA==',
            'Content-Type: application/json'
          ),
        ));
        
        $response = curl_exec($curl);
        
        curl_close($curl);
        

            
        $apiResponse2 = json_decode($response, true);
        DD($apiResponse2);
        $payout_balance = $apiResponse2['data']['Balance'];
        return response()->json([
            "status"=>"payout amount fetched",
            "payout_balance" => $payout_balance
        ]);
/////////////////--------E2PAY---------//////////////////
        // $curl = curl_init();
        // curl_setopt_array($curl, array(
        //   CURLOPT_URL => 'https://marketingllp.in/Api/Balance',
        //   CURLOPT_RETURNTRANSFER => true,
        //   CURLOPT_ENCODING => '',
        //   CURLOPT_MAXREDIRS => 10,
        //   CURLOPT_TIMEOUT => 0,
        //   CURLOPT_FOLLOWLOCATION => true,
        //   CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
        //   CURLOPT_CUSTOMREQUEST => 'POST',
        //   CURLOPT_POSTFIELDS =>'{
        //     "MerchantId" : "MT12996511"
        // }',
        //   CURLOPT_HTTPHEADER => array(
        //     'Token: k9njUwyaPf4RXyNPmaQVF6SWPIDwz5nO',
        //     'Content-Type: application/json'
        //   ),
        // ));
        
        // $response = curl_exec($curl);
        // curl_close($curl);
            
        // $apiResponse2 = json_decode($response, true);
        // $payout_balance = $apiResponse2['data']['balance'];
        // return response()->json([
        //   "status"=>"payout amount fetched",
        //   "payout_balance" => $payout_balance
        // ]);



        // dd("hello");
            // $curl = curl_init();
            
            // curl_setopt_array($curl, array(
            //   CURLOPT_URL => 'https://payhalt.com/gateway/merchant/payout/api/balance_check.php',
            //   CURLOPT_RETURNTRANSFER => true,
            //   CURLOPT_ENCODING => '',
            //   CURLOPT_MAXREDIRS => 10,
            //   CURLOPT_TIMEOUT => 0,
            //   CURLOPT_FOLLOWLOCATION => true,
            //   CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
            //   CURLOPT_CUSTOMREQUEST => 'POST',
            //   CURLOPT_POSTFIELDS =>'{
            //     "merchant_id":"4exnGJ94aU"
            // }',
            //   CURLOPT_HTTPHEADER => array(
            //     'Content-Type: application/json'
            //   ),
            // ));
            // $response = curl_exec($curl);
            // // dump($r esponse);
            // curl_close($curl);
            
            //         $apiResponse2 = json_decode($response, true);
            // // dd($apiResponse2);
            // $payout_balance = $apiResponse2['data']['balance'];
            // return response()->json([
            //     "status"=>"payout amount fetched",
            //      "payout_balance" => $payout_balance
            // ]);
    }

}