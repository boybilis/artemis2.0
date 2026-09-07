<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Voucher extends Model
{
    use HasFactory;

    protected $fillable = ['batch_id', 'code', 'price', 'duration_days', 'used', 'used_by', 'used_at', 'redeemed_at', 'status', 'payment_provider', 'provider_checkout_id', 'provider_payment_id'];

    public function batch() { return $this->belongsTo(CourseBatch::class, 'batch_id'); }

    public function user()
    {
        return $this->belongsTo(User::class, 'used_by');
    }

    public function enrollment() { return $this->hasOne(CourseEnrollment::class); }
}
