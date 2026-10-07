<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Casts\Attribute;
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
        return ['points' => 'decimal:2'];
    }

    protected function options(): Attribute
    {
        return Attribute::make(
            get: fn ($value) => $this->normalizeOptions($value),
            set: fn ($value) => json_encode($this->normalizeOptions($value), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
        );
    }

    private function normalizeOptions(mixed $value): array
    {
        for ($attempt = 0; $attempt < 2 && is_string($value); $attempt++) {
            $decoded = json_decode($value, true);
            if (json_last_error() !== JSON_ERROR_NONE) break;
            $value = $decoded;
        }

        return is_array($value) ? array_values($value) : [];
    }

    public function testBank() { return $this->belongsTo(TestBank::class); }
    public function course() { return $this->belongsTo(Course::class); }
    public function subject() { return $this->belongsTo(Subject::class); }
    public function subjects() { return $this->belongsToMany(Subject::class, 'test_bank_question_subject')->withTimestamps(); }
    public function creator() { return $this->belongsTo(User::class, 'created_by'); }
    public function quizzes() { return $this->belongsToMany(TestBankQuiz::class, 'test_bank_quiz_questions')->withTimestamps(); }
}
