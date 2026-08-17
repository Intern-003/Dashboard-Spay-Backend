<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class VideoKycSession extends Model
{
    protected $fillable = [
        'session_id','user_id','customer_name','customer_email','customer_mobile',
        'status','video_path','video_mime','video_size','started_at','ended_at','meta','purpose',
'response_url',
'redirect_url',
    ];

    protected $casts = [
        'meta' => 'array',
        'started_at' => 'datetime',
        'ended_at' => 'datetime',
    ];
}