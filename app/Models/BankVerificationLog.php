<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class BankVerificationLog extends Model
{
    protected $fillable = [
        'account_number','ifsc','name_to_match',
        'request_id','task_id','group_id',
        'success','response_code','response_message',
        'billable','reason_code','reason_message',
        'beneficiary_name','verification_status',
        'name_match_score','is_penny_drop',
        'request_timestamp','response_timestamp',
        'http_status','raw_response'
    ];

    protected $casts = [
        'success' => 'boolean',
        'is_penny_drop' => 'boolean',
        'raw_response' => 'array',
        'request_timestamp' => 'datetime',
        'response_timestamp' => 'datetime',
    ];
}