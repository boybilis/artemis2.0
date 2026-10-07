<?php

namespace Tests\Feature;

use App\Models\Course;
use App\Models\Subject;
use App\Models\TestBank;
use App\Models\TestBankQuestion;
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
