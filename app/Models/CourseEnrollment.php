<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CourseEnrollment extends Model
{
    protected $fillable = ['user_id', 'batch_id', 'voucher_id', 'status', 'enrolled_at', 'expires_at'];

    protected $casts = ['enrolled_at' => 'datetime', 'expires_at' => 'datetime'];

    public function user() { return $this->belongsTo(User::class); }
    public function batch() { return $this->belongsTo(CourseBatch::class, 'batch_id'); }
    public function voucher() { return $this->belongsTo(Voucher::class); }

    public function isActive(): bool
    {
        return $this->status === 'active' && (!$this->expires_at || $this->expires_at->isFuture());
    }
}
