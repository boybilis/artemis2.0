<?php

namespace Tests\Feature;

use App\Models\Course;
use App\Models\CourseBatch;
use App\Models\CourseEnrollment;
use App\Models\QuizQuestion;
use App\Models\Topic;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class AssessmentTypesTest extends TestCase
{
    use RefreshDatabase;

    private function enroll(User $user, Course $course): CourseEnrollment
    {
        $batch = CourseBatch::create([
            'course_id' => $course->id,
            'name' => 'Test Batch',
            'code' => 'TEST-' . $course->id,
            'status' => 'open',
        ]);

        return CourseEnrollment::create([
            'user_id' => $user->id,
            'batch_id' => $batch->id,
            'status' => 'active',
            'enrolled_at' => now(),
            'expires_at' => now()->addDays(30),
        ]);
    }

    public function test_highlighting_hides_answer_keys_and_supports_exact_and_partial_scoring(): void
    {
        $question = new QuizQuestion([
            'response_type' => 'highlight',
            'maximum_points' => 2,
            'scoring_method' => 'partial_credit',
            'response_config' => [
                'type' => 'highlight_text',
                'segments' => [
                    ['key' => 'segment_1', 'text' => 'difficulty breathing', 'is_correct' => true],
                    ['key' => 'segment_2', 'text' => 'warm hands', 'is_correct' => false],
                    ['key' => 'segment_3', 'text' => 'oxygen saturation of 84%', 'is_correct' => true],
                ],
            ],
        ]);

        $learnerConfig = $question->learnerResponseConfig();
        $this->assertArrayNotHasKey('is_correct', $learnerConfig['segments'][0]);
        $this->assertSame(1.0, $question->gradeAnswer(['segment_1'])['earned']);
        $this->assertSame(0.0, $question->gradeAnswer(['segment_1', 'segment_2'])['earned']);
        $complete = $question->gradeAnswer(['segment_3', 'segment_1']);
        $this->assertSame(2.0, $complete['earned']);
        $this->assertTrue($complete['correct']);
        $this->assertStringContainsString('difficulty breathing', $question->correctAnswerForReview());
        $this->assertStringContainsString('oxygen saturation of 84%', $question->correctAnswerForReview());

        $question->scoring_method = 'all_or_nothing';
        $this->assertSame(0.0, $question->gradeAnswer(['segment_1', 'segment_2', 'segment_3'])['earned']);
    }

    public function test_cloze_dropdown_hides_correct_answers_and_supports_partial_or_exact_scoring(): void
    {
        $question = new QuizQuestion([
            'response_type' => 'cloze',
            'question' => 'The nurse should {{blank_1}} and then {{blank_2}}.',
            'maximum_points' => 2,
            'scoring_method' => 'partial_credit',
            'response_config' => [
                'type'=>'cloze_dropdown',
                'template'=>'The nurse should {{blank_1}} and then {{blank_2}}.',
                'blanks'=>[
                    ['key'=>'blank_1','label'=>'Dropdown 1','options'=>[
                        ['value'=>'blank_1_1','label'=>'assess','is_correct'=>true],
                        ['value'=>'blank_1_2','label'=>'document','is_correct'=>false],
                    ]],
                    ['key'=>'blank_2','label'=>'Dropdown 2','options'=>[
                        ['value'=>'blank_2_1','label'=>'intervene','is_correct'=>true],
                        ['value'=>'blank_2_2','label'=>'leave','is_correct'=>false],
                    ]],
                ],
            ],
        ]);

        $learnerConfig = $question->learnerResponseConfig();
        $this->assertArrayNotHasKey('is_correct', $learnerConfig['blanks'][0]['options'][0]);
        $partial = $question->gradeAnswer(['blank_1'=>'blank_1_1','blank_2'=>'blank_2_2']);
        $this->assertSame(1.0, $partial['earned']);
        $this->assertFalse($partial['correct']);
        $correct = $question->gradeAnswer(['blank_1'=>'blank_1_1','blank_2'=>'blank_2_1']);
        $this->assertSame(2.0, $correct['earned']);
        $this->assertTrue($correct['correct']);
    }

    private function courseAndTopic(): array
    {
        $course = Course::create([
            'title' => 'CSS Foundations',
            'description' => 'Test course',
            'is_published' => true,
        ]);

        $topic = Topic::create([
            'course_id' => $course->id,
            'title' => 'Selectors',
            'sort_order' => 1,
            'status' => 'approved',
        ]);

        return [$course, $topic];
    }

    public function test_sata_topic_quiz_is_graded_on_the_server_and_unlocks_progress(): void
    {
        [$course, $topic] = $this->courseAndTopic();
        $user = User::factory()->create();
        $this->enroll($user, $course);

        QuizQuestion::create([
            'course_id' => $course->id,
            'topic_id' => $topic->id,
            'question_type' => 'quiz',
            'response_type' => 'sata',
            'question' => 'Select valid CSS selectors.',
            'options' => ['.card', 'color:', '#header', 'font-size:'],
            'answer' => 0,
            'correct_answers' => [0, 2],
            'status' => 'approved',
        ]);

        $this->actingAs($user)->getJson("/api/courses/{$course->id}/topics")->assertOk();

        $this->actingAs($user)
            ->postJson('/api/quiz/attempt', [
                'topic_id' => $topic->id,
                'answers' => [[2, 0]],
            ])
            ->assertOk()
            ->assertJsonPath('passed', true)
            ->assertJsonPath('score', 1);

        $this->assertDatabaseHas('user_progress', [
            'user_id' => $user->id,
            'topic_id' => $topic->id,
        ]);
    }

    public function test_midterm_and_final_use_separate_instructor_question_banks(): void
    {
        [$course] = $this->courseAndTopic();
        $user = User::factory()->create();
        $this->enroll($user, $course);

        QuizQuestion::create([
            'course_id' => $course->id,
            'topic_id' => null,
            'question_type' => 'midterm',
            'response_type' => 'sata',
            'question' => 'Midterm SATA',
            'options' => ['A', 'B', 'C', 'D'],
            'answer' => 1,
            'correct_answers' => [1, 3],
            'status' => 'approved',
        ]);

        QuizQuestion::create([
            'course_id' => $course->id,
            'topic_id' => null,
            'question_type' => 'final',
            'response_type' => 'single',
            'question' => 'Final single choice',
            'options' => ['A', 'B', 'C', 'D'],
            'answer' => 2,
            'correct_answers' => [2],
            'status' => 'approved',
        ]);

        $this->actingAs($user)
            ->getJson("/api/courses/{$course->id}/exam/questions?type=mid")
            ->assertOk()
            ->assertJsonCount(1, 'questions')
            ->assertJsonPath('questions.0.question', 'Midterm SATA')
            ->assertJsonPath('questions.0.responseType', 'sata');

        $this->postJson("/api/courses/{$course->id}/exam/submit", [
            'answers' => [[3, 1]],
        ])->assertOk()->assertJsonPath('passed', true);

        $this->assertDatabaseHas('quiz_attempts', [
            'user_id' => $user->id,
            'course_id' => $course->id,
            'assessment_type' => 'midterm',
            'passed' => true,
        ]);
    }

    public function test_instructor_can_create_an_image_question_with_additional_choices(): void
    {
        Storage::fake('public');
        [$course, $topic] = $this->courseAndTopic();
        $instructor = User::factory()->create(['role' => 'instructor']);

        $this->actingAs($instructor)
            ->post("/admin/content/courses/{$course->id}/quizzes", [
                'question_type' => 'quiz',
                'response_type' => 'sata',
                'topic_id' => $topic->id,
                'question' => 'Which areas in the image use valid selectors?',
                'question_image' => UploadedFile::fake()->createWithContent(
                    'selector-diagram.png',
                    base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mNk+A8AAQUBAScY42YAAAAASUVORK5CYII=')
                ),
                'options' => ['Area A', 'Area B', 'Area C', 'Area D', 'Area E', 'Area F'],
                'correct_answers' => [0, 2, 5],
            ])
            ->assertRedirect();

        $question = QuizQuestion::firstOrFail();
        $this->assertSame(6, count($question->options));
        $this->assertSame([0, 2, 5], $question->correct_answers);
        $this->assertSame('pending', $question->status);
        Storage::disk('public')->assertExists($question->image_path);
    }

    public function test_grid_question_is_sanitized_and_graded_by_cell_points(): void
    {
        [$course, $topic] = $this->courseAndTopic();
        $user = User::factory()->create();
        $this->enroll($user, $course);
        $config = [
            'type' => 'dynamic_matrix_grid', 'title' => 'Classification', 'instructions' => 'Select each answer.',
            'maximum_points' => 2,
            'columns' => [['key' => 'finding', 'label' => 'Finding', 'type' => 'static_text'], ['key' => 'class', 'label' => 'Class', 'type' => 'dropdown']],
            'rows' => [
                ['key' => 'row_1', 'cells' => [['column_key' => 'finding', 'type' => 'static_text', 'value' => 'Fever'], ['column_key' => 'class', 'type' => 'dropdown', 'options' => [['value' => 'urgent', 'label' => 'Urgent', 'points' => 1, 'is_correct' => true], ['value' => 'routine', 'label' => 'Routine', 'points' => 0, 'is_correct' => false]]]]],
                ['key' => 'row_2', 'cells' => [['column_key' => 'finding', 'type' => 'static_text', 'value' => 'Refill'], ['column_key' => 'class', 'type' => 'dropdown', 'options' => [['value' => 'urgent', 'label' => 'Urgent', 'points' => 0, 'is_correct' => false], ['value' => 'routine', 'label' => 'Routine', 'points' => 1, 'is_correct' => true]]]]],
            ],
        ];
        QuizQuestion::create(['course_id' => $course->id, 'topic_id' => $topic->id, 'question_type' => 'quiz', 'response_type' => 'grid', 'question' => 'Classify findings.', 'options' => [], 'answer' => 0, 'correct_answers' => [], 'response_config' => $config, 'maximum_points' => 2, 'scoring_method' => 'partial_credit', 'status' => 'approved']);

        $questions = $this->actingAs($user)->getJson("/api/courses/{$course->id}/topics")->assertOk()->json('topics.0.quiz.0');
        $this->assertSame('grid', $questions['responseType']);
        $this->assertArrayNotHasKey('points', $questions['responseConfig']['rows'][0]['cells'][1]['options'][0]);
        $this->assertArrayNotHasKey('is_correct', $questions['responseConfig']['rows'][0]['cells'][1]['options'][0]);

        $this->actingAs($user)->postJson('/api/quiz/attempt', ['topic_id' => $topic->id, 'answers' => [['row_1.class' => 'urgent', 'row_2.class' => 'routine']]])
            ->assertOk()->assertJsonPath('passed', true)->assertJsonPath('score', 2);
    }

    public function test_grid_sata_cell_supports_multiple_selections_and_partial_credit(): void
    {
        $question = new QuizQuestion([
            'response_type' => 'grid', 'maximum_points' => 2, 'scoring_method' => 'partial_credit',
            'response_config' => ['rows' => [[
                'key' => 'row_1', 'cells' => [['column_key' => 'actions', 'type' => 'sata', 'options' => [
                    ['value' => 'assess', 'label' => 'Assess', 'points' => 1, 'is_correct' => true],
                    ['value' => 'notify', 'label' => 'Notify', 'points' => 1, 'is_correct' => true],
                    ['value' => 'ignore', 'label' => 'Ignore', 'points' => 0, 'is_correct' => false],
                ]],
            ]]]],
        ]);

        $partial = $question->gradeAnswer(['row_1.actions' => ['assess']]);
        $complete = $question->gradeAnswer(['row_1.actions' => ['notify', 'assess']]);
        $this->assertSame(1.0, $partial['earned']);
        $this->assertFalse($partial['correct']);
        $this->assertSame(2.0, $complete['earned']);
        $this->assertTrue($complete['correct']);
    }
}
