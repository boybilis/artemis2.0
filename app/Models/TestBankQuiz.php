<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class TestBankQuiz extends Model
{
    protected $fillable = [
        'test_bank_id', 'title', 'description', 'item_count', 'subject_ids',
        'randomize_questions', 'status', 'created_by', 'owner_user_id',
        'quiz_type', 'time_limit_minutes',
    ];

    protected function casts(): array
    {
        return [
            'subject_ids' => 'array',
            'randomize_questions' => 'boolean',
            'time_limit_minutes' => 'integer',
        ];
    }

    public function testBank() { return $this->belongsTo(TestBank::class); }
    public function creator() { return $this->belongsTo(User::class, 'created_by'); }
    public function owner() { return $this->belongsTo(User::class, 'owner_user_id'); }
    public function questions() { return $this->belongsToMany(TestBankQuestion::class, 'test_bank_quiz_questions')->withTimestamps(); }
    public function attempts() { return $this->hasMany(TestBankQuizAttempt::class); }
}
