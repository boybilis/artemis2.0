<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
class QuestionFlag extends Model {
    protected $fillable = ['user_id','attempt_type','attempt_id','question_index','question_snapshot','reason','reply','replied_by','replied_at'];
    protected $casts = ['question_snapshot'=>'array','replied_at'=>'datetime'];
    public function user() { return $this->belongsTo(User::class); }
    public function responder() { return $this->belongsTo(User::class, 'replied_by'); }
}
