<?php

namespace Tests\Feature;

use App\Models\Course;
use App\Models\TestBank;
use App\Models\TestBankEnrollment;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class TestBankEnrollmentTest extends TestCase
{
    use RefreshDatabase;

    public function test_each_test_bank_belongs_to_a_course_and_activation_uses_its_access_duration(): void
    {
        Carbon::setTestNow('2026-09-23 09:00:00');
        $learner = User::factory()->create();
        $course = Course::create(['title' => 'DOH-HAAD']);
        $testBank = TestBank::create([
            'course_id' => $course->id,
            'title' => 'DOH-HAAD Test Bank',
            'code' => 'TB-DOH-001',
            'access_days' => 30,
            'status' => 'active',
        ]);

        $enrollment = TestBankEnrollment::activate($testBank, $learner);

        $this->assertTrue($enrollment->enrolled_at->equalTo(now()));
        $this->assertTrue($enrollment->expires_at->equalTo(now()->addDays(30)));
        $this->assertTrue($testBank->course->is($course));
        Carbon::setTestNow();
    }

    public function test_learner_feed_only_returns_active_unexpired_test_bank_access(): void
    {
        $learner = User::factory()->create();
        $course = Course::create(['title' => 'NCLEX Review']);
        $active = TestBank::create([
            'course_id' => $course->id,
            'title' => 'NCLEX Test Bank',
            'code' => 'TB-NCLEX-001',
            'access_days' => 60,
            'status' => 'active',
        ]);
        $expired = TestBank::create([
            'course_id' => $course->id,
            'title' => 'Expired Test Bank',
            'code' => 'TB-NCLEX-OLD',
            'access_days' => 10,
            'status' => 'active',
        ]);
        TestBankEnrollment::activate($active, $learner);
        TestBankEnrollment::create([
            'test_bank_id' => $expired->id,
            'user_id' => $learner->id,
            'status' => 'active',
            'enrolled_at' => now()->subDays(20),
            'expires_at' => now()->subDays(10),
        ]);

        $this->actingAs($learner)->getJson('/api/test-banks/enrolled')
            ->assertOk()
            ->assertJsonCount(1, 'testBanks')
            ->assertJsonPath('testBanks.0.title', 'NCLEX Test Bank')
            ->assertJsonPath('testBanks.0.course.title', 'NCLEX Review');
    }

    public function test_enrolled_test_bank_feed_requires_authentication(): void
    {
        $this->getJson('/api/test-banks/enrolled')->assertUnauthorized();
    }
}
