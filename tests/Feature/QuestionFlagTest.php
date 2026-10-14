<?php
namespace Tests\Feature;
use App\Models\{User,QuizAttempt,QuestionFlag};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
class QuestionFlagTest extends TestCase {
    use RefreshDatabase;
    public function test_owned_question_flag_and_management_reply_are_private(): void {
        $learner=User::factory()->create(['role'=>'student','is_admin'=>false]);
        $other=User::factory()->create(['role'=>'student','is_admin'=>false]);
        $admin=User::factory()->create(['role'=>'admin','is_admin'=>true]);
        QuizAttempt::create(['user_id'=>$learner->id,'score'=>0,'total'=>1,'passed'=>false,'review_data'=>[['question'=>'Which answer?','correctAnswer'=>'Assessment first']]]);
        $this->actingAs($other)->postJson('/api/question-flags',['question'=>'Which answer?','reason'=>'Please explain'])->assertStatus(422);
        $this->actingAs($learner)->postJson('/api/question-flags',['question'=>'Which answer?','reason'=>'Please explain'])->assertCreated();
        $flag=QuestionFlag::firstOrFail();
        $this->assertSame('Course assessment', $flag->question_snapshot['flagExam']['title']);
        $this->assertNotEmpty($flag->question_snapshot['flagExam']['taken_at']);
        $this->getJson('/api/question-flags')->assertJsonCount(1,'flags.data');
        $this->put(route('admin.question-flags.reply',$flag),['reply'=>'Unauthorized'])->assertNotFound();
        $this->actingAs($admin)->get(route('admin.question-flags.index'))->assertOk()->assertSee('Please explain')->assertSee('Quiz name')->assertSee('Quiz type')->assertSee('Exam date taken')->assertSee('Date flagged')->assertSee('flagModal'.$flag->id)->assertSee('View');
        $this->put(route('admin.question-flags.reply',$flag),['reply'=>'Assess the patient first.'])->assertRedirect();
        $this->actingAs($learner)->getJson('/api/question-flags')->assertJsonPath('flags.data.0.reply','Assess the patient first.');
        $this->actingAs($other)->getJson('/api/question-flags')->assertJsonCount(0,'flags.data');
        $instructor=User::factory()->create(['role'=>'instructor','is_admin'=>false]);
        $this->actingAs($instructor)->get(route('admin.question-flags.index'))->assertOk();
        $this->put(route('admin.question-flags.reply',$flag),['reply'=>'Further explanation from your instructor.'])->assertRedirect();
        $this->actingAs($learner)->getJson('/api/question-flags')->assertJsonPath('flags.data.0.responder.name',$instructor->name);
    }
}
