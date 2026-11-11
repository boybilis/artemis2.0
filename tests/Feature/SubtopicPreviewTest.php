<?php

namespace Tests\Feature;

use App\Models\Course;
use App\Models\QuizQuestion;
use App\Models\Subject;
use App\Models\Subtopic;
use App\Models\Topic;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class SubtopicPreviewTest extends TestCase
{
    use RefreshDatabase;

    public function test_workspace_preview_scores_a_test_without_creating_an_attempt(): void
    {
        $course = Course::create(['title'=>'Preview course']);
        $subject = Subject::create(['course_id'=>$course->id, 'title'=>'Preview subject', 'subject_code'=>'PREVIEW']);
        $topic = Topic::create(['course_id'=>$course->id, 'subject_id'=>$subject->id, 'title'=>'Assessments']);
        $sub = Subtopic::create(['topic_id'=>$topic->id, 'title'=>'Practice', 'content_type'=>'practice_test']);
        $question = QuizQuestion::create(['course_id'=>$course->id, 'topic_id'=>$topic->id, 'subtopic_id'=>$sub->id,
            'question'=>'Choose A', 'options'=>['A','B'], 'answer'=>0, 'question_type'=>'practice_test', 'status'=>'approved']);
        $root = '/admin/content/courses/'.$course->id;
        $preview = "$root/subtopics/{$sub->id}/preview/assessment";
        $this->actingAs(User::factory()->create(['role'=>'encoder', 'is_admin'=>false]));
        $this->get("$root/subjects/{$subject->id}/preview")->assertOk()->assertSee('subject-preview-data')->assertSee('Preview subject');
        $this->getJson($preview)->assertOk()->assertJsonPath('questions.0.id', $question->id)->assertJsonMissingPath('questions.0.correctAnswers');
        $this->postJson($preview, ['ids'=>[$question->id], 'answers'=>[0]])->assertOk()->assertJsonPath('passed', true);
        $this->assertDatabaseCount('quiz_attempts', 0);
        $this->assertDatabaseCount('user_progress', 0);
        $this->actingAs(User::factory()->create(['role'=>'student', 'is_admin'=>false]));
        $this->getJson($preview)->assertNotFound();
    }

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
