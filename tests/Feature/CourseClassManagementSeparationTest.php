<?php

namespace Tests\Feature;

use App\Models\Course;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CourseClassManagementSeparationTest extends TestCase
{
    use RefreshDatabase;

    public function test_course_management_contains_content_controls_without_batch_controls(): void
    {
        $admin = User::factory()->create(['role' => 'admin', 'is_admin' => true]);
        Course::create(['title' => 'DOH-HAAD', 'created_by' => $admin->id]);

        $this->actingAs($admin)->get(route('admin.content.index'))
            ->assertOk()
            ->assertSee('Course Content Library')
            ->assertSee('Manage Content')
            ->assertDontSee('Create Batch')
            ->assertDontSee('Manage Batches');
    }

    public function test_class_management_contains_batch_controls_without_content_editing_controls(): void
    {
        $admin = User::factory()->create(['role' => 'admin', 'is_admin' => true]);
        Course::create(['title' => 'DOH-HAAD', 'created_by' => $admin->id]);

        $this->actingAs($admin)->get(route('admin.classes.index'))
            ->assertOk()
            ->assertSee('Class Management')
            ->assertSee('Create Batch')
            ->assertSee('Manage Batches')
            ->assertDontSee('Manage Content');
    }
}
