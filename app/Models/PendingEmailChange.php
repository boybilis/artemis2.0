<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PendingEmailChange extends Model
{
    protected $guarded = [];
    protected $casts = ['expires_at' => 'datetime', 'last_sent_at' => 'datetime'];
}
