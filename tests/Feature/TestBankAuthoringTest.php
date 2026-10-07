<?php

namespace Tests\Feature;

use App\Models\Course;
use App\Models\Subject;
use App\Models\TestBank;
use App\Models\TestBankQuestion;
use App\Models\TestBankQuizAttempt;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class TestBankAuthoringTest extends TestCase
{
    use RefreshDatabase;

    private function catalog(): array
    {
        $admin = User::factory()->create(['role' => 'admin', 'is_admin' => true]);
        $course = Course::create(['title' => 'DOH-HAAD']);
        $subject = Subject::create(['course_id' => $course->id, 'subject_code' => 'MEDSURG', 'title' => 'Medical Surgical Nursing', 'status' => 'approved']);
        $secondSubject = Subject::create(['course_id' => $course->id, 'subject_code' => 'PHARMA', 'title' => 'Pharmacology', 'status' => 'approved']);
        $bank = TestBank::create(['course_id' => $course->id, 'title' => 'DOH Test Bank', 'code' => 'DOH-TB', 'price' => 1000, 'access_days' => 30, 'status' => 'active', 'created_by' => $admin->id]);
        return compact('admin', 'course', 'subject', 'secondSubject', 'bank');
    }

    public function test_admin_can_open_catalog_and_add_a_multiple_choice_question(): void
    {
        extract($this->catalog());
        Storage::fake('public');
        $this->actingAs($admin)->get(route('admin.content.test-banks.manage', [$course, $bank]))
            ->assertOk()->assertSee('Premade Quizzes')->assertSee('Preview')->assertSee('Archive');
        $this->actingAs($admin)->post(route('admin.content.test-banks.questions.store', [$course, $bank]), [
            'subject_ids' => [$subject->id, $secondSubject->id], 'question' => 'Which action is appropriate?',
            'options' => ['Assess first', 'Call immediately', 'Document only', ''],
            'correct_answer' => 0, 'points' => 2.5, 'rationale' => 'Assessment comes first.',
            'rationale_video_url' => 'https://www.youtube.com/watch?v=example123',
            'question_image' => UploadedFile::fake()->createWithContent(
                'reference.png',
                base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mNk+A8AAQUBAScY42YAAAAASUVORK5CYII=')
            ),
            'rationale_image' => UploadedFile::fake()->createWithContent(
                'rationale.png',
                base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mNk+A8AAQUBAScY42YAAAAASUVORK5CYII=')
            ),
        ])->assertSessionHas('success');
        $this->assertDatabaseHas('test_bank_questions', ['test_bank_id' => $bank->id, 'subject_id' => $subject->id, 'correct_answer' => 0, 'points' => 2.5]);
        $question = TestBankQuestion::firstOrFail();
        $this->assertEqualsCanonicalizing([$subject->id, $secondSubject->id], $question->subjects()->pluck('subjects.id')->all());

        $learner = User::factory()->create();
        $bank->enrollments()->create([
            'user_id' => $learner->id,
            'status' => 'active',
            'enrolled_at' => now(),
            'expires_at' => now()->addDays(30),
        ]);
        $workspace = $this->actingAs($learner)->getJson("/api/test-banks/{$bank->id}/workspace");
        $workspace->assertOk();
        $subjectCounts = collect($workspace->json('workspace.subjects'))->pluck('questionCount', 'id');
        $this->assertSame(1, $subjectCounts->get($subject->id));
        $this->assertSame(1, $subjectCounts->get($secondSubject->id));
        $this->assertSame('reference.png', $question->image_filename);
        Storage::disk('public')->assertExists($question->image_path);
        Storage::disk('public')->assertExists($question->rationale_image_path);
        $this->assertSame('rationale.png', $question->rationale_image_filename);
        $this->assertSame('https://www.youtube.com/watch?v=example123', $question->rationale_video_url);

        $this->actingAs($admin)->put(route('admin.content.test-banks.questions.update', [$course, $bank, $question]), [
            'subject_ids' => [$secondSubject->id], 'question' => 'Which medication action is appropriate?',
            'options' => ['Verify the order', 'Administer immediately', '', ''], 'correct_answer' => 0,
            'points' => 3, 'rationale' => 'Verify the order first.',
            'rationale_video_url' => 'https://vimeo.com/123456789',
        ])->assertSessionHas('success');
        $question->refresh();
        $this->assertSame('Which medication action is appropriate?', $question->question);
        $this->assertEqualsCanonicalizing([$secondSubject->id], $question->subjects()->pluck('subjects.id')->all());
        $this->assertSame('https://vimeo.com/123456789', $question->rationale_video_url);
        Storage::disk('public')->assertExists($question->rationale_image_path);

        $this->actingAs($admin)->post(route('admin.content.test-banks.questions.status', [$course, $bank, $question]))
            ->assertSessionHas('success');
        $this->assertDatabaseHas('test_bank_questions', ['id' => $question->id, 'status' => 'inactive']);
        Storage::disk('public')->assertExists($question->image_path);

        $workspace = $this->actingAs($learner)->getJson("/api/test-banks/{$bank->id}/workspace");
        $this->assertSame(0, collect($workspace->json('workspace.subjects'))->pluck('questionCount', 'id')->get($secondSubject->id));

        $this->actingAs($admin)->post(route('admin.content.test-banks.questions.status', [$course, $bank, $question]))
            ->assertSessionHas('success');
        $this->assertDatabaseHas('test_bank_questions', ['id' => $question->id, 'status' => 'active']);
    }

    public function test_admin_can_import_course_subject_questions_from_csv(): void
    {
        extract($this->catalog());
        $csv = "subject_codes,question,option_a,option_b,option_c,option_d,correct_answer,points,rationale\nMEDSURG|PHARMA,What comes first?,Assessment,Intervention,Evaluation,Documentation,A,3,Assess first";
        $this->actingAs($admin)->post(route('admin.content.test-banks.questions.import', [$course, $bank]), [
            'csv_file' => UploadedFile::fake()->createWithContent('questions.csv', $csv),
        ])->assertSessionHas('success');
        $this->assertDatabaseHas('test_bank_questions', ['test_bank_id' => $bank->id, 'subject_id' => $subject->id, 'points' => 3]);
        $question = TestBankQuestion::firstOrFail();
        $this->assertEqualsCanonicalizing([$subject->id, $secondSubject->id], $question->subjects()->pluck('subjects.id')->all());
    }

    public function test_legacy_double_encoded_options_are_normalized_for_editing(): void
    {
        extract($this->catalog());
        $question = TestBankQuestion::create([
            'test_bank_id' => $bank->id, 'course_id' => $course->id, 'subject_id' => $subject->id,
            'question' => 'Which intervention is appropriate?', 'options' => ['Short A', 'Short B'],
            'correct_answer' => 1, 'status' => 'active', 'created_by' => $admin->id,
        ]);
        $question->subjects()->sync([$subject->id]);
        $paragraph = 'This answer contains a full paragraph with the complete clinical intervention and supporting context.';
        DB::table('test_bank_questions')->where('id', $question->id)->update([
            'options' => json_encode(json_encode(['First answer', $paragraph])),
        ]);

        $this->assertSame(['First answer', $paragraph], $question->fresh()->options);
        $response = $this->actingAs($admin)->get(route('admin.content.test-banks.manage', [$course, $bank]));
        $response->assertOk()->assertSee($paragraph)->assertSee('textarea id="editOption0"', false);
    }

    public function test_admin_question_table_loads_twenty_rows_and_searches_the_entire_bank(): void
    {
        extract($this->catalog());
        foreach (range(1, 21) as $number) {
            $question = TestBankQuestion::create([
                'test_bank_id' => $bank->id, 'course_id' => $course->id, 'subject_id' => $subject->id,
                'question' => $number === 1 ? 'Oldest uniquely searchable question' : "Recent question {$number}",
                'options' => ['A', 'B'], 'correct_answer' => 0, 'status' => 'active', 'created_by' => $admin->id,
            ]);
            $question->subjects()->sync([$subject->id]);
        }

        $firstPage = $this->actingAs($admin)->get(route('admin.content.test-banks.manage', [$course, $bank]));
        $firstPage->assertOk()->assertSee('Showing 1–20 of 21')->assertDontSee('Oldest uniquely searchable question');

        $searchUrl = route('admin.content.test-banks.manage', [$course, $bank])
            .'?search='.urlencode('Oldest uniquely searchable');
        $search = $this->actingAs($admin)->get($searchUrl);
        $search->assertOk()->assertSee('Oldest uniquely searchable question')->assertSee('1 matching questions');
    }

    public function test_quiz_builder_uses_all_available_questions_when_requested_count_is_higher(): void
    {
        extract($this->catalog());
        foreach (range(1, 3) as $number) {
            $question = TestBankQuestion::create([
                'test_bank_id' => $bank->id, 'course_id' => $course->id, 'subject_id' => $subject->id,
                'question' => "Question {$number}", 'options' => ['A', 'B'], 'correct_answer' => 0,
                'status' => 'active', 'created_by' => $admin->id,
            ]);
            $question->subjects()->sync([$subject->id, $secondSubject->id]);
        }
        $this->actingAs($admin)->post(route('admin.content.test-banks.quizzes.store', [$course, $bank]), [
            'title' => 'Medical Surgical Drill', 'item_count' => 10, 'subject_ids' => [$subject->id],
        ])->assertSessionHas('success');
        $quiz = $bank->premadeQuizzes()->firstOrFail();
        $this->assertSame(3, $quiz->item_count);
        $this->assertSame(3, $quiz->questions()->count());
        $this->assertTrue($quiz->randomize_questions);
    }

    public function test_catalogs_for_the_same_course_share_the_course_question_bank(): void
    {
        extract($this->catalog());
        $secondBank = TestBank::create([
            'course_id' => $course->id, 'title' => 'DOH Practice Bank', 'code' => 'DOH-TB-2',
            'price' => 1200, 'access_days' => 60, 'status' => 'active', 'created_by' => $admin->id,
        ]);
        $otherCourse = Course::create(['title' => 'Prometrics']);
        $otherBank = TestBank::create([
            'course_id' => $otherCourse->id, 'title' => 'Prometrics Bank', 'code' => 'PROM-TB',
            'price' => 1000, 'access_days' => 30, 'status' => 'active', 'created_by' => $admin->id,
        ]);
        $question = TestBankQuestion::create([
            'test_bank_id' => $bank->id, 'course_id' => $course->id, 'subject_id' => $subject->id,
            'question' => 'Shared DOH course question', 'options' => ['A', 'B'],
            'correct_answer' => 0, 'status' => 'active', 'created_by' => $admin->id,
        ]);
        $question->subjects()->sync([$subject->id]);

        $this->actingAs($admin)
            ->get(route('admin.content.test-banks.manage', [$course, $secondBank]))
            ->assertOk()
            ->assertSee('Shared DOH course question');
        $this->actingAs($admin)
            ->get(route('admin.content.test-banks.manage', [$otherCourse, $otherBank]))
            ->assertOk()
            ->assertDontSee('Shared DOH course question');

        $this->actingAs($admin)->post(route('admin.content.test-banks.quizzes.store', [$course, $secondBank]), [
            'title' => 'Shared Question Quiz', 'item_count' => 1, 'subject_ids' => [$subject->id],
        ])->assertSessionHas('success');
        $quiz = $secondBank->premadeQuizzes()->firstOrFail();
        $this->assertTrue($quiz->questions()->whereKey($question->id)->exists());

        $this->actingAs($admin)->post(route('admin.content.test-banks.questions.status', [$course, $secondBank, $question]))
            ->assertSessionHas('success');
        $this->assertSame('inactive', $question->fresh()->status);
    }

    public function test_enrolled_learner_can_take_a_premade_test_and_review_the_result(): void
    {
        extract($this->catalog());
        Storage::fake('public');
        $question = TestBankQuestion::create([
            'test_bank_id' => $bank->id, 'course_id' => $course->id, 'subject_id' => $subject->id,
            'question' => 'Which answer is correct?', 'options' => ['Correct answer', 'Wrong answer'],
            'correct_answer' => 0, 'points' => 2, 'rationale' => 'This explains the correct answer.',
            'rationale_video_url' => 'https://www.youtube.com/watch?v=example123',
            'status' => 'active', 'created_by' => $admin->id,
        ]);
        $question->subjects()->sync([$subject->id]);
        $quiz = $bank->premadeQuizzes()->create([
            'title' => 'Pre Test 1', 'item_count' => 1, 'subject_ids' => [$subject->id],
            'randomize_questions' => true, 'status' => 'active', 'created_by' => $admin->id,
        ]);
        $quiz->questions()->sync([$question->id]);
        $learner = User::factory()->create();
        $bank->enrollments()->create([
            'user_id' => $learner->id, 'status' => 'active',
            'enrolled_at' => now(), 'expires_at' => now()->addDays(30),
        ]);

        $questions = $this->actingAs($learner)->getJson("/api/test-banks/{$bank->id}/quizzes/{$quiz->id}/questions");
        $questions->assertOk()
            ->assertJsonPath('title', 'Pre Test 1')
            ->assertJsonPath('questions.0.question', 'Which answer is correct?')
            ->assertJsonMissing(['correct_answer' => 0]);

        $result = $this->actingAs($learner)->postJson("/api/test-banks/{$bank->id}/quizzes/{$quiz->id}/submit", [
            'answers' => [0],
        ]);
        $result->assertOk()
            ->assertJsonPath('passed', true)
            ->assertJsonPath('score', 2)
            ->assertJsonPath('total', 2)
            ->assertJsonPath('questions.0.correct', true)
            ->assertJsonPath('questions.0.rationale', 'This explains the correct answer.')
            ->assertJsonPath('questions.0.rationaleVideoUrl', 'https://www.youtube.com/watch?v=example123');
        $this->assertDatabaseHas('test_bank_quiz_attempts', [
            'user_id' => $learner->id, 'test_bank_id' => $bank->id,
            'test_bank_quiz_id' => $quiz->id, 'score' => 1, 'passed' => true,
        ]);
        $this->assertCount(1, TestBankQuizAttempt::firstOrFail()->review_data);

        $history = $this->actingAs($learner)->getJson("/api/test-banks/{$bank->id}/workspace");
        $history->assertOk()
            ->assertJsonCount(1, 'workspace.history')
            ->assertJsonPath('workspace.history.0.title', 'Pre Test 1')
            ->assertJsonPath('workspace.history.0.passed', true)
            ->assertJsonPath('workspace.history.0.correctItems', 1)
            ->assertJsonPath('workspace.history.0.questions.0.rationale', 'This explains the correct answer.');
    }

    public function test_learner_without_active_test_bank_access_cannot_start_a_premade_test(): void
    {
        extract($this->catalog());
        $quiz = $bank->premadeQuizzes()->create([
            'title' => 'Protected Test', 'item_count' => 1, 'subject_ids' => [$subject->id],
            'randomize_questions' => true, 'status' => 'active', 'created_by' => $admin->id,
        ]);

        $this->actingAs(User::factory()->create())
            ->getJson("/api/test-banks/{$bank->id}/quizzes/{$quiz->id}/questions")
            ->assertForbidden();
    }

    public function test_learner_can_build_a_private_timed_quiz_and_all_attempts_are_numbered_in_history(): void
    {
        extract($this->catalog());
        foreach (range(1, 3) as $number) {
            $question = TestBankQuestion::create([
                'test_bank_id' => $bank->id, 'course_id' => $course->id, 'subject_id' => $subject->id,
                'question' => "Builder question {$number}", 'options' => ['Correct', 'Wrong'],
                'correct_answer' => 0, 'points' => 1, 'status' => 'active', 'created_by' => $admin->id,
            ]);
            $question->subjects()->sync([$subject->id]);
        }
        $learner = User::factory()->create();
        $otherLearner = User::factory()->create();
        foreach ([$learner, $otherLearner] as $enrolledLearner) {
            $bank->enrollments()->create([
                'user_id' => $enrolledLearner->id, 'status' => 'active',
                'enrolled_at' => now(), 'expires_at' => now()->addDays(30),
            ]);
        }

        $created = $this->actingAs($learner)->postJson("/api/test-banks/{$bank->id}/quizzes", [
            'subject_ids' => [$subject->id], 'item_count' => 2,
            'timed' => true, 'time_limit_minutes' => 15,
        ]);
        $created->assertOk()->assertJson(['success' => true]);
        $quiz = $bank->premadeQuizzes()->where('quiz_type', 'learner')->firstOrFail();
        $this->assertSame($learner->id, $quiz->owner_user_id);
        $this->assertSame(15, $quiz->time_limit_minutes);
        $this->assertSame(2, $quiz->questions()->count());

        for ($attempt = 1; $attempt <= 3; $attempt++) {
            $this->actingAs($learner)
                ->getJson("/api/test-banks/{$bank->id}/quizzes/{$quiz->id}/questions")
                ->assertOk()->assertJsonPath('timeLimitMinutes', 15);
            $this->actingAs($learner)
                ->postJson("/api/test-banks/{$bank->id}/quizzes/{$quiz->id}/submit", ['answers' => [0, 0]])
                ->assertOk();
        }

        $workspace = $this->actingAs($learner)->getJson("/api/test-banks/{$bank->id}/workspace");
        $workspace->assertOk()
            ->assertJsonCount(1, 'workspace.learnerQuizzes')
            ->assertJsonPath('workspace.learnerQuizzes.0.attemptCount', 3)
            ->assertJsonPath('workspace.learnerQuizzes.0.subjects.0.title', 'Medical Surgical Nursing')
            ->assertJsonCount(3, 'workspace.history')
            ->assertJsonPath('workspace.history.0.attemptNumber', 3)
            ->assertJsonPath('workspace.history.1.attemptNumber', 2)
            ->assertJsonPath('workspace.history.2.attemptNumber', 1);

        $otherWorkspace = $this->actingAs($otherLearner)->getJson("/api/test-banks/{$bank->id}/workspace");
        $otherWorkspace->assertOk()->assertJsonCount(0, 'workspace.learnerQuizzes');
        $this->actingAs($otherLearner)
            ->getJson("/api/test-banks/{$bank->id}/quizzes/{$quiz->id}/questions")
            ->assertNotFound();

        $this->actingAs($learner)->putJson("/api/test-banks/{$bank->id}/quizzes/{$quiz->id}", [
            'subject_ids' => [$subject->id], 'item_count' => 1,
            'timed' => false, 'time_limit_minutes' => null,
        ])->assertOk()->assertJsonPath('message', 'Practice test updated.');
        $quiz->refresh();
        $this->assertNull($quiz->time_limit_minutes);
        $this->assertSame(1, $quiz->questions()->count());
        $this->actingAs($otherLearner)->putJson("/api/test-banks/{$bank->id}/quizzes/{$quiz->id}", [
            'subject_ids' => [$subject->id], 'item_count' => 1,
            'timed' => false, 'time_limit_minutes' => null,
        ])->assertNotFound();

        $this->actingAs($learner)
            ->deleteJson("/api/test-banks/{$bank->id}/quizzes/{$quiz->id}")
            ->assertOk();
        $this->assertDatabaseMissing('test_bank_quizzes', ['id' => $quiz->id]);
        $this->assertDatabaseMissing('test_bank_quiz_attempts', ['test_bank_quiz_id' => $quiz->id]);
    }

    public function test_deleting_one_catalog_preserves_its_questions_for_another_catalog_in_the_same_course(): void
    {
        extract($this->catalog());
        $secondBank = TestBank::create([
            'course_id' => $course->id, 'title' => 'DOH Practice Bank', 'code' => 'DOH-TB-2',
            'price' => 1200, 'access_days' => 60, 'status' => 'active', 'created_by' => $admin->id,
        ]);
        $question = TestBankQuestion::create([
            'test_bank_id' => $bank->id, 'course_id' => $course->id, 'subject_id' => $subject->id,
            'question' => 'Question that must survive', 'options' => ['A', 'B'],
            'correct_answer' => 0, 'status' => 'active', 'created_by' => $admin->id,
        ]);
        $question->subjects()->sync([$subject->id]);

        $this->actingAs($admin)->delete(route('admin.content.test-banks.destroy', [$course, $bank]))
            ->assertSessionHas('success');

        $this->assertDatabaseHas('test_bank_questions', [
            'id' => $question->id,
            'test_bank_id' => $secondBank->id,
            'course_id' => $course->id,
        ]);
    }

    public function test_catalog_cannot_use_a_subject_from_another_course(): void
    {
        extract($this->catalog());
        $otherCourse = Course::create(['title' => 'Other']);
        $other = Subject::create(['course_id' => $otherCourse->id, 'subject_code' => 'OTHER', 'title' => 'Other', 'status' => 'approved']);
        $this->actingAs($admin)->post(route('admin.content.test-banks.questions.store', [$course, $bank]), [
            'subject_ids' => [$other->id], 'question' => 'Invalid?', 'options' => ['A', 'B'],
            'correct_answer' => 0,
        ])->assertNotFound();
        $this->assertDatabaseCount('test_bank_questions', 0);
    }
}
