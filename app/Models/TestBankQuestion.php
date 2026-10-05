<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class TestBankQuestion extends Model
{
    protected $fillable = [
        'test_bank_id', 'course_id', 'subject_id', 'question', 'options',
        'correct_answer', 'points', 'rationale', 'image_path', 'image_filename',
        'status', 'created_by',
    ];

    protected function casts(): array
    {
        return ['options' => 'array', 'points' => 'decimal:2'];
    }

    public function testBank() { return $this->belongsTo(TestBank::class); }
    public function course() { return $this->belongsTo(Course::class); }
    public function subject() { return $this->belongsTo(Subject::class); }
    public function creator() { return $this->belongsTo(User::class, 'created_by'); }
    public function quizzes() { return $this->belongsToMany(TestBankQuiz::class, 'test_bank_quiz_questions')->withTimestamps(); }
}
