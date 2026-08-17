<?php

namespace App\Console;

use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Console\Kernel as ConsoleKernel;
use App\Models\User;
use App\Models\Report;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;
use Illuminate\Support\Facades\File;

class Kernel extends ConsoleKernel
{
    /**
     * Define the application's command schedule.
     */
    protected function schedule(Schedule $schedule): void
    {
        // $schedule->command('inspire')->hourly();
        $schedule->command('payin:auto-fail')->everyFiveMinutes();
        $schedule->command('payin:reconcile')->hourly();



////////////////////-----------------------------////////////////////////////

        // Airpay Omisha Check Status
    //     $schedule->call(function () {
    
    //     $now = now();
    //     $logPath = storage_path('logs/airpay_checkstatus_test_' . $now->format('Y-m-d') . '.log');
    
    //     File::append($logPath, "🚀 [Airpay Omisha CheckStatus Scheduler] Started at {$now}" . PHP_EOL);
    
    //     try {
    
    //         \App\Models\User::chunk(100, function ($users) use ($logPath) {
    
    //             File::append($logPath, "👥 Processing user chunk count: " . count($users) . PHP_EOL);
    
    //             foreach ($users as $user) {
    
    //                 File::append($logPath, "➡️ User ID: {$user->id}" . PHP_EOL);
    
    //                 $reports = \App\Models\Report::where('user_id', $user->id)
    //                     ->where('product', 'UPI')
    //                     ->where('status', 'failed')
    //                     ->where('option4', '353405')
    //                     ->where('option1', 'payin calculation is pending')
    //                     ->whereNotNull('apitxnid')
    //                     ->where('created_at', '>=', now()->subHours(24))
    //                     ->limit(20)
    //                     ->get();
                        
    //                     // $reports = \App\Models\Report::where('user_id', $user->id)
    //                     // ->where('product', 'UPI')
    //                     // ->where('status', 'failed')
    //                     // ->where('option4', '353405')
    //                     // ->where('option1', 'TXN check (via checkstatus api)')
    //                     // ->whereNotNull('apitxnid')
    //                     // ->where('created_at', '>=', now()->subHours(24))
    //                     // ->limit(20)
    //                     // ->get();
    
    //                 File::append($logPath, "📊 Reports Found: {$reports->count()} for User ID: {$user->id}" . PHP_EOL);
    
    //                 foreach ($reports as $report) {
    
    //                     try {
    
    //                         File::append($logPath, "🧾 Processing Report ID: {$report->id}, MYTXNID: {$report->mytxnid}" . PHP_EOL);
    
    //                         if (empty($report->apitxnid)) {
    //                             File::append($logPath, "⚠️ Empty API TXNID for Report {$report->id}" . PHP_EOL);
    //                             continue;
    //                         }
    
    //                         $url = "https://omishajewels.com/Backend/api/checkstatus?ap_transactionid={$report->apitxnid}";
    //                         File::append($logPath, "🌐 API Call: {$url}" . PHP_EOL);
    
    //                         $response = \Http::timeout(15)->get($url);
    
    //                         if (!$response->successful()) {
    
    //                             File::append($logPath, "❌ API Failed for Report {$report->id}" . PHP_EOL);
    //                             continue;
    //                         }
    
    //                         $result = $response->json();
    
    //                         $paymentStatus = strtolower($result['data']['transaction_payment_status'] ?? '');
    //                         // $Txnreason1 = $result['data']['transaction_payment_status'] ?? $result['data']['reason'] ?? $result['data']['transaction_reason'] ??'' ;
    //                         $Txnreason1 = implode(' - ', array_filter([
    //                             $result['data']['transaction_payment_status'] ?? null,
    //                             $result['data']['reason'] ?? null,
    //                             $result['data']['transaction_reason'] ?? null
    //                         ]));
    
    
    //                         File::append($logPath, "📥 API Status: {$paymentStatus}" . PHP_EOL);
    
    //                         if ($paymentStatus === 'success') {
    
    //                             $report->update([
    //                                 'status' => 'success',
    //                                 'option1' => 'Payment counted and Payment Success (via checkstatus)',
    //                             ]);
    
    //                             File::append($logPath, "✅ SUCCESS Report {$report->id}" . PHP_EOL);
    
    //                             $callbackService = app(\App\Services\CallbackService::class);
    
    //                             $callbackService->merchantCallBackResponse(
    //                                 $user->callbackurl,
    //                                 'success',
    //                                 $report->txnid,
    //                                 $report->mytxnid,
    //                                 $report->amount,
    //                                 $report->apitxnid,
    //                                 now()->format('Y-m-d H:i:s')
    //                             );
    
    //                             File::append($logPath, "📨 SUCCESS Callback Sent for Report {$report->id}" . PHP_EOL);
    
    //                         } elseif ($paymentStatus === 'fail' || $paymentStatus === 'transaction details not available' || $paymentStatus ==='incomplete' || $paymentStatus ==='voided') {
    
    //                             $report->update([
    //                                 'option1' => 'TXN check (via checkstatus api)',
    //                                 // 'option1' => 'Done TXN check (via checkstatus api)',
    //                                 'option2' => $Txnreason1
    //                             ]);
    
    //                             File::append($logPath, "❌ Failed Report {$report->id}" . PHP_EOL);
    //                         }
    
    //                     } catch (\Exception $e) {
    
    //                         File::append(
    //                             $logPath,
    //                             "💥 Report Error {$report->id}: {$e->getMessage()}" . PHP_EOL
    //                         );
    //                     }
    //                 }
    //             }
    //         });
    
    //         File::append($logPath, "🏁 Scheduler Completed at " . now() . PHP_EOL . PHP_EOL);
    
    //     } catch (\Exception $e) {
    
    //         File::append($logPath, "💥 Scheduler Fatal Error: {$e->getMessage()}" . PHP_EOL);
    //     }
    
    // })->everyMinute();


////////////////////-----------------------------////////////////////////////

        // Airpay EBook Check Status
    //     $schedule->call(function () {
    
    //     $now = now();
    //     $logPath = storage_path('logs/airpay_checkstatus_test_' . $now->format('Y-m-d') . '.log');
    
    //     File::append($logPath, "🚀 [Airpay EBook Scheduler] Started at {$now}" . PHP_EOL);
    
    //     try {
    
    //         \App\Models\User::chunk(100, function ($users) use ($logPath, $now) {
    
    //             File::append($logPath, "👥 Processing user chunk count: " . count($users) . PHP_EOL);
    
    //             foreach ($users as $user) {
    
    //                 File::append($logPath, "➡️ User ID: {$user->id}, Callback URL: {$user->payin_callback}" . PHP_EOL);
    
    //                 $reports = \App\Models\Report::where('user_id', $user->id)
    //                     ->where('product', 'UPI')
    //                     ->where('status', 'failed')
    //                     ->where('option4', '352568')
    //                     ->where('option1', 'payin calculation is pending')
    //                     ->whereNotNull('apitxnid')
    //                     ->where('created_at', '>=', $now->copy()->subHours(24))
    //                     ->limit(20)
    //                     ->get();
                    
    //                 // $reports = \App\Models\Report::where('user_id', $user->id)
    //                 //     ->where('product', 'UPI')
    //                 //     ->where('status', 'failed')
    //                 //     ->where('option4', '352568')
    //                 //     ->where('option1', 'TXN check (via checkstatus api)')
    //                 //     ->whereNotNull('apitxnid')
    //                 //     ->where('created_at', '>=', $now->copy()->subHours(24))
    //                 //     ->limit(20)
    //                 //     ->get();
    
    //                 File::append($logPath, "📊 Reports Found: {$reports->count()} for User ID: {$user->id}" . PHP_EOL);
    
    //                 foreach ($reports as $report) {
    
    //                     File::append($logPath, "🧾 Processing Report ID: {$report->id}, MYTXNID: {$report->mytxnid}" . PHP_EOL);
    
    //                     try {
    
    //                         $url = "https://ebookspay.co.in/dashboard/api/checkstatus?ap_transactionid={$report->apitxnid}";
    
    //                         File::append($logPath, "🌐 API Call: {$url}" . PHP_EOL);
    
    //                         $response = \Http::timeout(15)->get($url);
    
    //                         if (!$response->successful()) {
    
    //                             File::append($logPath, "❌ API Failed for Report {$report->id}" . PHP_EOL);
    //                             continue;
    //                         }
    
    //                         $result = $response->json();
    
    //                         $paymentStatus = strtolower($result['data']['transaction_payment_status'] ?? '');
    //                         // $Txnreason = $result['data']['transaction_payment_status'] ?? $result['data']['reason'] ?? $result['data']['transaction_reason'] ??'' ;
    //                                                 $Txnreason = implode(' - ', array_filter([
    //                             $result['data']['transaction_payment_status'] ?? null,
    //                             $result['data']['reason'] ?? null,
    //                             $result['data']['transaction_reason'] ?? null
    //                         ]));
    
    //                         File::append($logPath, "📥 API Status: {$paymentStatus}" . PHP_EOL);
    
    //                         if ($paymentStatus === 'success') {
    
    //                             $report->update([
    //                                 'status' => 'success',
    //                                 'option1' => 'Payment counted and Payment Success (via checkstatus)',
    //                             ]);
    
    //                             File::append($logPath, "✅ SUCCESS Report {$report->id}" . PHP_EOL);
    
    //                             $callbackService = app(\App\Services\CallbackService::class);
    
    //                             $callbackService->merchantCallBackResponse(
    //                                 $user->payin_callback,
    //                                 'success',
    //                                 $report->txnid,
    //                                 $report->mytxnid,
    //                                 $report->amount,
    //                                 $report->apitxnid,
    //                                 now()->format('Y-m-d H:i:s')
    //                             );
    
    //                             File::append($logPath, "📨 SUCCESS Callback Sent for Report {$report->id}" . PHP_EOL);
    
    //                         } elseif ($paymentStatus === 'fail' || $paymentStatus === 'transaction details not available' || $paymentStatus ==='incomplete' || $paymentStatus ==='voided') {
    
    //                             $report->update([
    //                                 'option1' => 'TXN check (via checkstatus api)',
    //                                 // 'option1' => 'Done TXN check (via checkstatus api)',
    //                                 'option2' => $Txnreason
    //                             ]);
    
    //                             File::append($logPath, "❌ Failed Report {$report->id}" . PHP_EOL);
    //                         }
    
    //                     } catch (\Exception $e) {
    
    //                         File::append($logPath, "💥 Report Error {$report->id}: {$e->getMessage()}" . PHP_EOL);
    //                     }
    //                 }
    //             }
    //         });
    
    //         File::append($logPath, "🏁 [Airpay Scheduler] Completed at " . now() . PHP_EOL . PHP_EOL);
    
    //     } catch (\Exception $e) {
    
    //         File::append($logPath, "💥 Scheduler Fatal Error: {$e->getMessage()}" . PHP_EOL);
    //     }
    
    // })->everyMinute();
    
////////////////////-----------------------------////////////////////////////

        // Airpay Evah Check Status
        $schedule->call(function () {
    
        $now = now();
        $logPath = storage_path('logs/airpay_checkstatus_test_' . $now->format('Y-m-d') . '.log');
    
        File::append($logPath, "🚀 [Airpay Evah Scheduler] Started at {$now}" . PHP_EOL);
    
        try {
    
            \App\Models\User::chunk(100, function ($users) use ($logPath, $now) {
    
                File::append($logPath, "👥 Processing user chunk count: " . count($users) . PHP_EOL);
    
                foreach ($users as $user) {
    
                    File::append($logPath, "➡️ User ID: {$user->id}, Callback URL: {$user->payin_callback}" . PHP_EOL);
    
                    $reports = \App\Models\Report::where('user_id', $user->id)
                        ->where('product', 'UPI')
                        ->where('status', 'failed')
                        ->where('option4', '360909')
                        ->where('option1', 'payin calculation is pending')
                        ->whereNotNull('apitxnid')
                        ->where('created_at', '>=', $now->copy()->subHours(24))
                        ->limit(20)
                        ->get();
                    
                    // $reports = \App\Models\Report::where('user_id', $user->id)
                    //     ->where('product', 'UPI')
                    //     ->where('status', 'failed')
                    //     ->where('option4', '352568')
                    //     ->where('option1', 'TXN check (via checkstatus api)')
                    //     ->whereNotNull('apitxnid')
                    //     ->where('created_at', '>=', $now->copy()->subHours(24))
                    //     ->limit(20)
                    //     ->get();
    
                    File::append($logPath, "📊 Reports Found: {$reports->count()} for User ID: {$user->id}" . PHP_EOL);
    
                    foreach ($reports as $report) {
    
                        File::append($logPath, "🧾 Processing Report ID: {$report->id}, MYTXNID: {$report->mytxnid}" . PHP_EOL);
    
                        try {
    
                            $url = "https://evahfragrance.com/evah_backend/api/checkstatus?ap_transactionid={$report->apitxnid}";
    
                            File::append($logPath, "🌐 API Call: {$url}" . PHP_EOL);
    
                            $response = \Http::timeout(15)->get($url);
    
                            if (!$response->successful()) {
    
                                File::append($logPath, "❌ API Failed for Report {$report->id}" . PHP_EOL);
                                continue;
                            }
    
                            $result = $response->json();
    
                            $paymentStatus = strtolower($result['data']['transaction_payment_status'] ?? '');
                            // $Txnreason = $result['data']['transaction_payment_status'] ?? $result['data']['reason'] ?? $result['data']['transaction_reason'] ??'' ;
                                                    $Txnreason = implode(' - ', array_filter([
                                $result['data']['transaction_payment_status'] ?? null,
                                $result['data']['reason'] ?? null,
                                $result['data']['transaction_reason'] ?? null
                            ]));
    
                            File::append($logPath, "📥 API Status: {$paymentStatus}" . PHP_EOL);
    
                            if ($paymentStatus === 'success') {
    
                                $report->update([
                                    'status' => 'success',
                                    'option1' => 'Payment counted and Payment Success (via checkstatus)',
                                ]);
    
                                File::append($logPath, "✅ SUCCESS Report {$report->id}" . PHP_EOL);
    
                                $callbackService = app(\App\Services\CallbackService::class);
    
                                $callbackService->merchantCallBackResponse(
                                    $user->payin_callback,
                                    'success',
                                    $report->txnid,
                                    $report->mytxnid,
                                    $report->amount,
                                    $report->apitxnid,
                                    now()->format('Y-m-d H:i:s')
                                );
    
                                File::append($logPath, "📨 SUCCESS Callback Sent for Report {$report->id}" . PHP_EOL);
    
                            } elseif ($paymentStatus === 'fail' || $paymentStatus === 'transaction details not available' || $paymentStatus ==='incomplete' || $paymentStatus ==='voided') {
    
                                $report->update([
                                    'option1' => 'TXN check (via checkstatus api)',
                                    // 'option1' => 'Done TXN check (via checkstatus api)',
                                    'option2' => $Txnreason
                                ]);
    
                                File::append($logPath, "❌ Failed Report {$report->id}" . PHP_EOL);
                            }
    
                        } catch (\Exception $e) {
    
                            File::append($logPath, "💥 Report Error {$report->id}: {$e->getMessage()}" . PHP_EOL);
                        }
                    }
                }
            });
    
            File::append($logPath, "🏁 [Airpay Scheduler] Completed at " . now() . PHP_EOL . PHP_EOL);
    
        } catch (\Exception $e) {
    
            File::append($logPath, "💥 Scheduler Fatal Error: {$e->getMessage()}" . PHP_EOL);
        }
    
    })->everyMinute();
    
////////////////////-----------------------------////////////////////////////

        // Paytm EBook Check Status


    }


    /**
     * Register the commands for the application.
     */
    protected function commands(): void
    {
        $this->load(__DIR__.'/Commands');

        require base_path('routes/console.php');
    }
}
