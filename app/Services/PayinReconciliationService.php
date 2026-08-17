<?php

namespace App\Services;

use App\Models\Report;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class PayinReconciliationService
{
    public function reconcileToday(User $user)
    {
        $todayStart = now()->startOfDay();
        $todayEnd   = now()->endOfDay();
        
         Log::channel('reports')->info('🟢 Payin reconciliation started', [
            'user_id' => $user->id,
            'role'    => $user->role_type,
        ]);       

        $query = Report::where('status', 'success')
            ->where('product', 'UPI')
            ->where('description', 'Payment initiated')
            ->whereBetween('created_at', [$todayStart, $todayEnd]);

        if ($user->role_type !== 'admin') {
            $query->where('user_id', $user->id);
        }

        $sums = $query->selectRaw('
            SUM(payin_rolling_amount) as rolling,
            SUM(payin_amount) as payin,
            SUM(profit) as profit
        ')->first();

        $rolling = round($sums->rolling ?? 0, 2);
        $payin   = round($sums->payin ?? 0, 2);
        $profit  = round($sums->profit ?? 0, 2);
        
         Log::channel('reports')->info('📊 Payin calculated', [
            'rolling' => $rolling,
            'payin'   => $payin,
            'profit'  => $profit,
        ]);       

        if ($rolling <= 0 && $payin <= 0 && $profit <= 0) {
            return;
        }

        DB::transaction(function () use ($query, $user, $rolling, $payin, $profit) {

            // 1️⃣ Mark reports as counted (ANTI DOUBLE COUNT)
            $query->update([
                'description' => 'Payment counted'
            ]);

            Log::channel('reports')->info('🔒 Reports marked as Payment counted');

            Log::channel('reports')->info('💰 Wallet before update', [
                'rolling_amount' => $user->rolling_amount,
                'payin_wallet'   => $user->payin_wallet,
                'total_charges'  => $user->total_charges,
            ]);

            // 2️⃣ Update user wallets
            $user->increment('rolling_amount', $rolling);
            $user->increment('payin_wallet', $payin);
            $user->increment('total_charges', $profit);

            Log::info('Payin reconciled', [
                'user_id' => $user->id,
                'rolling' => $rolling,
                'payin'   => $payin,
                'profit'  => $profit,
            ]);
        });
    }
}
