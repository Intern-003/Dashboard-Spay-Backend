<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class MerchantKycDocument extends Model
{
    protected $fillable = [
        'user_id', 'document_key', 'document_name',
        'status', 'remarks', 'verified_at', 'verified_by',
    ];
}