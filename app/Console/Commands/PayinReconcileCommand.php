<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\User;
use App\Services\PayinReconciliationService;

class PayinReconcileCommand extends Command
{
    protected $signature = 'payin:reconcile';
    protected $description = 'Reconcile daily payins and update wallets';

    public function handle()
    {
        User::chunk(50, function ($users) {
            foreach ($users as $user) {
                app(PayinReconciliationService::class)
                    ->reconcileToday($user);
            }
        });

        $this->info('Payin reconciliation completed');
    }
}
