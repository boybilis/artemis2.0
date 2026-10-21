<?php

namespace Tests\Feature;

use App\Models\{User,Course,CourseBatch,CourseEnrollment,Subject,Topic,Subtopic,QuizQuestion,QuizAttempt};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SubjectAssessmentBatchScopeTest extends TestCase
{
    use RefreshDatabase;

    public function test_pre_test_limits_are_independent_for_each_batch_and_subject(): void
    {
        $user=User::factory()->create();
        $course=Course::create(['title'=>'Nursing','approval_status'=>'approved','is_published'=>true]);
        $batches=collect(['A','B'])->map(function ($code) use ($user,$course) {
            $batch=CourseBatch::create(['course_id'=>$course->id,'name'=>$code,'code'=>$code,'status'=>'open']);
            $batch->courses()->sync([$course->id]);
            CourseEnrollment::create(['user_id'=>$user->id,'batch_id'=>$batch->id,'status'=>'active','enrolled_at'=>now(),'expires_at'=>now()->addMonth()]);
            return $batch;
        });
        $items=collect(['Fundamentals','Cardiovascular'])->map(function ($title) use ($course) {
            $subject=Subject::create(['course_id'=>$course->id,'title'=>$title,'status'=>'approved']);
            $topic=Topic::create(['course_id'=>$course->id,'subject_id'=>$subject->id,'title'=>'Pre-Test','status'=>'approved']);
            $item=Subtopic::create(['topic_id'=>$topic->id,'title'=>'Pre-test','content_type'=>'pre_test','status'=>'approved']);
            QuizQuestion::create(['course_id'=>$course->id,'topic_id'=>$topic->id,'subtopic_id'=>$item->id,'question_type'=>'subtopic_assessment','response_type'=>'single','question'=>'Question?','options'=>['A','B'],'answer'=>0,'correct_answers'=>[0],'maximum_points'=>1,'status'=>'approved']);
            return $item;
        });
        QuizAttempt::create(['user_id'=>$user->id,'course_id'=>$course->id,'batch_id'=>$batches[0]->id,'topic_id'=>$items[0]->topic_id,'subtopic_id'=>$items[0]->id,'assessment_type'=>'pre_test','score'=>0,'total'=>1,'points_earned'=>0,'points_possible'=>1,'passed'=>false]);
        $lesson=Topic::create(['course_id'=>$course->id,'subject_id'=>$items[0]->topic->subject_id,'title'=>'Lecture Videos','status'=>'approved','sort_order'=>10]);
        Subtopic::create(['topic_id'=>$lesson->id,'content_type'=>'subtopic','title'=>'Video','video_url'=>'https://example.test/video','status'=>'approved']);
        $this->actingAs($user);
        $root='/api/courses/'.$course->id;
        $first=$root.'/subtopics/'.$items[0]->id.'/assessment';
        $second=$root.'/subtopics/'.$items[1]->id.'/assessment';
        $this->getJson($root.'/topics?batch_id='.$batches[1]->id)->assertOk()->assertJsonPath('topics.0.subtopics.0.attemptsUsed',0);
        $before=$this->getJson($root.'/topics?batch_id='.$batches[1]->id)->assertOk()->json('topics');
        $this->assertTrue(collect($before)->firstWhere('id',$lesson->id)['isLocked']);
        $this->getJson($first.'/questions?batch_id='.$batches[1]->id)->assertOk()->assertJsonPath('attemptsUsed',0);
        $this->getJson($first.'/questions?batch_id='.$batches[0]->id)->assertUnprocessable();
        $this->postJson($first.'/submit',['answers'=>[0],'batch_id'=>$batches[1]->id])->assertOk()->assertJsonPath('attemptsUsed',1);
        $this->assertDatabaseHas('quiz_attempts',['user_id'=>$user->id,'batch_id'=>$batches[1]->id,'subtopic_id'=>$items[0]->id,'score'=>1]);
        $after=$this->getJson($root.'/topics?batch_id='.$batches[1]->id)->assertOk()->json('topics');
        $this->assertFalse(collect($after)->firstWhere('id',$lesson->id)['isLocked']);
        $this->getJson($first.'/questions?batch_id='.$batches[1]->id)->assertUnprocessable();
        $this->getJson($first.'/summary?batch_id='.$batches[1]->id)->assertOk()->assertJsonPath('score',1);
        $this->getJson($first.'/summary?batch_id='.$batches[0]->id)->assertOk()->assertJsonPath('score',0);
        $this->getJson($second.'/questions?batch_id='.$batches[1]->id)->assertOk()->assertJsonPath('attemptsUsed',0);
        $this->postJson($second.'/submit',['answers'=>[0],'batch_id'=>$batches[0]->id])->assertBadRequest();
        $this->postJson($second.'/submit',['answers'=>[0],'batch_id'=>$batches[1]->id])->assertOk();
        $this->postJson('/api/progress/unlock',['topic_id'=>$items[1]->topic_id,'subtopic_id'=>$items[1]->id,'item_type'=>'assessment','batch_id'=>$batches[1]->id])->assertOk();
        $outside=CourseBatch::create(['course_id'=>$course->id,'name'=>'Outside','code'=>'OUT','status'=>'open']);
        $outside->courses()->sync([$course->id]);
        $this->getJson($first.'/questions?batch_id='.$outside->id)->assertNotFound();
        $this->assertDatabaseCount('quiz_attempts',3);
    }
}
