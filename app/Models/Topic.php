<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Topic extends Model
{
    use HasFactory;

    protected $fillable = [
        'title',
        'sort_order',
        'status',
        'description',
        'video_url',
        'videos',
        'documentation_path',
        'documentation_filename',
        'course_id',
        'subject_id',
        'quiz_passing_percentage',
        'assessment_time_limit_minutes',
        'assessment_question_count',
    ];

    protected $casts = [
        'videos' => 'array',
    ];

    public function lessons()
    {
        return $this->hasMany(Lesson::class)->orderBy('sort_order');
    }

    public function subtopics()
    {
        return $this->hasMany(Subtopic::class)->orderBy('sort_order');
    }

    public function quizQuestions()
    {
        return $this->hasMany(QuizQuestion::class);
    }

    public function course()
    {
        return $this->belongsTo(Course::class);
    }

    public function subject()
    {
        return $this->belongsTo(Subject::class);
    }
}
