<?php

namespace Tests\Feature;

use App\Models\Course;
use App\Models\CourseEnrollment;
use App\Models\CourseBatch;
use App\Models\QuizQuestion;
use App\Models\QuizAttempt;
use App\Models\Subject;
use App\Models\Subtopic;
use App\Models\Topic;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SubtopicAssessmentTest extends TestCase
{
    use RefreshDatabase;

    public function test_post_test_uses_its_configured_attempt_limit_and_own_bank(): void
    {
        $user = User::factory()->create();
        $course = Course::create(['title'=>'NCLEX','approval_status'=>'approved','is_published'=>true]);
        $batch = CourseBatch::create(['course_id'=>$course->id,'name'=>'NCLEX Batch 1','code'=>'NCLEX-B1','status'=>'open']);
        CourseEnrollment::create(['user_id'=>$user->id,'batch_id'=>$batch->id,'status'=>'active','enrolled_at'=>now(),'expires_at'=>now()->addMonth()]);
        $subject = Subject::create(['course_id'=>$course->id,'subject_code'=>'N1','title'=>'Nursing']);
        $policyTopic = Topic::create(['course_id'=>$course->id,'subject_id'=>$subject->id,'title'=>'Policy','status'=>'approved']);
        $topic = Topic::create(['course_id'=>$course->id,'subject_id'=>$subject->id,'title'=>'Care','status'=>'approved']);
        $preTest = Subtopic::create(['topic_id'=>$policyTopic->id,'content_type'=>'pre_test','title'=>'Pre-test','status'=>'approved']);
        QuizAttempt::create(['user_id'=>$user->id,'course_id'=>$course->id,'batch_id'=>$batch->id,'topic_id'=>$policyTopic->id,'subtopic_id'=>$preTest->id,'assessment_type'=>'pre_test','score'=>1,'total'=>1,'points_earned'=>1,'points_possible'=>1,'passed'=>true]);
        $item = Subtopic::create(['topic_id'=>$topic->id,'content_type'=>'post_test','title'=>'Post-test','instructions'=>'Answer all questions.','maximum_attempts'=>2,'status'=>'approved']);
        QuizQuestion::create(['course_id'=>$course->id,'topic_id'=>$topic->id,'subtopic_id'=>$item->id,'question_type'=>'subtopic_assessment','response_type'=>'single','question'=>'Question?','rationale'=>'A is correct because it follows the review guideline.','options'=>['A','B'],'answer'=>0,'correct_answers'=>[0],'status'=>'approved','maximum_points'=>1]);

        $this->actingAs($user)->getJson("/api/courses/{$course->id}/subtopics/{$item->id}/assessment/questions")->assertOk()->assertJsonCount(1, 'questions');
        $this->actingAs($user)->postJson("/api/courses/{$course->id}/subtopics/{$item->id}/assessment/submit", ['answers'=>[0]])->assertOk()->assertJson(['passed'=>true,'maximumAttempts'=>2]);
        $this->actingAs($user)->getJson("/api/courses/{$course->id}/subtopics/{$item->id}/assessment/questions")->assertOk();
        $this->actingAs($user)->postJson("/api/courses/{$course->id}/subtopics/{$item->id}/assessment/submit", ['answers'=>[1]])
            ->assertOk()
            ->assertJsonPath('incorrectQuestions.0.learnerAnswer', 'B')
            ->assertJsonPath('incorrectQuestions.0.correctAnswer', 'A')
            ->assertJsonPath('incorrectQuestions.0.rationale', 'A is correct because it follows the review guideline.');
        $this->actingAs($user)->getJson("/api/courses/{$course->id}/subtopics/{$item->id}/assessment/questions")->assertStatus(422);
        $this->actingAs($user)->getJson("/api/courses/{$course->id}/subtopics/{$item->id}/assessment/summary")
            ->assertOk()
            ->assertJsonPath('score', 0)
            ->assertJsonPath('total', 1)
            ->assertJsonPath('reviewAvailable', true)
            ->assertJsonPath('incorrectQuestions.0.learnerAnswer', 'B')
            ->assertJsonPath('incorrectQuestions.0.correctAnswer', 'A')
            ->assertJsonPath('incorrectQuestions.0.rationale', 'A is correct because it follows the review guideline.');
        $batchPeer = User::factory()->create();
        CourseEnrollment::create(['user_id'=>$batchPeer->id,'batch_id'=>$batch->id,'status'=>'active','enrolled_at'=>now()]);
        QuizAttempt::create(['user_id'=>$batchPeer->id,'course_id'=>$course->id,'batch_id'=>$batch->id,'topic_id'=>$topic->id,'subtopic_id'=>$item->id,'assessment_type'=>'post_test','score'=>1,'total'=>1,'points_earned'=>1,'points_possible'=>1,'passed'=>true]);
        $secondBatch = CourseBatch::create(['course_id'=>$course->id,'name'=>'NCLEX Batch 2','code'=>'NCLEX-B2','status'=>'open']);
        $coursePeer = User::factory()->create();
        CourseEnrollment::create(['user_id'=>$coursePeer->id,'batch_id'=>$secondBatch->id,'status'=>'active','enrolled_at'=>now()]);
        QuizAttempt::create(['user_id'=>$coursePeer->id,'course_id'=>$course->id,'batch_id'=>$secondBatch->id,'topic_id'=>$topic->id,'subtopic_id'=>$item->id,'assessment_type'=>'post_test','score'=>1,'total'=>1,'points_earned'=>1,'points_possible'=>1,'passed'=>true]);
        $this->actingAs($user)->getJson("/api/courses/{$course->id}/progress-report")
            ->assertOk()
            ->assertJsonPath('course.id', $course->id)
            ->assertJsonPath('batch.id', $batch->id)
            ->assertJsonCount(3, 'attempts')
            ->assertJsonPath('attempts.1.assessmentType', 'post_test')
            ->assertJsonPath('attempts.1.attemptNumber', 1)
            ->assertJsonPath('attempts.2.attemptNumber', 2)
            ->assertJsonPath('attempts.2.percentage', 0)
            ->assertJsonPath('attempts.2.batchRank.rank', 2)
            ->assertJsonPath('attempts.2.batchRank.total', 2)
            ->assertJsonPath('attempts.2.courseRank.rank', 3)
            ->assertJsonPath('attempts.2.courseRank.total', 3);
    }
}
