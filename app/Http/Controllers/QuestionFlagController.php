<?php
namespace App\Http\Controllers;
use App\Models\{QuestionFlag,TestBankQuizAttempt,QuizAttempt};
use Illuminate\Http\Request;
class QuestionFlagController extends Controller {
    public function index(Request $request) {
        return response()->json(['success'=>true,'flags'=>QuestionFlag::where('user_id',$request->user()->id)->with('responder:id,name')->latest()->paginate(10)]);
    }
    public function store(Request $request) {
        $data=$request->validate(['question'=>['required','string','max:30000'],'reason'=>['required','string','min:3','max:5000']]);
        $match=null;
        foreach (['test_bank'=>TestBankQuizAttempt::class,'assessment'=>QuizAttempt::class] as $type=>$model) {
            foreach ($model::where('user_id',$request->user()->id)->whereNotNull('review_data')->latest()->cursor() as $attempt) {
                foreach (($attempt->review_data ?? []) as $index=>$question) {
                    if (is_array($question) && ($question['question'] ?? null)===$data['question']) {
                        $question['flagExam'] = $this->examDetails($attempt, $type);
                        $match=['attempt_type'=>$type,'attempt_id'=>$attempt->id,'question_index'=>(int)$index,'question_snapshot'=>$question];
                        break 3;
                    }
                }
            }
        }
        abort_unless($match,422,'This question is not available in your completed test reviews.');
        QuestionFlag::create($match+['user_id'=>$request->user()->id,'reason'=>$data['reason']]);
        return response()->json(['success'=>true,'message'=>'Question flagged. You can view replies in Flagged Questions.'],201);
    }
    public function manage() {
        $flags = QuestionFlag::with(['user:id,name,email','responder:id,name'])->latest()->paginate(15);
        $banks = TestBankQuizAttempt::with('quiz')->whereIn('id', $flags->where('attempt_type','test_bank')->pluck('attempt_id'))->get()->keyBy('id');
        $assessments = QuizAttempt::with(['topic','subtopic'])->whereIn('id', $flags->where('attempt_type','assessment')->pluck('attempt_id'))->get()->keyBy('id');
        foreach ($flags as $flag) {
            $attempt = ($flag->attempt_type === 'test_bank' ? $banks : $assessments)->get($flag->attempt_id);
            $flag->exam_details = $flag->question_snapshot['flagExam'] ?? ($attempt && $attempt->user_id === $flag->user_id ? $this->examDetails($attempt, $flag->attempt_type) : ['title'=>'Unavailable quiz','type'=>'Unavailable','taken_at'=>null]);
        }
        return view('admin.question-flags', compact('flags'));
    }
    private function examDetails($attempt, string $type): array {
        if ($type === 'test_bank') {
            $quiz = $attempt->quiz;
            $title = $quiz?->title ?? 'Deleted quiz';
            $label = match ($quiz?->test_type) { 'warm_up'=>'Warm-up', 'mastery'=>'Mastery', 'simulation'=>'Simulation', default=>'Test Bank quiz' };
        } else {
            $title = $attempt->subtopic?->title ?? $attempt->topic?->title ?? 'Course assessment';
            $label = ucfirst(str_replace('_',' ', $attempt->assessment_type ?? 'Assessment'));
        }
        return ['title'=>$title,'type'=>$label,'taken_at'=>$attempt->created_at?->toIso8601String()];
    }
    public function reply(Request $request, QuestionFlag $flag) {
        $data=$request->validate(['reply'=>['required','string','min:1','max:10000']]);
        $flag->update($data+['replied_by'=>$request->user()->id,'replied_at'=>now()]);
        return back()->with('success','Reply saved. The learner can now view your answer.');
    }
}
