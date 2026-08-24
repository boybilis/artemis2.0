<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class QuizAttempt extends Model
{
    use HasFactory;

    protected $fillable = ['user_id', 'topic_id', 'subtopic_id', 'course_id', 'batch_id', 'assessment_type', 'score', 'total', 'points_earned', 'points_possible', 'passed', 'review_data'];

    protected $casts = ['review_data' => 'array', 'passed' => 'boolean'];

    public function batch() { return $this->belongsTo(CourseBatch::class, 'batch_id'); }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function topic()
    {
        return $this->belongsTo(Topic::class);
    }

    public function subtopic()
    {
        return $this->belongsTo(Subtopic::class);
    }
}
