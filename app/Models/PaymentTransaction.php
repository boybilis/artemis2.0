<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PaymentTransaction extends Model
{
    protected $fillable = [
        'user_id', 'batch_id', 'reference', 'amount', 'currency', 'provider',
        'provider_checkout_id', 'provider_payment_id', 'status', 'paid_at',
    ];

    protected $casts = ['amount' => 'decimal:2', 'paid_at' => 'datetime'];

    public function user() { return $this->belongsTo(User::class); }
    public function batch() { return $this->belongsTo(CourseBatch::class); }
}
