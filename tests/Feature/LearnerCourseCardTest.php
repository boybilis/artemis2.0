<?php

namespace Tests\Feature;

use App\Models\Course;
use App\Models\CourseBatch;
use App\Models\CourseEnrollment;
use App\Models\Subject;
use App\Models\Topic;
use App\Models\User;
use App\Models\UserProgress;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LearnerCourseCardTest extends TestCase
{
    use RefreshDatabase;

    public function test_enrolled_course_card_receives_real_progress_and_program_details(): void
    {
        $learner = User::factory()->create();
        $course = Course::create(['title'=>'NCLEX-RN Review','is_published'=>true,'approval_status'=>'approved']);
        Subject::create(['course_id'=>$course->id,'code'=>'NUR-1','title'=>'Nursing Fundamentals','status'=>'approved','sort_order'=>1]);
        Subject::create(['course_id'=>$course->id,'code'=>'NUR-2','title'=>'Medical-Surgical Nursing','status'=>'approved','sort_order'=>2]);
        $first = Topic::create(['course_id'=>$course->id,'title'=>'Policy','status'=>'approved','sort_order'=>1]);
        Topic::create(['course_id'=>$course->id,'title'=>'Fundamentals','status'=>'approved','sort_order'=>2]);
        $batch = CourseBatch::create([
            'course_id'=>$course->id,'name'=>'NCLEX Batch 1','code'=>'NCLEX-B1','status'=>'open',
            'price'=>5999,'modality'=>'Live via Zoom','schedule_day'=>'Monday','start_time'=>'19:00:00',
            'ends_at'=>now()->addMonths(2),
        ]);
        CourseEnrollment::create(['user_id'=>$learner->id,'batch_id'=>$batch->id,'status'=>'active','enrolled_at'=>now(),'expires_at'=>$batch->ends_at]);
        UserProgress::create(['user_id'=>$learner->id,'course_id'=>$course->id,'topic_id'=>$first->id,'max_unlocked_index'=>1]);

        $card = collect($this->actingAs($learner)->getJson('/api/courses')->assertOk()->json('courses'))
            ->firstWhere('batch_id', $batch->id);

        $this->assertTrue($card['is_enrolled']);
        $this->assertSame(2, $card['subject_count']);
        $this->assertSame(1, $card['completed_topic_count']);
        $this->assertSame(2, $card['topic_count']);
        $this->assertSame(50, $card['course_progress']);
        $this->assertSame('Live via Zoom', $card['batch_modality']);
    }
}
