<?php

namespace App\Services;

use App\Repositories\GlideTransactionRepository;
use Exception;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\DB;

class GlideTransactionService
{
    protected $repo;

    public function __construct(GlideTransactionRepository $repo)
    {
        $this->repo = $repo;
    }

    public function handleWebhook(array $payload)
    {
        $data = $this->mapWebhookToDb($payload);
        
        return $this->repo->store($data);
    }
    
    public function generateRandomIds($count = 1, $length = 20) {
        $ids = [];
        for ($i = 0; $i < $count; $i++) {
            $ids[] = Str::random($length);
        }
        return $ids;
        
        // $metadata = [
        //     'orderId' => "werwerwer",
        //     'userId'  => 22,
        // ];
        
        // $metaToken = $this->processMetaData('encrypt', $metadata);
        // //dd($metaToken);
        
        // dd($this->decryptEncryptedToken($metaToken["encrypted"]));
    }

    
    public function processMetaData($action, $data)
    {
        try {
            if ($action === 'encrypt') {
                return $this->generateEncryptedToken($data, 25);// AES-256
            }

            if ($action === 'decrypt') {
                return $this->decryptEncryptedToken($data);
            }

            throw new Exception("Invalid action. Use 'encrypt' or 'decrypt'.");
        } catch (\Exception $e) {
            return "Error: " . $e->getMessage();
        }
    }
    
    /**
     * Generate short encrypted token (20–30 chars)
     *
     * @param array $data
     * @param int $length
     * @return string
     */
    function generateEncryptedToken(array $data, $length = 25)
    {
        // 1. Encrypt data (AES-256)
        $encrypted = Crypt::encrypt($data);

        // 2. Hash it (SHA256) and convert to base62
        $hash = base_convert(substr(hash('sha256', $encrypted), 0, 30), 16, 36);

        // 3. Trim to required length
        return ["encrypted" => $encrypted, "shorter" => substr($hash, 0, $length)];
    }
    
    /**
     * Decrypt original data from encrypted string
     *
     * @param string $encrypted
     * @return array|string
     */
    function decryptEncryptedToken($encrypted)
    {
        try {
            return Crypt::decrypt($encrypted);
        } catch (\Exception $e) {
            return "Error: " . $e->getMessage();
        }
    }


    private function mapWebhookToDb(array $data)
    {
        $p = $data['payload'];

        return [
            'webhook_id' => $data['webhookId'] ?? null,
            'entity_id' => $data['entityId'] ?? null,
            'session_id' => $p['sessionId'] ?? null,

            'created_at_utc' => $p['createdAt'] ?? null,
            'expires_at_utc' => $p['expiresAt'] ?? null,
            'expired' => $p['expired'] ?? false,

            'payment_status' => $p['paymentStatus'] ?? null,
            'payment_chain_id' => $p['paymentChainId'] ?? null,
            'payment_currency' => $p['paymentCurrency'] ?? null,
            'payment_currency_symbol' => $p['paymentCurrencySymbol'] ?? null,
            'payment_currency_tier' => $p['paymentCurrencyTier'] ?? null,

            'payment_amount' => $p['paymentAmount'] ?? null,
            'payment_amount_usd' => $p['paymentAmountUSD'] ?? null,

            'payer_account' => $p['payerAccount'] ?? null,
            'payer_wallet_address' => $p['payerWalletAddress'] ?? null,
            'payer_email' => $p['payerEmail'] ?? null,
            'enable_refund_emails' => $p['enableRefundEmails'] ?? false,

            'payment_action' => $p['paymentAction'] ?? null,
            'payment_tx_hash' => $p['paymentTransactionHash'] ?? null,
            'payment_tx_url' => $p['paymentTransactionUrl'] ?? null,

            'unsigned_tx_chainid' => $p['unsignedTransaction']['chainId'] ?? null,
            'unsigned_tx_to' => $p['unsignedTransaction']['to'] ?? null,
            'unsigned_tx_value' => $p['unsignedTransaction']['value'] ?? null,

            'sponsored_tx_chainid' => $p['sponsoredTransactionChainId'] ?? null,
            'sponsored_tx_status' => $p['sponsoredTransactionStatus'] ?? null,
            'sponsored_tx_hash' => $p['sponsoredTransactionHash'] ?? null,
            'sponsored_tx_url' => $p['sponsoredTransactionUrl'] ?? null,
            'sponsored_tx_raw' => $p['sponsoredTransaction'] ?? null,

            'sponsored_tx_amount' => $p['sponsoredTransactionAmount'] ?? null,
            'sponsored_tx_currency' => $p['sponsoredTransactionCurrency'] ?? null,
            'sponsored_tx_currency_symbol' => $p['sponsoredTransactionCurrencySymbol'] ?? null,
            'sponsored_tx_amount_usd' => $p['sponsoredTransactionAmountUSD'] ?? null,

            'gas_refuel_amount' => $p['gasRefuelAmount'] ?? null,
            'gas_refuel_amount_usd' => $p['gasRefuelUSD'] ?? null,
            'gas_refuel_tx_status' => $p['gasRefuelTransactionStatus'] ?? null,
            'gas_refuel_tx_hash' => $p['gasRefuelTransactionHash'] ?? null,
            'gas_refuel_tx_url' => $p['gasRefuelTransactionUrl'] ?? null,

            'gas_fee_usd' => $p['gasFeeUSD'] ?? null,
            'service_fee_usd' => $p['serviceFeeUSD'] ?? null,
            'total_fee_usd' => $p['totalFeeUSD'] ?? null,

            'eta_seconds' => $p['etaInSeconds'] ?? null,
            'metadata' => $p['metadata'] ?? null,
            'allow_arbitrary_deposit' => $p['allowArbitraryDeposit'] ?? false,

            'actual_payment_chain_id' => $p['actualPaymentChainId'] ?? null,
            'actual_payment_currency' => $p['actualPaymentCurrency'] ?? null,
            'actual_payment_currency_symbol' => $p['actualPaymentCurrencySymbol'] ?? null,
            'actual_payment_currency_tier' => $p['actualPaymentCurrencyTier'] ?? null,

            'actual_payment_amount' => $p['actualPaymentAmount'] ?? null,
            'actual_payment_amount_usd' => $p['actualPaymentAmountUSD'] ?? null
        ];
    }
}
