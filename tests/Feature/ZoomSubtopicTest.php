<?php

namespace Tests\Feature;

use App\Models\Course;
use App\Models\CourseBatch;
use App\Models\CourseEnrollment;
use App\Models\Subtopic;
use App\Models\Topic;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ZoomSubtopicTest extends TestCase
{
    use RefreshDatabase;

    public function test_instructor_can_save_a_scheduled_zoom_link_under_a_topic(): void
    {
        $instructor = User::factory()->create(['role' => 'instructor', 'is_admin' => false]);
        $course = Course::create(['title' => 'DOH-HAAD', 'created_by' => $instructor->id]);
        $topic = Topic::create(['course_id' => $course->id, 'title' => 'Zoom Link', 'status' => 'approved']);

        $this->actingAs($instructor)->post(route('admin.content.subtopics.store', $course), [
            'topic_id' => $topic->id,
            'content_type' => 'zoom_link',
            'title' => 'Maternal Nursing Live Review',
            'zoom_url' => 'https://zoom.us/j/123456789',
            'zoom_description' => "Live discussion and question review.\nPrepare your notes.",
            'zoom_starts_at' => '2026-08-15 09:00:00',
            'zoom_ends_at' => '2026-08-15 11:00:00',
        ])->assertRedirect()->assertSessionHasNoErrors();

        $this->assertDatabaseHas('subtopics', [
            'topic_id' => $topic->id,
            'content_type' => 'zoom_link',
            'title' => 'Maternal Nursing Live Review',
            'zoom_url' => 'https://zoom.us/j/123456789',
            'status' => 'pending',
        ]);
    }

    public function test_approved_zoom_schedule_is_returned_to_an_enrolled_learner(): void
    {
        $learner = User::factory()->create(['role' => 'student', 'is_admin' => false]);
        $course = Course::create(['title' => 'DOH-HAAD']);
        $batch = CourseBatch::create(['course_id' => $course->id, 'name' => 'Batch 1', 'code' => 'HAAD-B1', 'status' => 'open']);
        CourseEnrollment::create(['user_id' => $learner->id, 'batch_id' => $batch->id, 'status' => 'active', 'enrolled_at' => now()]);
        $topic = Topic::create(['course_id' => $course->id, 'title' => 'Zoom Link', 'status' => 'approved']);
        Subtopic::create([
            'topic_id' => $topic->id, 'content_type' => 'zoom_link', 'title' => 'Live Review',
            'zoom_url' => 'https://zoom.us/j/987654321', 'zoom_description' => 'Weekly live review.',
            'zoom_starts_at' => '2026-08-15 09:00:00', 'status' => 'approved',
        ]);

        $this->actingAs($learner)->getJson("/api/courses/{$course->id}/topics")
            ->assertOk()
            ->assertJsonPath('topics.0.subtopics.0.contentType', 'zoom_link')
            ->assertJsonPath('topics.0.subtopics.0.zoomUrl', 'https://zoom.us/j/987654321')
            ->assertJsonPath('topics.0.subtopics.0.zoomDescription', 'Weekly live review.');
    }
}
