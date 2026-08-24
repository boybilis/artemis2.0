<?php

namespace Tests\Feature;

use App\Models\Course;
use App\Models\QuizQuestion;
use App\Models\Subject;
use App\Models\Subtopic;
use App\Models\Topic;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SubjectContentWorkspaceTest extends TestCase
{
    use RefreshDatabase;

    public function test_subject_manage_workspace_only_displays_content_from_the_selected_subject(): void
    {
        $instructor = User::factory()->create(['role' => 'instructor', 'is_admin' => false]);
        $course = Course::create(['title' => 'DOH-HAAD', 'created_by' => $instructor->id]);
        $first = Subject::create(['course_id' => $course->id, 'subject_code' => 'SUB-A', 'title' => 'First Subject']);
        $second = Subject::create(['course_id' => $course->id, 'subject_code' => 'SUB-B', 'title' => 'Second Subject']);
        $firstTopic = Topic::create(['course_id' => $course->id, 'subject_id' => $first->id, 'title' => 'Visible Topic', 'sort_order' => 1]);
        $secondTopic = Topic::create(['course_id' => $course->id, 'subject_id' => $second->id, 'title' => 'Hidden Topic', 'sort_order' => 1]);

        QuizQuestion::create([
            'course_id' => $course->id, 'topic_id' => $firstTopic->id, 'question_type' => 'quiz',
            'response_type' => 'single', 'question' => 'Visible Question', 'options' => ['A', 'B'],
            'answer' => 0, 'correct_answers' => [0], 'status' => 'approved',
        ]);
        QuizQuestion::create([
            'course_id' => $course->id, 'topic_id' => $secondTopic->id, 'question_type' => 'quiz',
            'response_type' => 'single', 'question' => 'Hidden Question', 'options' => ['A', 'B'],
            'answer' => 0, 'correct_answers' => [0], 'status' => 'approved',
        ]);

        $this->actingAs($instructor)
            ->get(route('admin.content.topics', ['course' => $course->id, 'subject_id' => $first->id]))
            ->assertOk()->assertSee('Visible Topic')
            ->assertDontSee('class="topic-card" data-id="' . $secondTopic->id . '"', false);

        $this->actingAs($instructor)
            ->get(route('admin.content.quizzes', ['course' => $course->id, 'subject_id' => $first->id]))
            ->assertOk()->assertSee('Visible Topic')->assertDontSee('Hidden Topic');
    }

    public function test_course_wide_content_urls_return_to_the_subject_catalog(): void
    {
        $instructor = User::factory()->create(['role' => 'instructor', 'is_admin' => false]);
        $course = Course::create(['title' => 'DOH-HAAD', 'created_by' => $instructor->id]);

        $this->actingAs($instructor)->get(route('admin.content.topics', $course->id))
            ->assertRedirect(route('admin.content.subjects', $course->id));
        $this->actingAs($instructor)->get(route('admin.content.quizzes', $course->id))
            ->assertRedirect(route('admin.content.subjects', $course->id));
    }

    public function test_topic_import_copies_subtopics_assessments_and_questions_into_current_subject(): void
    {
        $admin = User::factory()->create(['role' => 'admin', 'is_admin' => true]);
        $course = Course::create(['title' => 'DOH-HAAD', 'created_by' => $admin->id]);
        $sourceSubject = Subject::create(['course_id' => $course->id, 'subject_code' => 'SRC', 'title' => 'Source']);
        $targetSubject = Subject::create(['course_id' => $course->id, 'subject_code' => 'DST', 'title' => 'Destination']);
        $sourceTopic = Topic::create([
            'course_id' => $course->id, 'subject_id' => $sourceSubject->id, 'title' => 'Policy',
            'description' => 'Shared policy content', 'sort_order' => 1, 'quiz_passing_percentage' => 85,
        ]);
        $assessment = Subtopic::create([
            'topic_id' => $sourceTopic->id, 'content_type' => 'pre_test', 'title' => 'Pre-test',
            'instructions' => 'Read the instructions.', 'maximum_attempts' => 1, 'sort_order' => 1,
        ]);
        QuizQuestion::create([
            'course_id' => $course->id, 'topic_id' => $sourceTopic->id, 'subtopic_id' => $assessment->id,
            'question_type' => 'pre_test', 'response_type' => 'single', 'question' => 'Imported question',
            'options' => ['A', 'B'], 'answer' => 0, 'correct_answers' => [0], 'status' => 'approved',
        ]);

        $this->actingAs($admin)->post(route('admin.content.topics.import', $course->id), [
            'subject_id' => $targetSubject->id,
            'source_topic_id' => $sourceTopic->id,
        ])->assertRedirect()->assertSessionHas('success');

        $copy = Topic::where('subject_id', $targetSubject->id)->where('title', 'Policy')->firstOrFail();
        $this->assertNotSame($sourceTopic->id, $copy->id);
        $this->assertSame(85, $copy->quiz_passing_percentage);
        $copiedAssessment = $copy->subtopics()->where('content_type', 'pre_test')->firstOrFail();
        $this->assertNotSame($assessment->id, $copiedAssessment->id);
        $this->assertDatabaseHas('quiz_questions', [
            'topic_id' => $copy->id,
            'subtopic_id' => $copiedAssessment->id,
            'question' => 'Imported question',
        ]);
    }
}
