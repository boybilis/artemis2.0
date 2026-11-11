<?php

namespace Tests\Feature;

use App\Models\Course;
use App\Models\Subtopic;
use App\Models\Topic;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class SubtopicPreviewTest extends TestCase
{
    use RefreshDatabase;

    public function test_encoder_can_preview_pending_media_only_under_its_course(): void
    {
        Storage::fake('public');
        Storage::disk('public')->put('subtopics/preview.pdf', "%PDF-1.4\n%%EOF\n");
        Storage::disk('public')->put('subtopic-videos/preview.mp4', 'Video fixture');
        $course = Course::create(['title'=>'Preview course']);
        $other = Course::create(['title'=>'Other course']);
        $topic = Topic::create(['course_id'=>$course->id, 'title'=>'Pending section', 'status'=>'pending']);
        $subtopic = Subtopic::create(['topic_id'=>$topic->id, 'title'=>'Pending media', 'status'=>'pending',
            'documentation_path'=>'/storage/subtopics/preview.pdf', 'video_path'=>'subtopic-videos/preview.mp4']);
        $path = fn ($id, $kind) => "/admin/content/courses/$id/subtopics/{$subtopic->id}/preview/$kind";
        $this->actingAs(User::factory()->create(['role'=>'encoder', 'is_admin'=>false]));
        $this->head($path($course->id, 'pdf'))->assertOk()->assertHeader('Content-Type', 'application/pdf');
        $this->get($path($course->id, 'video'))->assertOk();
        $this->get($path($other->id, 'video'))->assertNotFound();
        $this->actingAs(User::factory()->create(['role'=>'student', 'is_admin'=>false]));
        $this->get($path($course->id, 'video'))->assertNotFound();
    }
}
