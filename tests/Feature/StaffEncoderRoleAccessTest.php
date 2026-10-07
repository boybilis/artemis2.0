<?php

namespace Tests\Feature;

use App\Models\Course;
use App\Models\TestBank;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class StaffEncoderRoleAccessTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_assign_encoder_and_staff_roles(): void
    {
        $admin = User::factory()->create(['role' => 'admin', 'is_admin' => true]);
        $encoder = User::factory()->create(['role' => 'student', 'is_admin' => false]);
        $staff = User::factory()->create(['role' => 'student', 'is_admin' => false]);

        $this->actingAs($admin)->post(route('admin.users.role', $encoder), ['role' => 'encoder'])->assertRedirect();
        $this->post(route('admin.users.role', $staff), ['role' => 'staff'])->assertRedirect();

        $this->assertDatabaseHas('users', ['id' => $encoder->id, 'role' => 'encoder', 'is_admin' => false]);
        $this->assertDatabaseHas('users', ['id' => $staff->id, 'role' => 'staff', 'is_admin' => false]);
    }

    public function test_encoder_can_manage_content_and_test_banks_but_not_operations(): void
    {
        $encoder = User::factory()->create(['role' => 'encoder', 'is_admin' => false]);
        $admin = User::factory()->create(['role' => 'admin', 'is_admin' => true]);
        $course = Course::create(['title' => 'DOH-HAAD', 'created_by' => $admin->id]);
        $bank = TestBank::create(['course_id' => $course->id, 'title' => 'DOH Test Bank', 'code' => 'DOH-TB', 'price' => 1000, 'access_days' => 30, 'status' => 'active', 'created_by' => $admin->id]);

        $this->actingAs($encoder)->get(route('admin.content.index'))->assertOk();
        $this->get(route('admin.content.test-banks.manage', [$course, $bank]))->assertOk();
        $this->get(route('admin.users.index'))->assertNotFound();
        $this->get(route('admin.vouchers.index'))->assertNotFound();
        $this->get(route('admin.settings.index'))->assertNotFound();
    }

    public function test_staff_can_manage_operations_but_not_content_or_roles(): void
    {
        $staff = User::factory()->create(['role' => 'staff', 'is_admin' => false]);
        $learner = User::factory()->create(['role' => 'student', 'is_admin' => false]);

        $this->actingAs($staff)->get(route('admin.users.index'))->assertOk();
        $this->get(route('admin.classes.index'))->assertOk();
        $this->get(route('admin.vouchers.index'))->assertOk();
        $this->get(route('admin.reports.index'))->assertOk();
        $this->get(route('admin.content.index'))->assertNotFound();
        $this->post(route('admin.users.role', $learner), ['role' => 'admin'])->assertNotFound();
        $this->get(route('admin.settings.index'))->assertNotFound();
    }
}
