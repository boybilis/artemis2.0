<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Subject extends Model
{
    use HasFactory;

    protected $fillable = [
        'course_id', 'subject_code', 'title', 'description', 'start_date', 'end_date',
        'schedule_day', 'start_time', 'end_time', 'modality', 'sort_order', 'status',
    ];

    protected $casts = [
        'start_date' => 'date',
        'end_date' => 'date',
    ];

    public function course()
    {
        return $this->belongsTo(Course::class);
    }

    public function topics()
    {
        return $this->hasMany(Topic::class)->orderBy('sort_order');
    }
}
