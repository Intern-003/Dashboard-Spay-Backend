<?php

namespace App\Console\Commands;

use App\Models\Report;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use App\Services\CallbackService;

class AutoFailPayinCommand extends Command
{
    protected $signature = 'payin:auto-fail';
    protected $description = 'Auto fail UPI payins after timeout and send callbacks';

public function handle()
{
    $timeout = now()->subMinutes(20);

    Log::channel('reports')->info('⏱ Auto-fail payin started');

    Report::where('product','UPI')
        ->where('status','initiated')
        ->where('created_at','<=',$timeout)
        ->chunkById(100,function($reports){

            foreach($reports as $report){

                Log::channel('reports')->info('🧾 Processing report', [
                    'report_id'=>$report->id,
                    'txnid'=>$report->txnid
                ]);

                DB::transaction(function() use ($report){

                    $report->update([
                        'status'=>'failed',
                        'description'=>'Payment Failed (auto-timeout)'
                    ]);

                    Log::channel('reports')->info('\Report auto-failed', [
                        'report_id'=>$report->id
                    ]);

                    $user = $report->user;
                    if(!$user || !$user->callbackurl){
                        Log::channel('reports')->warning(' Callback skipped');
                        return;
                    }

                    app(\App\Services\CallbackService::class)
                        ->merchantCallBackResponse(
                            $user->callbackurl,
                            'failed',
                            $report->txnid,
                            $report->mytxnid,
                            $report->amount,
                            $report->apitxnid,
                            now()->format('Y-m-d H:i:s')
                        );

                    Log::channel('reports')->info(' Callback sent', [
                        'report_id'=>$report->id
                    ]);
                });
            }
        });

    $this->info('Auto fail payin completed');
}

}
