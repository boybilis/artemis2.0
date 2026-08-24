<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Subtopic extends Model
{
    use HasFactory;

    protected $fillable = [
        'topic_id',
        'content_type',
        'title',
        'instructions',
        'zoom_url',
        'zoom_description',
        'zoom_starts_at',
        'zoom_ends_at',
        'maximum_attempts',
        'passing_percentage',
        'assessment_time_limit_minutes',
        'assessment_question_count',
        'sort_order',
        'video_url',
        'video_path',
        'video_filename',
        'documentation_path',
        'documentation_filename',
        'status',
    ];

    protected $casts = ['zoom_starts_at' => 'datetime', 'zoom_ends_at' => 'datetime'];

    public function topic()
    {
        return $this->belongsTo(Topic::class);
    }

    public function questions()
    {
        return $this->hasMany(QuizQuestion::class);
    }

    public function attempts()
    {
        return $this->hasMany(QuizAttempt::class);
    }
}
