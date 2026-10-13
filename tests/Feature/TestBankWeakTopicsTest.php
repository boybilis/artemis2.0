<?php

namespace Tests\Feature;

use App\Http\Controllers\TestBankController;
use App\Models\{Course, Subject, TestBank, TestBankQuestion, TestBankQuiz, TestBankQuizAttempt, User};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Tests\TestCase;

class TestBankWeakTopicsTest extends TestCase
{
    use RefreshDatabase;

    public function test_weak_policy_ranks_completed_answers_and_balances_selected_subjects(): void
    {
        $user = User::factory()->create();
        $other = User::factory()->create();
        $course = Course::create(['title' => 'Weak Topics Course']);
        $bank = TestBank::create(['course_id' => $course->id, 'title' => 'Bank', 'code' => 'WEAK', 'price' => 100, 'access_days' => 30, 'status' => 'active']);
        $subjects = collect();
        foreach ([0, 1, 2, 3] as $index) {
            $subject = Subject::create(['course_id' => $course->id, 'title' => 'Subject '.$index, 'subject_code' => 'WEAK-'.$index, 'status' => 'approved']);
            $subjects->push($subject);
            for ($i = 0; $i < 8; $i++) {
                $question = TestBankQuestion::create(['course_id' => $course->id, 'subject_id' => $subject->id, 'test_bank_id' => $bank->id, 'question' => "Question $index $i", 'options' => ['A', 'B'], 'correct_answer' => 0, 'points' => 1, 'status' => 'active']);
                $question->subjects()->attach($subject->id);
            }
        }
        $quiz = TestBankQuiz::create(['test_bank_id' => $bank->id, 'title' => 'Mixed', 'quiz_type' => 'learner', 'owner_user_id' => $user->id, 'item_count' => 10, 'subject_ids' => $subjects->pluck('id')->all(), 'status' => 'active']);
        $review = [];
        foreach ($subjects as $index => $subject) {
            for ($i = 0; $i < 4; $i++) $review[] = ['subjectIds' => [$subject->id], 'correct' => $i < $index];
        }
        $makeAttempt = fn ($owner, $data) => TestBankQuizAttempt::create(['user_id' => $owner->id, 'test_bank_id' => $bank->id, 'test_bank_quiz_id' => $quiz->id, 'score' => 6, 'total' => 16, 'points_earned' => 6, 'points_possible' => 16, 'passed' => false, 'review_data' => $data]);
        $makeAttempt($user, $review);
        $makeAttempt($user, ['cancelled' => true]);
        $makeAttempt($other, [['subjectIds' => [$subjects[3]->id], 'correct' => false]]);
        $controller = new TestBankController();
        $rank = new \ReflectionMethod($controller, 'weakTopicSubjectIds');
        $this->assertSame($subjects->take(3)->pluck('id')->all(), $rank->invoke($controller, $bank, $user->id)->all());
        $this->assertCount(0, $rank->invoke($controller, $bank, User::factory()->create()->id));
        $this->actingAs($user);
        $request = Request::create('/', 'POST', ['subject_ids' => [$subjects[3]->id], 'item_count' => 10, 'timed' => false, 'coverage_mode' => 'weak']);
        $selection = new \ReflectionMethod($controller, 'learnerQuizSelection');
        [, $ids, $questions] = $selection->invoke($controller, $request, $bank);
        $this->assertSame($subjects->take(3)->pluck('id')->all(), $ids->all());
        $this->assertCount(10, $questions);
        $this->assertCount(10, $questions->unique());
        $counts = TestBankQuestion::whereIn('id', $questions)->with('subjects')->get()->flatMap(fn ($q) => $q->subjects->pluck('id'))->countBy()->values();
        $this->assertLessThanOrEqual(1, $counts->max() - $counts->min());

        // Pre-made subject tests must contribute alongside custom quiz answers.
        foreach ([10 => 'Warm-up', 20 => 'Mastery Test'] as $itemCount => $label) {
            $premade = TestBankQuiz::create([
                'test_bank_id' => $bank->id, 'title' => $label.' Subject 3',
                'quiz_type' => 'subject', 'owner_user_id' => $user->id,
                'item_count' => $itemCount, 'subject_ids' => [$subjects[3]->id], 'status' => 'active',
            ]);
            TestBankQuizAttempt::create([
                'user_id' => $user->id, 'test_bank_id' => $bank->id,
                'test_bank_quiz_id' => $premade->id, 'score' => 0, 'total' => $itemCount,
                'points_earned' => 0, 'points_possible' => $itemCount, 'passed' => false,
                'review_data' => array_fill(0, $itemCount, ['subjectIds' => [$subjects[3]->id], 'correct' => false]),
            ]);
            $this->assertSame(
                [$subjects[0]->id, $subjects[3]->id, $subjects[1]->id],
                $rank->invoke($controller, $bank, $user->id)->all(),
                $label.' pre-made results should change the weakest-subject ranking.',
            );
        }
        [, $ids] = $selection->invoke($controller, $request, $bank);
        $this->assertEqualsCanonicalizing([$subjects[0]->id, $subjects[3]->id, $subjects[1]->id], $ids->all());
    }
}
