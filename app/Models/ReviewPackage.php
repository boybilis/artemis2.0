<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ReviewPackage extends Model
{
    protected $fillable = ['name', 'description', 'price', 'starts_at', 'class_type', 'status', 'created_by'];
    protected $casts = ['price' => 'decimal:2', 'starts_at' => 'date'];

    public function batches()
    {
        return $this->belongsToMany(CourseBatch::class, 'review_package_batches', 'review_package_id', 'batch_id')->withTimestamps();
    }

    public function creator() { return $this->belongsTo(User::class, 'created_by'); }
    public function transactions() { return $this->hasMany(PaymentTransaction::class); }

    public function scopeAvailable($query)
    {
        return $query->where('status', 'active')->whereHas('batches', fn ($batch) => $batch->available());
    }
}
