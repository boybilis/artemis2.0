<?php

namespace Tests\Feature;

use App\Models\Course;
use App\Models\Subtopic;
use App\Models\Topic;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class CourseContentOwnershipTest extends TestCase
{
    use RefreshDatabase;

    public function test_topic_cannot_be_deleted_through_another_courses_url(): void
    {
        [$admin, $firstCourse, $foreignTopic] = $this->foreignTopicFixture();

        $this->actingAs($admin)
            ->delete(route('admin.content.topics.destroy', ['course' => $firstCourse->id, 'topic' => $foreignTopic->id]))
            ->assertNotFound();

        $this->assertDatabaseHas('topics', ['id' => $foreignTopic->id]);
    }

    public function test_subtopic_and_its_files_cannot_be_deleted_through_another_courses_url(): void
    {
        Storage::fake('public');
        [$admin, $firstCourse, $foreignTopic] = $this->foreignTopicFixture();
        Storage::disk('public')->put('documentation/protected.pdf', 'document');
        Storage::disk('public')->put('subtopic-videos/protected.mp4', 'video');
        $foreignSubtopic = Subtopic::create([
            'topic_id' => $foreignTopic->id,
            'title' => 'Protected lesson',
            'content_type' => 'subtopic',
            'documentation_path' => '/storage/documentation/protected.pdf',
            'video_path' => 'subtopic-videos/protected.mp4',
            'status' => 'approved',
        ]);

        $this->actingAs($admin)
            ->delete(route('admin.content.subtopics.destroy', ['course' => $firstCourse->id, 'subtopic' => $foreignSubtopic->id]))
            ->assertNotFound();

        $this->assertDatabaseHas('subtopics', ['id' => $foreignSubtopic->id]);
        Storage::disk('public')->assertExists('documentation/protected.pdf');
        Storage::disk('public')->assertExists('subtopic-videos/protected.mp4');
    }

    private function foreignTopicFixture(): array
    {
        $admin = User::factory()->create(['role' => 'admin', 'is_admin' => true]);
        $firstCourse = Course::create(['title' => 'Course A', 'created_by' => $admin->id]);
        $secondCourse = Course::create(['title' => 'Course B', 'created_by' => $admin->id]);
        $foreignTopic = Topic::create(['course_id' => $secondCourse->id, 'title' => 'Course B Topic', 'status' => 'approved']);

        return [$admin, $firstCourse, $foreignTopic];
    }
}
