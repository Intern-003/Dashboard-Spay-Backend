<?php

namespace App\Services\PayoutProviders;

class PayoutProviderFactory
{
    public static function make($providerName)
    {
        return match ($providerName) {
            'cashfree'   => new CashfreeProvider(),
            'spay'       => new SpayProvider(),
            'nxt'        => new NxtProvider(),
            'E2pay'      => new E2payProvider(),
            'BridgMoney' => new BridgMoneyProvider(),
            'Shaymavenue' => new ShaymavenueProvider(),
            default      => null,
        };
    }
}
