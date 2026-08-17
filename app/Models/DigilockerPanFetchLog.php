<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class DigilockerPanFetchLog extends Model
{
    protected $fillable = [
        'request_id',
        'success',
        'status',
        'document_name',
        'issuer',
        'issue_date',
        'fetched_at',
        'certificate_number',
        'pan_number',
        'holder_name',
        'holder_dob',
        'pdf_base64',
        'raw_response',
        'http_status',
    ];

    protected $casts = [
        'success'      => 'boolean',
        'issue_date'   => 'datetime',
        'fetched_at'   => 'datetime',
        'raw_response' => 'array',
    ];
}