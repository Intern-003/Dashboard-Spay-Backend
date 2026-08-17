<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;
use App\Models\Report;

class PayoutRefundService
{
    public function todayRefundAmount($user)
    {
        $todayStart = now()->startOfDay();
        $todayEnd   = now()->endOfDay();
        $cutoff     = now()->subMinutes(30);

        $query = Report::where('product', 'payout')
            ->whereIn('status', ['pending', 'failed'])
            ->whereBetween('created_at', [$todayStart, $todayEnd])
            ->where('created_at', '<=', $cutoff);

        if ($user->role_type !== 'admin') {
            $query->where('user_id', $user->id);
        }

        return round(
            $query->sum(DB::raw('amount + profit')),
            2
        );
    }
}
