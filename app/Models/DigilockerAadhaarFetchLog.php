<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class DigilockerAadhaarFetchLog extends Model
{
    protected $fillable = [
        'request_id',
        'success',
        'status',
        'document_name',
        'issuer',
        'issue_date',
        'fetched_at',
        'name',
        'dob',
        'gender',
        'uid',
        'photo_base64',
        'raw_response',
        'http_status',
    ];

    protected $casts = [
        'success' => 'boolean',
        'issue_date' => 'datetime',
        'fetched_at' => 'datetime',
        'raw_response' => 'array',
    ];
}