<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class TestBankQuiz extends Model
{
    protected $fillable = [
        'test_bank_id', 'title', 'description', 'item_count', 'subject_ids',
        'randomize_questions', 'status', 'created_by',
    ];

    protected function casts(): array
    {
        return ['subject_ids' => 'array', 'randomize_questions' => 'boolean'];
    }

    public function testBank() { return $this->belongsTo(TestBank::class); }
    public function creator() { return $this->belongsTo(User::class, 'created_by'); }
    public function questions() { return $this->belongsToMany(TestBankQuestion::class, 'test_bank_quiz_questions')->withTimestamps(); }
}
