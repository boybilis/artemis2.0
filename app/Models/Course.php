<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Course extends Model
{
    use HasFactory;

    protected $guarded = [];

    protected $casts = [
        'is_published' => 'boolean',
    ];

    public function scopeAvailable($query)
    {
        return $query->where('is_published', true)
            ->where('approval_status', 'approved');
    }

    public function isAvailable(): bool
    {
        return $this->is_published && $this->approval_status === 'approved';
    }

    public function topics()
    {
        return $this->hasMany(Topic::class)->orderBy('sort_order');
    }

    public function subjects()
    {
        return $this->hasMany(Subject::class)->orderBy('sort_order');
    }

    public function userProgress()
    {
        return $this->hasMany(UserProgress::class);
    }

    public function certificates()
    {
        return $this->hasMany(Certificate::class);
    }

    public function quizAttempts()
    {
        return $this->hasMany(QuizAttempt::class);
    }

    public function enrollments()
    {
        return $this->hasManyThrough(CourseEnrollment::class, CourseBatch::class, 'course_id', 'batch_id');
    }

    public function batches() { return $this->hasMany(CourseBatch::class); }

    public function creator() { return $this->belongsTo(User::class, 'created_by'); }
}
