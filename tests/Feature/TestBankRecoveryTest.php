<?php
namespace Tests\Feature;
use App\Models\{User,Course,Subject,TestBank,TestBankQuestion,TestBankQuizAttempt,TestBankEnrollment};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\{Cache,Event};
use Tests\TestCase;
class TestBankRecoveryTest extends TestCase {
    use RefreshDatabase;
    public function test_explicit_cancellation_records_recovered_attempt_and_clears_draft(): void {
        extract($this->exam());
        $this->flushSession();$this->postJson('/api/auth/login',['email'=>$user->email,'password'=>'password'])->assertOk();
        $this->deleteJson($url.'/attempt')->assertOk();
        $this->assertTrue(TestBankQuizAttempt::firstOrFail()->review_data['cancelled']);
        $this->assertNull(Cache::get('tb_recovery_'.$user->id.'_'.$quiz->id));
        $this->deleteJson($url.'/attempt')->assertOk();
        $this->assertDatabaseCount('test_bank_quiz_attempts',1);
    }
    public function test_cancellation_save_failure_preserves_recovery(): void {
        extract($this->exam());
        Event::listen('eloquent.creating: '.TestBankQuizAttempt::class,fn()=>throw new \RuntimeException('Cancellation save failed'));
        $this->deleteJson($url.'/attempt')->assertStatus(500);
        Event::forget('eloquent.creating: '.TestBankQuizAttempt::class);
        $this->assertNotNull(Cache::get('tb_recovery_'.$user->id.'_'.$quiz->id));
        $this->assertNotEmpty(session()->get('test_bank_quiz_'.$user->id.'_'.$quiz->id));
        $this->assertDatabaseCount('test_bank_quiz_attempts',0);
    }
    private function exam(bool $shuffle = true): array {
        $user=User::factory()->create();$course=Course::create(['title'=>'Recovery']);
        $subject=Subject::create(['course_id'=>$course->id,'subject_code'=>'REC','title'=>'Recovery','status'=>'approved']);
        $bank=TestBank::create(['course_id'=>$course->id,'title'=>'Recovery bank','code'=>'RECOVERY','price'=>100,'access_days'=>30,'status'=>'active']);
        $q=TestBankQuestion::create(['course_id'=>$course->id,'test_bank_id'=>$bank->id,'subject_id'=>$subject->id,'question'=>'Recovery question','options'=>['Correct','Wrong'],'correct_answer'=>0,'points'=>1,'status'=>'active']);$q->subjects()->attach($subject);
        $quiz=$bank->premadeQuizzes()->create(['title'=>'Recovery quiz','quiz_type'=>'premade','test_type'=>'warm_up','item_count'=>1,'subject_ids'=>[$subject->id],'status'=>'active','time_limit_minutes'=>10]);$quiz->questions()->attach($q);
        TestBankEnrollment::activate($bank,$user);$this->actingAs($user);
        $url='/api/test-banks/'.$bank->id.'/quizzes/'.$quiz->id;
        $start=$this->getJson($url.'/questions'.($shuffle ? '?shuffle_choices=1' : ''))->assertOk()->json();
        $answer=array_search('Correct',$start['questions'][0]['options']);
        return compact('user','bank','quiz','url','start','answer');
    }
    public function test_resume_keeps_original_deadline_choices_and_saved_progress_after_session_loss(): void {
        extract($this->exam());
        $this->postJson($url.'/draft',['attempt_token'=>$start['attemptToken'],'answers'=>[$answer],'marked'=>[0],'index'=>0])->assertOk();
        $this->travel(2)->minutes();$this->flushSession();$this->postJson('/api/auth/login',['email'=>$user->email,'password'=>'password'])->assertOk();
        $resume=$this->getJson($url.'/questions?shuffle_choices=1')->assertOk()->assertJsonPath('resumed',true)->json();
        $this->assertSame($start['attemptToken'],$resume['attemptToken']);
        $this->assertSame($start['deadline'],$resume['deadline']);
        $this->assertSame($start['questions'],$resume['questions']);
        $this->assertSame([$answer],$resume['draft']['answers']);
        $this->assertSame([0],$resume['draft']['marked']);
    }
    public function test_failed_database_save_preserves_attempt_and_retry_creates_only_one_result(): void {
        extract($this->exam());
        $payload=['attempt_token'=>$start['attemptToken'],'answers'=>[$answer]];
        Event::listen('eloquent.creating: '.TestBankQuizAttempt::class,fn()=>throw new \RuntimeException('Simulated save interruption'));
        $this->postJson($url.'/submit',$payload)->assertStatus(500);
        Event::forget('eloquent.creating: '.TestBankQuizAttempt::class);
        $this->assertDatabaseCount('test_bank_quiz_attempts',0);
        $this->assertNotNull(Cache::get('tb_recovery_'.$user->id.'_'.$quiz->id));
        $this->assertNotEmpty(session()->get('test_bank_quiz_'.$user->id.'_'.$quiz->id));
        $saved=$this->postJson($url.'/submit',$payload)->assertOk()->json();
        $this->assertNull(Cache::get('tb_recovery_'.$user->id.'_'.$quiz->id));
        $this->assertNull(session()->get('test_bank_quiz_'.$user->id.'_'.$quiz->id));
        $this->postJson($url.'/submit',$payload)->assertOk()->assertJsonPath('attemptId',$saved['attemptId']);
        $this->assertDatabaseCount('test_bank_quiz_attempts',1);
    }
    public function test_expired_exam_accepts_only_answers_saved_before_deadline(): void {
        extract($this->exam());
        $this->postJson($url.'/draft',['attempt_token'=>$start['attemptToken'],'answers'=>[$answer],'marked'=>[],'index'=>0])->assertOk();
        $this->travel(11)->minutes();
        $this->postJson($url.'/draft',['attempt_token'=>$start['attemptToken'],'answers'=>[1-$answer],'index'=>0])->assertUnprocessable();
        $this->postJson($url.'/submit',['attempt_token'=>$start['attemptToken'],'answers'=>[1-$answer]])->assertOk()->assertJsonPath('correctItems',1);
    }
    public function test_blank_answer_is_not_credited_as_option_a(): void {
        extract($this->exam(false));
        $this->postJson($url.'/submit',['attempt_token'=>$start['attemptToken'],'answers'=>[null]])->assertOk()->assertJsonPath('correctItems',0);
    }
    public function test_missing_answer_is_not_credited_as_option_a(): void {
        extract($this->exam(false));
        $this->postJson($url.'/submit',['attempt_token'=>$start['attemptToken'],'answers'=>[]])->assertOk()->assertJsonPath('correctItems',0);
    }
    public function test_test_bank_review_flags_reference_the_saved_attempt(): void {
        extract($this->exam());
        $result=$this->postJson($url.'/submit',['attempt_token'=>$start['attemptToken'],'answers'=>[$answer]])->assertOk()->json();
        $reference=$result['questions'][0]['flagReference'];
        $this->assertSame('test_bank',$reference['attempt_type']);
        $this->assertSame($result['attemptId'],$reference['attempt_id']);
        $this->postJson('/api/question-flags',$reference+['reason'=>'Please explain this answer'])->assertCreated();
        $this->assertDatabaseHas('question_flags',['user_id'=>$user->id,'attempt_type'=>'test_bank','attempt_id'=>$result['attemptId'],'question_index'=>0]);
    }
}
