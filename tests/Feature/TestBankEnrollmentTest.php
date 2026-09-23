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

    public function test_admin_can_create_a_test_bank_for_a_master_course(): void
    {
        $admin = User::factory()->create(['role' => 'admin', 'is_admin' => true]);
        $course = Course::create(['title' => 'PNLE Review']);

        $this->actingAs($admin)->post("/admin/content/courses/{$course->id}/test-banks", [
            'title' => 'PNLE Practice Bank',
            'code' => 'TB-PNLE-001',
            'description' => 'Independent practice catalog.',
            'price' => 1499,
            'access_days' => 45,
            'status' => 'active',
        ])->assertRedirect();

        $this->assertDatabaseHas('test_banks', [
            'course_id' => $course->id,
            'code' => 'TB-PNLE-001',
            'access_days' => 45,
            'created_by' => $admin->id,
        ]);
    }

    public function test_instructor_cannot_manage_paid_test_bank_catalogs(): void
    {
        $instructor = User::factory()->create(['role' => 'instructor', 'is_admin' => false]);
        $course = Course::create(['title' => 'NCLEX Review']);

        $this->actingAs($instructor)
            ->get("/admin/content/courses/{$course->id}/test-banks")
            ->assertNotFound();
    }

    public function test_catalog_only_returns_active_currently_available_test_banks(): void
    {
        $learner = User::factory()->create();
        $course = Course::create(['title' => 'Civil Service Review']);
        TestBank::create(['course_id' => $course->id, 'title' => 'Available Bank', 'code' => 'TB-CS-001', 'price' => 500, 'access_days' => 30, 'status' => 'active']);
        TestBank::create(['course_id' => $course->id, 'title' => 'Draft Bank', 'code' => 'TB-CS-002', 'price' => 500, 'access_days' => 30, 'status' => 'draft']);
        TestBank::create(['course_id' => $course->id, 'title' => 'Future Bank', 'code' => 'TB-CS-003', 'price' => 500, 'access_days' => 30, 'status' => 'active', 'starts_at' => now()->addDay()]);

        $this->actingAs($learner)->getJson('/api/test-banks')
            ->assertOk()
            ->assertJsonCount(1, 'testBanks')
            ->assertJsonPath('testBanks.0.title', 'Available Bank')
            ->assertJsonPath('testBanks.0.course.title', 'Civil Service Review');
    }
}
