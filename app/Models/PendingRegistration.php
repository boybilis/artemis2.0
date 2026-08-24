<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PendingRegistration extends Model
{
    protected $guarded = [];
    protected $casts = ['registration_data'=>'encrypted:array','expires_at'=>'datetime','last_sent_at'=>'datetime'];
}
