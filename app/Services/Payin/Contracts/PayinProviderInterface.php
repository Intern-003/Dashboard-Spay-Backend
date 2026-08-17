<?php

namespace App\Services\Payin\Contracts;

use App\Models\User;
use App\Models\Report;

interface PayinProviderInterface
{
    /**
     * Generate QR (or initiate payin) and update report if needed.
     * Must return standardized array response.
     */
    public function generateUpiQr(array $payload, User $user, Report $report): array;
}