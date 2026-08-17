<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class GstAdvanceVerification extends Model
{
    protected $fillable = [
        'gstin',
        'financial_year',
        'request_id',
        'task_id',
        'group_id',
        'success',
        'response_code',
        'response_message',
        'billable',
        'request_timestamp',
        'response_timestamp',
        'raw_response',
        'http_status',
    ];

    protected $casts = [
        'success' => 'boolean',
        'raw_response' => 'array',
        'request_timestamp' => 'datetime',
        'response_timestamp' => 'datetime',
    ];
}