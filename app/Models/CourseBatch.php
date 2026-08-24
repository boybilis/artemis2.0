<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CourseBatch extends Model
{
    protected $fillable = ['course_id', 'name', 'code', 'description', 'starts_at', 'ends_at', 'schedule_day', 'start_time', 'end_time', 'modality', 'price', 'usd_price', 'capacity', 'status', 'created_by'];

    protected $casts = ['starts_at' => 'datetime', 'ends_at' => 'datetime', 'price' => 'decimal:2', 'usd_price' => 'decimal:2'];

    public function course() { return $this->belongsTo(Course::class); }
    public function enrollments() { return $this->hasMany(CourseEnrollment::class, 'batch_id'); }
    public function instructors() { return $this->belongsToMany(User::class, 'course_batch_instructors')->withTimestamps(); }

    public function scopeAvailable($query)
    {
        return $query->where('status', 'open')
            ->where(fn ($q) => $q->whereNull('ends_at')->orWhere('ends_at', '>', now()))
            ->where(fn ($q) => $q->whereNull('capacity')->orWhereRaw('(select count(*) from course_enrollments where course_enrollments.batch_id = course_batches.id and course_enrollments.status = ?) < course_batches.capacity', ['active']));
    }
}
