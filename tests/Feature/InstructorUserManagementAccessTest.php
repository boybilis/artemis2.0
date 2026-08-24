<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class InstructorUserManagementAccessTest extends TestCase
{
    use RefreshDatabase;

    public function test_instructor_dashboard_does_not_show_user_management_menu(): void
    {
        $instructor = User::factory()->create(['role' => 'instructor', 'is_admin' => false]);

        $this->actingAs($instructor)->get(route('admin.dashboard'))
            ->assertOk()
            ->assertDontSee(route('admin.users.index'), false);
    }

    public function test_instructor_cannot_open_or_modify_user_management_records_directly(): void
    {
        $instructor = User::factory()->create(['role' => 'instructor', 'is_admin' => false]);
        $learner = User::factory()->create(['role' => 'student', 'is_admin' => false, 'is_active' => true]);

        $this->actingAs($instructor)->get(route('admin.users.index'))->assertNotFound();
        $this->get(route('admin.users.show', $learner))->assertNotFound();
        $this->post(route('admin.users.toggle', $learner))->assertNotFound();
        $this->post(route('admin.users.role', $learner), ['role' => 'admin'])->assertNotFound();

        $learner->refresh();
        $this->assertTrue((bool) $learner->is_active);
        $this->assertSame('student', $learner->role);
    }

    public function test_administrator_still_sees_and_can_open_user_management(): void
    {
        $admin = User::factory()->create(['role' => 'admin', 'is_admin' => true]);

        $this->actingAs($admin)->get(route('admin.dashboard'))
            ->assertOk()
            ->assertSee(route('admin.users.index'), false);
        $this->get(route('admin.users.index'))->assertOk();
    }
}
