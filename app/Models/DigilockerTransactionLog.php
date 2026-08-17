<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class DigilockerTransactionLog extends Model
{
    protected $fillable = [
        'request_id',
        'status',
        'response_code',
        'response_message',
        'billable',
        'provider_created_at',
        'provider_updated_at',
        'raw_response',
        'http_status',
    ];

    protected $casts = [
        'billable'            => 'boolean',
        'provider_created_at' => 'datetime',
        'provider_updated_at' => 'datetime',
        'raw_response'        => 'array',
    ];
}