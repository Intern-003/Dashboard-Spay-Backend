<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class OtpCustomMobileLog extends Model
{
    protected $fillable = [
    'user',
    'sender',
    'templateid',
    'mobile',
    'otp',            // ✅ add
    'otp_status',     // ✅ add
    'message',
    'status',
    'api_message',
    'code',
    'info',
    'uid',
    'api_status',
    'http_status',
    'raw_response'
];

    protected $casts = [
        'status' => 'boolean',
        'raw_response' => 'array',
    ];
}