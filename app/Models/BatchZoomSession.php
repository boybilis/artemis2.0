<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class BatchZoomSession extends Model
{
    protected $fillable = ['batch_id', 'title', 'description', 'zoom_url', 'starts_at', 'ends_at', 'status', 'created_by'];
    protected $casts = ['starts_at' => 'datetime', 'ends_at' => 'datetime'];

    public function batch() { return $this->belongsTo(CourseBatch::class, 'batch_id'); }
    public function creator() { return $this->belongsTo(User::class, 'created_by'); }
}
