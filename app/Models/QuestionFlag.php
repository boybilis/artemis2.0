<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
class QuestionFlag extends Model {
    public static function reviewQuestions(Model $attempt, string $type): array {
        $questions = [];
        foreach (($attempt->review_data ?? []) as $index => $question) {
            if (!is_array($question)) continue;
            $question['flagReference'] = ['attempt_type'=>$type,'attempt_id'=>$attempt->id,'question_index'=>(int)$index];
            $questions[] = $question;
        }
        return $questions;
    }
    protected $fillable = ['user_id','attempt_type','attempt_id','question_index','question_snapshot','reason','reply','replied_by','replied_at','staff_read_at','learner_read_at'];
    protected $casts = ['question_snapshot'=>'array','replied_at'=>'datetime'];
    public function user() { return $this->belongsTo(User::class); }
    public function responder() { return $this->belongsTo(User::class, 'replied_by'); }
}
