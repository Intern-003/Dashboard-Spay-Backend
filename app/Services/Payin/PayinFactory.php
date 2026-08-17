<?php

namespace App\Services\Payin;

use App\Services\Payin\Contracts\PayinProviderInterface;
use App\Services\Payin\Providers\AirpayAllProvider;
use App\Services\Payin\Providers\AirpayProvider;
use App\Services\Payin\Providers\EvahProvider;
use App\Services\Payin\Providers\OmishaProvider;
use App\Services\Payin\Providers\EbookProvider;
use App\Services\Payin\Providers\SoulfulProvider;
use App\Services\Payin\Providers\NxtProvider;
use App\Services\Payin\Providers\E2payProvider;
use App\Services\Payin\Providers\AirOmishaProvider;
use App\Services\Payin\Providers\BulkPeProvider;
use App\Services\Payin\Providers\PaytmProvider;
use App\Services\Payin\Providers\NttProvider;
use App\Services\Payin\Providers\RazorpayProvider;
use App\Services\Payin\Providers\VRazorProvider;
use App\Services\Payin\Providers\RiseXpayProvider;
use App\Services\Payin\Providers\ShaymavenueProvider;


use InvalidArgumentException;

class PayinFactory
{
    public function __construct(private PayinService $payinService) {}

    public function make(string $providerName): PayinProviderInterface
    {
        $providerName = trim($providerName);

        return match ($providerName) {
            'Airpay_all' => new AirpayAllProvider($this->payinService),
            'Airpay'     => new AirpayProvider($this->payinService),
            'Evah'       => new EvahProvider($this->payinService),
            'Omisha'     => new OmishaProvider($this->payinService),
            'Ebook'      => new EbookProvider($this->payinService),
            'Soulful'    => new SoulfulProvider($this->payinService),
            'nxt'        => new NxtProvider($this->payinService),
            'E2PAY'      => new E2payProvider($this->payinService),
            'AirOmisha'  => new AirOmishaProvider($this->payinService),
            'BulkPe'     => new BulkPeProvider($this->payinService),
            'Paytm'      => new PaytmProvider($this->payinService),
            'NTT'        => new NttProvider($this->payinService),
            'Razorpay'   => new RazorpayProvider($this->payinService),
            'VRazor'     => new VRazorProvider($this->payinService),
            'Risexpay'   => new RiseXpayProvider($this->payinService),
            'Shaymavenue'   => new ShaymavenueProvider($this->payinService),
            default      => throw new InvalidArgumentException("Unsupported provider: {$providerName}")
        };
    }
}