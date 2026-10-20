<?php
namespace Tests\Feature;

use App\Models\{User,Course,CourseBatch,CourseEnrollment,Subject,Topic,Subtopic,QuizQuestion,UserProgress};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SubjectEntryTopicTest extends TestCase
{
    use RefreshDatabase;
    private function subject(): array {
        $user=User::factory()->create();
        $course=Course::create(['title'=>'Review','approval_status'=>'approved','is_published'=>true]);
        $batch=CourseBatch::create(['course_id'=>$course->id,'name'=>'Review batch','code'=>'ENTRY','status'=>'open']);
        CourseEnrollment::create(['user_id'=>$user->id,'batch_id'=>$batch->id,'status'=>'active','enrolled_at'=>now()]);
        $subject=Subject::create(['course_id'=>$course->id,'subject_code'=>'SUBJ-16','title'=>'Renal','status'=>'approved']);
        $this->actingAs($user);
        return compact('user','course','subject');
    }
    private function preTest($course,$subject): Subtopic {
        $topic=Topic::create(['course_id'=>$course->id,'subject_id'=>$subject->id,'title'=>'Pre-Test','status'=>'approved']);
        $item=Subtopic::create(['topic_id'=>$topic->id,'content_type'=>'pre_test','title'=>'Pre-Test','status'=>'approved']);
        QuizQuestion::create(['course_id'=>$course->id,'topic_id'=>$topic->id,'subtopic_id'=>$item->id,'question_type'=>'subtopic_assessment','response_type'=>'single','question'=>'Question','options'=>['A','B'],'answer'=>0,'correct_answers'=>[0],'maximum_points'=>1,'status'=>'approved']);
        return $item;
    }
    public function test_agreement_is_recognized_and_must_be_completed_before_pre_test(): void {
        extract($this->subject());
        $agreement=Topic::create(['course_id'=>$course->id,'subject_id'=>$subject->id,'title'=>'End User Agreement','status'=>'approved']);
        Subtopic::create(['topic_id'=>$agreement->id,'title'=>'Read agreement','content_type'=>'subtopic','documentation_path'=>'https://example.test/agreement.pdf','status'=>'approved']);
        $item=$this->preTest($course,$subject);
        $data=$this->getJson("/api/courses/{$course->id}/topics")->assertOk()->json('topics');
        $entry=collect($data)->firstWhere('id',$agreement->id);
        $this->assertTrue($entry['isPolicyTopic']);$this->assertFalse($entry['isLocked']);
        $this->assertTrue(collect($data)->firstWhere('id',$item->topic_id)['isPreTestTopic']);
        $url="/api/courses/{$course->id}/subtopics/{$item->id}/assessment/questions";
        $this->getJson($url)->assertForbidden()->assertJsonPath('message','Complete the Policy / End User Agreement topic before opening the Pre-test.');
        UserProgress::create(['user_id'=>$user->id,'course_id'=>$course->id,'topic_id'=>$agreement->id,'max_unlocked_index'=>1]);
        $this->getJson($url)->assertOk()->assertJsonCount(1,'questions');
    }
    public function test_pre_test_can_open_when_no_policy_or_agreement_is_configured(): void {
        extract($this->subject());$item=$this->preTest($course,$subject);
        $this->getJson("/api/courses/{$course->id}/subtopics/{$item->id}/assessment/questions")->assertOk();
    }
    public function test_policy_aliases_do_not_match_ordinary_learning_topics(): void {
        foreach(['Policy','Course Policy','End User Agreement','End-User Agreement'] as $title)$this->assertTrue((new Topic(['title'=>$title]))->isPolicyPrerequisite());
        $this->assertFalse((new Topic(['title'=>'Renal Nursing']))->isPolicyPrerequisite());
    }
}
