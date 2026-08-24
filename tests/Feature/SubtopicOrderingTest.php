<?php

namespace Tests\Feature;

use App\Models\Course;
use App\Models\Subject;
use App\Models\Subtopic;
use App\Models\Topic;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class SubtopicOrderingTest extends TestCase
{
    use RefreshDatabase;

    public function test_blank_subtopic_order_uses_the_next_available_order(): void
    {
        $instructor = User::factory()->create(['role' => 'instructor', 'is_admin' => false]);
        $course = Course::create(['title' => 'NCLEX Review', 'created_by' => $instructor->id]);
        $subject = Subject::create(['course_id' => $course->id, 'subject_code' => 'NURS-101', 'title' => 'Nursing']);
        $topic = Topic::create(['course_id' => $course->id, 'subject_id' => $subject->id, 'title' => 'Policy', 'sort_order' => 1]);
        Subtopic::create(['topic_id' => $topic->id, 'title' => 'First', 'sort_order' => 1]);

        $this->actingAs($instructor)->post(route('admin.content.subtopics.store', $course), [
            'topic_id' => $topic->id,
            'content_type' => 'subtopic',
            'title' => 'Review Policy',
            'sort_order' => '',
        ])->assertRedirect()->assertSessionHasNoErrors();

        $this->assertSame(2, Subtopic::where('title', 'Review Policy')->firstOrFail()->sort_order);
    }

    public function test_instructor_can_upload_a_video_with_progress_compatible_storage(): void
    {
        Storage::fake('public');
        $instructor = User::factory()->create(['role' => 'instructor', 'is_admin' => false]);
        $course = Course::create(['title' => 'NCLEX Review', 'created_by' => $instructor->id]);
        $subject = Subject::create(['course_id' => $course->id, 'subject_code' => 'NURS-102', 'title' => 'Nursing']);
        $topic = Topic::create(['course_id' => $course->id, 'subject_id' => $subject->id, 'title' => 'Video Topic']);

        $this->actingAs($instructor)->post(route('admin.content.subtopics.store', $course), [
            'topic_id' => $topic->id, 'content_type' => 'subtopic', 'title' => 'Uploaded lesson',
            'video_file' => UploadedFile::fake()->create('lesson.mp4', 1024, 'video/mp4'),
        ])->assertRedirect()->assertSessionHasNoErrors();

        $subtopic = Subtopic::where('title', 'Uploaded lesson')->firstOrFail();
        Storage::disk('public')->assertExists($subtopic->video_path);
        $this->assertSame('lesson.mp4', $subtopic->video_filename);
    }
}
