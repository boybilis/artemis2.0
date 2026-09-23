<?php

namespace Tests\Feature;

use App\Models\Course;
use App\Models\Subject;
use App\Models\TestBank;
use App\Models\TestBankQuestion;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Tests\TestCase;

class TestBankAuthoringTest extends TestCase
{
    use RefreshDatabase;

    private function catalog(): array
    {
        $admin = User::factory()->create(['role' => 'admin', 'is_admin' => true]);
        $course = Course::create(['title' => 'DOH-HAAD']);
        $subject = Subject::create(['course_id' => $course->id, 'subject_code' => 'MEDSURG', 'title' => 'Medical Surgical Nursing', 'status' => 'approved']);
        $bank = TestBank::create(['course_id' => $course->id, 'title' => 'DOH Test Bank', 'code' => 'DOH-TB', 'price' => 1000, 'access_days' => 30, 'status' => 'active', 'created_by' => $admin->id]);
        return compact('admin', 'course', 'subject', 'bank');
    }

    public function test_admin_can_open_catalog_and_add_a_multiple_choice_question(): void
    {
        extract($this->catalog());
        $this->actingAs($admin)->get(route('admin.content.test-banks.manage', [$course, $bank]))->assertOk()->assertSee('Premade Quizzes');
        $this->actingAs($admin)->post(route('admin.content.test-banks.questions.store', [$course, $bank]), [
            'subject_id' => $subject->id, 'question' => 'Which action is appropriate?',
            'options' => ['Assess first', 'Call immediately', 'Document only', ''],
            'correct_answer' => 0, 'rationale' => 'Assessment comes first.',
        ])->assertSessionHas('success');
        $this->assertDatabaseHas('test_bank_questions', ['test_bank_id' => $bank->id, 'subject_id' => $subject->id, 'correct_answer' => 0]);
    }

    public function test_admin_can_import_course_subject_questions_from_csv(): void
    {
        extract($this->catalog());
        $csv = "subject_code,question,option_a,option_b,option_c,option_d,correct_answer,rationale\nMEDSURG,What comes first?,Assessment,Intervention,Evaluation,Documentation,A,Assess first";
        $this->actingAs($admin)->post(route('admin.content.test-banks.questions.import', [$course, $bank]), [
            'csv_file' => UploadedFile::fake()->createWithContent('questions.csv', $csv),
        ])->assertSessionHas('success');
        $this->assertDatabaseHas('test_bank_questions', ['test_bank_id' => $bank->id, 'subject_id' => $subject->id]);
    }

    public function test_quiz_builder_uses_all_available_questions_when_requested_count_is_higher(): void
    {
        extract($this->catalog());
        foreach (range(1, 3) as $number) TestBankQuestion::create([
            'test_bank_id' => $bank->id, 'course_id' => $course->id, 'subject_id' => $subject->id,
            'question' => "Question {$number}", 'options' => ['A', 'B'], 'correct_answer' => 0,
            'status' => 'active', 'created_by' => $admin->id,
        ]);
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
            'subject_id' => $other->id, 'question' => 'Invalid?', 'options' => ['A', 'B'],
            'correct_answer' => 0,
        ])->assertNotFound();
        $this->assertDatabaseCount('test_bank_questions', 0);
    }
}
