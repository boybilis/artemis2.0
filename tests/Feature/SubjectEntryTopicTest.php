<?php
namespace Tests\Feature;

use App\Models\{User,Course,CourseBatch,CourseEnrollment,Subject,Topic,Subtopic,QuizQuestion,UserProgress};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SubjectEntryTopicTest extends TestCase
{
    use RefreshDatabase;
    public function test_agreement_completion_unlocks_videos_when_subject_has_no_pre_test(): void {
        extract($this->subject());
        $agreement=Topic::create(['course_id'=>$course->id,'subject_id'=>$subject->id,'title'=>'End User Agreement','status'=>'approved']);
        $document=Subtopic::create(['topic_id'=>$agreement->id,'title'=>'Read agreement','content_type'=>'subtopic','documentation_path'=>'https://example.test/agreement.pdf','status'=>'approved']);
        $lesson=Topic::create(['course_id'=>$course->id,'subject_id'=>$subject->id,'title'=>'Renal videos','status'=>'approved']);
        Subtopic::create(['topic_id'=>$lesson->id,'title'=>'Video 1','content_type'=>'subtopic','video_url'=>'https://example.test/video','status'=>'approved']);
        $assessment=Subtopic::create(['topic_id'=>$lesson->id,'title'=>'Post-Test','content_type'=>'post_test','status'=>'approved']);
        QuizQuestion::create(['course_id'=>$course->id,'topic_id'=>$lesson->id,'subtopic_id'=>$assessment->id,'question_type'=>'subtopic_assessment','response_type'=>'single','question'=>'Question','options'=>['A','B'],'answer'=>0,'correct_answers'=>[0],'maximum_points'=>1,'status'=>'approved']);
        $url="/api/courses/{$course->id}/subtopics/{$assessment->id}/assessment/questions";
        $this->getJson($url)->assertForbidden();
        $data=$this->getJson("/api/courses/{$course->id}/topics")->assertOk()->json('topics');
        $this->assertTrue(collect($data)->firstWhere('id',$lesson->id)['isLocked']);
        $this->postJson('/api/progress/unlock',['topic_id'=>$agreement->id,'subtopic_id'=>$document->id,'item_type'=>'doc'])->assertOk()->assertJsonPath('max_unlocked_index',1);
        $this->getJson($url)->assertForbidden();
        $this->postJson("/api/courses/{$course->id}/subjects/{$subject->id}/policy/accept", ['agreed'=>true])->assertOk();
        $data=$this->getJson("/api/courses/{$course->id}/topics")->assertOk()->json('topics');
        $this->assertFalse(collect($data)->firstWhere('id',$lesson->id)['isLocked']);
        $this->getJson($url)->assertOk();
    }
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
        $this->getJson($url)->assertForbidden()->assertJsonPath('message','Read and accept this subject’s End User Agreement first.');
        UserProgress::create(['user_id'=>$user->id,'course_id'=>$course->id,'topic_id'=>$agreement->id,'max_unlocked_index'=>1]);
        $this->getJson($url)->assertForbidden();
        $this->postJson("/api/courses/{$course->id}/subjects/{$subject->id}/policy/accept", ['agreed'=>true])->assertOk();
        $this->getJson($url)->assertOk()->assertJsonCount(1,'questions');
    }
    public function test_pre_test_can_open_when_no_policy_or_agreement_is_configured(): void {
        extract($this->subject());$item=$this->preTest($course,$subject);
        $this->getJson("/api/courses/{$course->id}/subtopics/{$item->id}/assessment/questions")->assertOk();
    }
    public function test_policy_acceptance_unlocks_only_pre_test_then_scored_pre_test_unlocks_other_sections(): void {
        extract($this->subject());
        $policy = Topic::create(['course_id'=>$course->id, 'subject_id'=>$subject->id, 'title'=>'Policy', 'status'=>'approved', 'sort_order'=>0]);
        $doc = Subtopic::create(['topic_id'=>$policy->id, 'title'=>'End User Agreement', 'content_type'=>'subtopic', 'documentation_path'=>'https://example.test/agreement.pdf', 'status'=>'approved']);
        $preTest = $this->preTest($course, $subject);
        $lecture = Topic::create(['course_id'=>$course->id, 'subject_id'=>$subject->id, 'title'=>'Lectures', 'status'=>'approved', 'sort_order'=>3]);
        Subtopic::create(['topic_id'=>$lecture->id, 'title'=>'Video', 'content_type'=>'subtopic', 'video_url'=>'https://example.test/video', 'status'=>'approved']);
        $url = "/api/courses/{$course->id}/topics";
        $data = collect($this->getJson($url)->assertOk()->json('topics'));
        $this->assertFalse($data->firstWhere('id',$policy->id)['isLocked']);
        $this->assertTrue($data->firstWhere('id',$preTest->topic_id)['isLocked']);
        $this->assertTrue($data->firstWhere('id',$lecture->id)['isLocked']);
        $this->postJson('/api/progress/unlock', ['topic_id'=>$policy->id, 'subtopic_id'=>$doc->id, 'item_type'=>'doc'])->assertOk();
        $data = collect($this->getJson($url)->assertOk()->json('topics'));
        $this->assertTrue($data->firstWhere('id',$preTest->topic_id)['isLocked']);
        $this->postJson("/api/courses/{$course->id}/subjects/{$subject->id}/policy/accept", ['agreed'=>true])->assertOk();
        $data = collect($this->getJson($url)->assertOk()->json('topics'));
        $this->assertFalse($data->firstWhere('id',$preTest->topic_id)['isLocked']);
        $this->assertTrue($data->firstWhere('id',$lecture->id)['isLocked']);
        $this->getJson("/api/courses/{$course->id}/subtopics/{$preTest->id}/assessment/questions")->assertOk();
        $this->postJson("/api/courses/{$course->id}/subtopics/{$preTest->id}/assessment/submit", ['answers'=>[0]])->assertOk();
        $data = collect($this->getJson($url)->assertOk()->json('topics'));
        $this->assertFalse($data->firstWhere('id',$lecture->id)['isLocked']);
    }
    public function test_policy_aliases_do_not_match_ordinary_learning_topics(): void {
        foreach(['Policy','Course Policy','End User Agreement','End-User Agreement'] as $title)$this->assertTrue((new Topic(['title'=>$title]))->isPolicyPrerequisite());
        $this->assertFalse((new Topic(['title'=>'Renal Nursing']))->isPolicyPrerequisite());
    }
}
