<?php
namespace Tests\Feature;

use App\Models\{Course, Subject, TestBank, TestBankQuestion, User};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class EncoderDashboardTest extends TestCase
{
    use RefreshDatabase;

    public function test_encoder_sees_question_inventory_without_user_details(): void
    {
        $encoder = User::factory()->create(['role' => 'encoder', 'is_admin' => false]);
        $learner = User::factory()->create(['name' => 'Private Learner Name']);
        $course = Course::create(['title' => 'Inventory Course']);
        $subject = Subject::create(['course_id' => $course->id, 'title' => 'Inventory Subject', 'subject_code' => 'INV', 'status' => 'approved']);
        $bank = TestBank::create(['course_id' => $course->id, 'title' => 'Inventory Bank', 'code' => 'INV-B', 'price' => 100, 'access_days' => 30, 'status' => 'active']);
        $question = TestBankQuestion::create(['course_id' => $course->id, 'test_bank_id' => $bank->id, 'subject_id' => $subject->id, 'question' => 'Inventory question', 'options' => ['A', 'B'], 'correct_answer' => 0, 'points' => 1, 'status' => 'active']);
        $question->subjects()->attach($subject->id);
        $this->actingAs($encoder)->get(route('admin.dashboard'))->assertOk()
            ->assertViewIs('admin.encoder-dashboard')->assertSee('Inventory Bank')->assertSee('Inventory Subject')
            ->assertDontSee('Recent Users')->assertDontSee('Total users')->assertDontSee($learner->email)->assertDontSee($learner->name)
            ->assertViewHas('courseCounts', fn ($counts) => (int) $counts[$course->id]->total === 1)
            ->assertViewHas('subjectCounts', fn ($counts) => (int) $counts[$subject->id] === 1);
        $this->get(route('admin.users.index'))->assertNotFound();
    }

    public function test_admin_dashboard_retains_its_original_overview(): void
    {
        $admin = User::factory()->create(['role' => 'admin', 'is_admin' => true]);
        $this->actingAs($admin)->get(route('admin.dashboard'))->assertOk()->assertViewIs('admin.dashboard')->assertSee('Recent Users');
    }
}
