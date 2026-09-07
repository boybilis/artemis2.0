<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Voucher extends Model
{
    use HasFactory;

    protected $fillable = ['batch_id', 'code', 'price', 'duration_days', 'used', 'used_by', 'used_at', 'redeemed_at', 'status', 'payment_provider', 'provider_checkout_id', 'provider_payment_id'];

    protected $casts = [
        'used' => 'boolean',
        'used_at' => 'datetime',
        'redeemed_at' => 'datetime',
        'price' => 'decimal:2',
    ];

    public function batch() { return $this->belongsTo(CourseBatch::class, 'batch_id'); }

    public function user()
    {
        return $this->belongsTo(User::class, 'used_by');
    }

    public function enrollment() { return $this->hasOne(CourseEnrollment::class); }

    public function statusLabel(): string
    {
        if ($this->used) return $this->payment_provider === 'paymongo' ? 'Paid / Enrolled' : 'Redeemed';

        return match ($this->status) {
            'pending_payment' => 'Awaiting Payment',
            'payment_creation_failed' => 'Checkout Failed',
            'payment_configuration_error' => 'Configuration Error',
            default => 'Active Code',
        };
    }

    public function statusCssClass(): string
    {
        if ($this->used) return 'success';

        return match ($this->status) {
            'pending_payment' => 'warning',
            'payment_creation_failed', 'payment_configuration_error' => 'danger',
            default => 'info',
        };
    }

    public function paymentMethodLabel(): string
    {
        return $this->payment_provider === 'paymongo' ? 'PayMongo QR Ph' : 'Enrollment Code';
    }
}
