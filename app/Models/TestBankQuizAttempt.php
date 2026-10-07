<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class TestBankQuizAttempt extends Model
{
    protected $fillable = [
        'user_id', 'test_bank_id', 'test_bank_quiz_id', 'score', 'total',
        'points_earned', 'points_possible', 'passed', 'review_data',
    ];

    protected function casts(): array
    {
        return [
            'score' => 'integer',
            'total' => 'integer',
            'points_earned' => 'decimal:2',
            'points_possible' => 'decimal:2',
            'passed' => 'boolean',
            'review_data' => 'array',
        ];
    }

    public function user() { return $this->belongsTo(User::class); }
    public function testBank() { return $this->belongsTo(TestBank::class); }
    public function quiz() { return $this->belongsTo(TestBankQuiz::class, 'test_bank_quiz_id'); }
}
