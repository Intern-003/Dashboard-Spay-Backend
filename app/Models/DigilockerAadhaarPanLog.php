<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class DigilockerAadhaarPanLog extends Model
{
    protected $fillable = [
        'purpose','response_url','redirect_url',
        'success','api_message',
        'request_id','webhook_security_key','expires_at','sdk_url',
        'http_status','raw_response',
    ];

    protected $casts = [
        'success' => 'boolean',
        'expires_at' => 'datetime',
        'raw_response' => 'array',
    ];
}