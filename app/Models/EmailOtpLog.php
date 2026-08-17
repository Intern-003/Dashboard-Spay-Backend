<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class EmailOtpLog extends Model
{
    protected $fillable = [
        'email','name',
        'otp','otp_status',
        'subject','message',
        'status','api_message','provider_status','provider_message','message_id',
        'http_status','raw_response'
    ];

    protected $casts = [
        'status' => 'boolean',
        'raw_response' => 'array',
    ];
}