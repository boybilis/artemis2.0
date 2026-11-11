<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CourseBatch extends Model
{
    public const CLASS_DAYS = ['Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday', 'Sunday'];
    public const COURSE_TYPES = [
        'complete' => 'Complete Review Course',
        'comprehensive' => 'Comprehensive Review Course',
        'final_coaching' => 'Final Coaching Review Course',
        'mentoring' => 'Mentoring Course',
    ];


    public function filterSubjects($query)
    {
        $type = $this->course_type ?? ($this->includes_intensive_final_coaching ? 'complete' : 'comprehensive');
        return match ($type) {
            'final_coaching' => $query->where('is_intensive_final_coaching', true),
            'mentoring' => $query->where('is_mentoring', true),
            'comprehensive' => $query->where('is_intensive_final_coaching', false),
            default => $query,
        };
    }

    public function includesUnassignedTopics(): bool
    {
        return !in_array($this->course_type, ['final_coaching', 'mentoring'], true);
    }
    protected $fillable = ['course_id', 'name', 'code', 'description', 'course_type', 'includes_intensive_final_coaching', 'starts_at', 'ends_at', 'schedule_day', 'start_time', 'end_time', 'class_sessions', 'modality', 'price', 'usd_price', 'capacity', 'status', 'created_by'];

    protected $casts = ['starts_at' => 'datetime', 'ends_at' => 'datetime', 'price' => 'decimal:2', 'usd_price' => 'decimal:2', 'includes_intensive_final_coaching' => 'boolean', 'class_sessions' => 'array'];

    public function weeklySessions(): array
    {
        if ($this->class_sessions !== null) return $this->class_sessions;
        $schedule = strtolower(trim((string) $this->schedule_day));
        $tokens = match ($schedule) {
            'mwf' => ['mon', 'wed', 'fri'],
            'tth', 'tts' => $schedule === 'tth' ? ['tue', 'thu'] : ['tue', 'thu', 'sat'],
            default => preg_split('/[^a-z]+/', $schedule, -1, PREG_SPLIT_NO_EMPTY),
        };
        return array_values(array_map(fn ($day) => ['day'=>$day, 'start_time'=>substr((string) $this->start_time, 0, 5), 'end_time'=>substr((string) $this->end_time, 0, 5)],
            array_filter(self::CLASS_DAYS, fn ($day) => in_array(strtolower($day), $tokens, true) || in_array(strtolower(substr($day, 0, 3)), $tokens, true))));
    }

    public function course() { return $this->belongsTo(Course::class); }
    public function courses() { return $this->belongsToMany(Course::class, 'course_batch_courses', 'batch_id', 'course_id')->withTimestamps(); }
    public function enrollments() { return $this->hasMany(CourseEnrollment::class, 'batch_id'); }
    public function instructors() { return $this->belongsToMany(User::class, 'course_batch_instructors')->withTimestamps(); }
    public function zoomSessions() { return $this->hasMany(BatchZoomSession::class, 'batch_id')->orderBy('starts_at'); }

    public function scopeForCourse($query, int $courseId)
    {
        return $query->whereHas('courses', fn ($courses) => $courses->where('courses.id', $courseId));
    }

    public function scopeAvailable($query)
    {
        return $query->where('status', 'open')
            ->where(fn ($q) => $q->whereNull('ends_at')->orWhere('ends_at', '>', now()))
            ->where(fn ($q) => $q->whereNull('capacity')->orWhereRaw('(select count(*) from course_enrollments where course_enrollments.batch_id = course_batches.id and course_enrollments.status = ?) < course_batches.capacity', ['active']));
    }

    protected static function booted(): void
    {
        static::creating(function (self $batch) {
            if (!isset($batch->course_type)) {
                $batch->course_type = $batch->includes_intensive_final_coaching ? 'complete' : 'comprehensive';
            }
        });
        // Keep legacy callers and older tests compatible while the application
        // transitions from one course per batch to the batch/course pivot.
        static::saved(function (CourseBatch $batch) {
            if ($batch->course_id) {
                $batch->courses()->syncWithoutDetaching([(int) $batch->course_id]);
            }
        });
    }
}
