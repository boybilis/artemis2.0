<?php

namespace Tests\Feature;

use App\Models\Course;
use App\Models\CourseBatch;
use App\Models\CourseEnrollment;
use App\Models\BatchZoomSession;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ZoomSubtopicTest extends TestCase
{
    use RefreshDatabase;

    public function test_instructor_manages_zoom_calendar_on_a_batch(): void
    {
        $instructor = User::factory()->create(['role' => 'instructor', 'is_admin' => false]);
        $course = Course::create(['title' => 'DOH-HAAD', 'created_by' => $instructor->id]);
        $batch = CourseBatch::create(['course_id'=>$course->id, 'name'=>'HAAD Batch 1', 'code'=>'HAAD-B1', 'status'=>'open']);

        $this->actingAs($instructor)->postJson(route('admin.content.batches.zoom-sessions.store', [$course, $batch]), [
            'title' => 'Maternal Nursing Live Review',
            'zoom_url' => 'https://zoom.us/j/123456789',
            'description' => "Live discussion and question review.\nPrepare your notes.",
            'starts_at' => now()->addDay()->format('Y-m-d H:i:s'),
            'ends_at' => now()->addDay()->addHours(2)->format('Y-m-d H:i:s'),
            'status' => 'scheduled',
        ])->assertOk()->assertJsonPath('success', true);

        $this->assertDatabaseHas('batch_zoom_sessions', [
            'batch_id' => $batch->id,
            'title' => 'Maternal Nursing Live Review',
            'zoom_url' => 'https://zoom.us/j/123456789',
            'status' => 'scheduled',
        ]);
    }

    public function test_batch_zoom_schedule_is_visible_only_on_the_matching_enrollment(): void
    {
        $learner = User::factory()->create(['role' => 'student', 'is_admin' => false]);
        $outsider = User::factory()->create(['role' => 'student', 'is_admin' => false]);
        $course = Course::create(['title' => 'DOH-HAAD', 'approval_status'=>'approved', 'is_published'=>true]);
        $batch = CourseBatch::create(['course_id' => $course->id, 'name' => 'Batch 1', 'code' => 'HAAD-B1', 'status' => 'open', 'ends_at'=>now()->addMonth()]);
        CourseEnrollment::create(['user_id' => $learner->id, 'batch_id' => $batch->id, 'status' => 'active', 'enrolled_at' => now()]);
        BatchZoomSession::create([
            'batch_id'=>$batch->id, 'title'=>'Live Review', 'zoom_url'=>'https://zoom.us/j/987654321',
            'description'=>'Weekly live review.', 'starts_at'=>now()->addDay(), 'status'=>'scheduled',
        ]);

        $this->actingAs($learner)->getJson('/api/courses')->assertOk()
            ->assertJsonPath('courses.0.zoom_sessions.0.zoom_url', 'https://zoom.us/j/987654321');
        $this->actingAs($outsider)->getJson('/api/courses')->assertOk()
            ->assertJsonCount(0, 'courses.0.zoom_sessions');
    }

    public function test_new_master_course_zoom_subtopics_are_rejected(): void
    {
        $instructor = User::factory()->create(['role'=>'instructor', 'is_admin'=>false]);
        $course = Course::create(['title'=>'DOH-HAAD']);
        $this->actingAs($instructor)->post(route('admin.content.subtopics.store', $course), [
            'topic_id'=>999, 'content_type'=>'zoom_link', 'title'=>'Wrong location',
        ])->assertSessionHasErrors('content_type');
    }
}
