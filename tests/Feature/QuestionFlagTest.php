<?php
namespace Tests\Feature;
use App\Models\{User,QuizAttempt,QuestionFlag};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
class QuestionFlagTest extends TestCase {
    use RefreshDatabase;
    public function test_unread_counts_clear_only_on_view_and_reply_updates_become_unread(): void {
        $learner=User::factory()->create(['role'=>'student','is_admin'=>false]);
        $other=User::factory()->create(['role'=>'student','is_admin'=>false]);
        $admin=User::factory()->create(['role'=>'admin','is_admin'=>true]);
        $flag=QuestionFlag::create(['user_id'=>$learner->id,'attempt_type'=>'assessment','attempt_id'=>1,'question_index'=>0,'question_snapshot'=>['question'=>'Question'],'reason'=>'Please explain']);
        $this->actingAs($admin)->getJson(route('admin.question-flags.unread'))->assertJsonPath('count',1);
        $this->get(route('admin.question-flags.index'))->assertOk();
        $this->getJson(route('admin.question-flags.unread'))->assertJsonPath('count',1);
        $this->postJson(route('admin.question-flags.read',$flag))->assertOk();
        $this->getJson(route('admin.question-flags.unread'))->assertJsonPath('count',0);
        $this->put(route('admin.question-flags.reply',$flag),['reply'=>'First explanation'])->assertRedirect();
        $firstReply=$flag->fresh()->replied_at->toIso8601String();
        $this->actingAs($other)->getJson('/api/question-flags/unread')->assertJsonPath('count',0);
        $this->postJson('/api/question-flags/'.$flag->id.'/read',['replied_at'=>$firstReply])->assertNotFound();
        $this->getJson(route('admin.question-flags.unread'))->assertNotFound();
        $this->actingAs($learner)->getJson('/api/question-flags/unread')->assertJsonPath('count',1);
        $this->getJson('/api/question-flags')->assertOk();
        $this->getJson('/api/question-flags/unread')->assertJsonPath('count',1);
        $this->postJson('/api/question-flags/'.$flag->id.'/read',['replied_at'=>$firstReply])->assertOk();
        $this->getJson('/api/question-flags/unread')->assertJsonPath('count',0);
        $this->travel(2)->seconds();
        $this->actingAs($admin)->put(route('admin.question-flags.reply',$flag),['reply'=>'Updated explanation'])->assertRedirect();
        $this->actingAs($learner)->postJson('/api/question-flags/'.$flag->id.'/read',['replied_at'=>$firstReply])->assertOk();
        $this->getJson('/api/question-flags/unread')->assertJsonPath('count',1);
        $this->postJson('/api/question-flags/'.$flag->id.'/read',['replied_at'=>$flag->fresh()->replied_at->toIso8601String()])->assertOk();
        $this->getJson('/api/question-flags/unread')->assertJsonPath('count',0);
    }
    public function test_repeated_question_is_linked_to_exact_attempt_and_position(): void {
        $learner=User::factory()->create();
        $old=QuizAttempt::create(['user_id'=>$learner->id,'score'=>0,'total'=>2,'passed'=>false,'created_at'=>now()->subDays(2),'review_data'=>[
            ['question'=>'Repeated question','questionId'=>101],
            ['question'=>'Repeated question','questionId'=>102],
        ]]);
        QuizAttempt::create(['user_id'=>$learner->id,'score'=>0,'total'=>1,'passed'=>false,'review_data'=>[['question'=>'Repeated question','questionId'=>103]]]);
        $reference=QuestionFlag::reviewQuestions($old,'assessment')[1]['flagReference'];
        $this->actingAs($learner)->postJson('/api/question-flags',$reference+['reason'=>'Please explain this specific question'])->assertCreated();
        $flag=QuestionFlag::firstOrFail();
        $this->assertSame($old->id,(int)$flag->attempt_id);
        $this->assertSame(1,(int)$flag->question_index);
        $this->assertSame(102,$flag->question_snapshot['questionId']);
        $this->assertSame($old->created_at->toIso8601String(),$flag->question_snapshot['flagExam']['taken_at']);
        $this->postJson('/api/question-flags',array_merge($reference,['question_index'=>99,'reason'=>'Invalid position']))->assertUnprocessable();
        $this->postJson('/api/question-flags',['question'=>'Repeated question','reason'=>'Text only is ambiguous'])->assertUnprocessable();
        $this->postJson('/api/question-flags',array_merge($reference,['attempt_type'=>'test_bank','reason'=>'Wrong attempt type']))->assertUnprocessable();
        $this->assertDatabaseCount('question_flags',1);
    }
    public function test_owned_question_flag_and_management_reply_are_private(): void {
        $learner=User::factory()->create(['role'=>'student','is_admin'=>false]);
        $other=User::factory()->create(['role'=>'student','is_admin'=>false]);
        $admin=User::factory()->create(['role'=>'admin','is_admin'=>true]);
        $attempt=QuizAttempt::create(['user_id'=>$learner->id,'score'=>0,'total'=>1,'passed'=>false,'review_data'=>[['question'=>'Which answer?','correctAnswer'=>'Assessment first']]]);
        $payload=['attempt_type'=>'assessment','attempt_id'=>$attempt->id,'question_index'=>0,'reason'=>'Please explain'];
        $this->actingAs($other)->postJson('/api/question-flags',$payload)->assertStatus(422);
        $this->actingAs($learner)->postJson('/api/question-flags',$payload)->assertCreated();
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
