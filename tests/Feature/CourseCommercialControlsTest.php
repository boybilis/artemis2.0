<?php

namespace Tests\Feature;

use App\Models\Course;
use App\Models\User;
use App\Models\Subject;
use App\Models\Topic;
use App\Models\Subtopic;
use App\Models\QuizQuestion;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CourseCommercialControlsTest extends TestCase
{
    use RefreshDatabase;

    public function test_instructor_course_is_pending_and_cannot_set_commercial_fields(): void
    {
        $instructor = User::factory()->create(['role' => 'instructor', 'is_admin' => false]);

        $this->actingAs($instructor)->post('/admin/content/courses', [
            'title' => 'PNLE Review', 'description' => 'Review', 'price' => 9999,
            'available_until' => now()->addYear()->format('Y-m-d H:i:s'),
        ])->assertRedirect();

        $course = Course::where('created_by', $instructor->id)->firstOrFail();
        $this->assertSame('pending', $course->approval_status);
        $this->assertFalse($course->is_published);
        $this->assertEquals(0, $course->price);
        $this->assertNull($course->available_until);
    }

    public function test_only_admin_update_sets_price_approval_and_fixed_end_date(): void
    {
        $instructor = User::factory()->create(['role' => 'instructor', 'is_admin' => false]);
        $admin = User::factory()->create(['role' => 'admin', 'is_admin' => true]);
        $course = Course::create(['title' => 'Civil Service Review', 'approval_status' => 'pending', 'is_published' => false, 'price' => 0]);
        $end = now()->addMonths(3)->startOfMinute();

        $this->actingAs($instructor)->post("/admin/content/courses/{$course->id}", [
            'title' => $course->title, 'price' => 1, 'approval_status' => 'approved',
            'available_until' => $end->format('Y-m-d H:i:s'),
        ])->assertRedirect();
        $this->assertSame('pending', $course->fresh()->approval_status);

        $this->actingAs($admin)->post("/admin/content/courses/{$course->id}", [
            'title' => $course->title, 'price' => 750, 'approval_status' => 'approved',
            'available_until' => $end->format('Y-m-d H:i:s'),
        ])->assertRedirect();

        $course->refresh();
        $this->assertSame('approved', $course->approval_status);
        $this->assertTrue($course->is_published);
        $this->assertEquals(750, $course->price);
        $this->assertTrue($course->available_until->equalTo($end));
    }

    public function test_admin_course_status_cascades_to_all_course_content(): void
    {
        $admin = User::factory()->create(['role' => 'admin', 'is_admin' => true]);
        $course = Course::create(['title'=>'NCLEX','approval_status'=>'pending','is_published'=>false,'price'=>0]);
        $subject = Subject::create(['course_id'=>$course->id,'subject_code'=>'N1','title'=>'Nursing','status'=>'pending']);
        $topic = Topic::create(['course_id'=>$course->id,'subject_id'=>$subject->id,'title'=>'Care','status'=>'pending']);
        $subtopic = Subtopic::create(['topic_id'=>$topic->id,'title'=>'Lesson','status'=>'pending']);
        $question = QuizQuestion::create(['course_id'=>$course->id,'topic_id'=>$topic->id,'question_type'=>'quiz','response_type'=>'single','question'=>'Question?','options'=>['A','B'],'answer'=>0,'correct_answers'=>[0],'status'=>'pending']);
        $otherCourse = Course::create(['title'=>'PNLE','approval_status'=>'approved','is_published'=>true,'price'=>0]);
        $otherSubject = Subject::create(['course_id'=>$otherCourse->id,'subject_code'=>'P1','title'=>'PNLE Nursing','status'=>'approved']);
        $otherTopic = Topic::create(['course_id'=>$otherCourse->id,'subject_id'=>$otherSubject->id,'title'=>'PNLE Care','status'=>'approved']);
        $otherSubtopic = Subtopic::create(['topic_id'=>$otherTopic->id,'title'=>'PNLE Lesson','status'=>'approved']);
        $otherQuestion = QuizQuestion::create(['course_id'=>$otherCourse->id,'topic_id'=>$otherTopic->id,'question_type'=>'quiz','response_type'=>'single','question'=>'Other question?','options'=>['A','B'],'answer'=>0,'correct_answers'=>[0],'status'=>'approved']);
        $payload = ['title'=>$course->title,'description'=>'','price'=>500,'available_from'=>now()->subDay()->format('Y-m-d H:i:s'),'available_until'=>now()->addMonth()->format('Y-m-d H:i:s')];

        $this->actingAs($admin)->post("/admin/content/courses/{$course->id}", [...$payload,'approval_status'=>'approved'])->assertRedirect();
        $this->assertSame('approved', $subject->fresh()->status);
        $this->assertSame('approved', $topic->fresh()->status);
        $this->assertSame('approved', $subtopic->fresh()->status);
        $this->assertSame('approved', $question->fresh()->status);

        $this->actingAs($admin)->post("/admin/content/courses/{$course->id}", [...$payload,'approval_status'=>'pending'])->assertRedirect();
        $this->assertSame('pending', $subject->fresh()->status);
        $this->assertSame('pending', $topic->fresh()->status);
        $this->assertSame('pending', $subtopic->fresh()->status);
        $this->assertSame('pending', $question->fresh()->status);
        $this->assertSame('approved', $otherCourse->fresh()->approval_status);
        $this->assertSame('approved', $otherSubject->fresh()->status);
        $this->assertSame('approved', $otherTopic->fresh()->status);
        $this->assertSame('approved', $otherSubtopic->fresh()->status);
        $this->assertSame('approved', $otherQuestion->fresh()->status);
    }
}
