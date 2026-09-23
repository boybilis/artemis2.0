<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class TestBank extends Model
{
    protected $fillable = ['course_id', 'title', 'code', 'description', 'price', 'usd_price', 'starts_at', 'ends_at', 'access_days', 'status', 'created_by'];
    protected $casts = ['price'=>'decimal:2', 'usd_price'=>'decimal:2', 'starts_at'=>'datetime', 'ends_at'=>'datetime', 'access_days'=>'integer'];

    public function enrollments() { return $this->hasMany(TestBankEnrollment::class); }
    public function course() { return $this->belongsTo(Course::class); }
    public function creator() { return $this->belongsTo(User::class, 'created_by'); }
}
