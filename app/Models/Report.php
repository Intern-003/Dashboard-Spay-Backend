<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Carbon\Carbon;

class Report extends Model
{
    use HasFactory;
    
    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
     
    protected $table = "reports";
     
    protected $fillable = [
        'user_id',
        'mobile',
        'amount',
        'charge',
        'profit',
        'gst',
        'tds',
        'apitxnid',
        'glide_uiwidget_sessionid',
        'txnid',
        'payid',
        'refno',
        'description',
        'remark',
        'option1',
        'option2',
        'option3',
        'option4',
        'status',
        'payment_platform',
        'payout_amount',
        'payout_mode',
        'payin_opening',
        'payin_closing',
        'payout_opening_balance',
        'payout_closing_balance',
        'payin_amount',
        'transaction_type',
        'product',
        'mytxnid',
        'aepstype',
        'payee_vpa',
        'payer_vpa',
        'payer_mobile',
        'payer_acc_no',
        'payer_ifsc',
        'commission_inc_gst',
        'bank_other_charges',
        'payin_rolling_amount',
        'payer_name',
        'payer_email',
        'airpay_credential',
        'chargeback_status',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
     
    protected $casts = [
        'amount'              => 'decimal:2',
        'charge'              => 'decimal:2',
        'profit'              => 'decimal:2',
        'gst'                 => 'decimal:2',
        'tds'                 => 'decimal:2',
        'payout_amount'       => 'decimal:2',
        'payin_amount'        => 'decimal:2',
        'commission_inc_gst'  => 'decimal:2',
        'bank_other_charges'  => 'decimal:2',
    ];

    /**
     * Relationships
     */
    public function user()
    {
        return $this->belongsTo(User::class);
    }
    
        // ✅ THIS WILL REPLACE created_at & updated_at
    protected function serializeDate(\DateTimeInterface $date)
    {
        return Carbon::instance($date)
            ->timezone('Asia/Kolkata')
            ->format('d M Y, h:i A');
    }
 
}
