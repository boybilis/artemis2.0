<?php

namespace Tests\Feature;

use App\Models\Course;
use App\Models\Subject;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SubjectManagementTest extends TestCase
{
    use RefreshDatabase;

    public function test_instructor_can_manage_a_subject_without_delivery_schedule(): void
    {
        $instructor = User::factory()->create(['role' => 'instructor', 'is_admin' => false]);
        $course = Course::create(['title' => 'NCLEX Review', 'created_by' => $instructor->id]);

        $payload = [
            'subject_code' => 'NURS-101',
            'title' => 'Medical-Surgical Nursing',
            'description' => 'Adult health review',
            'sort_order' => 1,
        ];

        $this->actingAs($instructor)
            ->post(route('admin.content.subjects.store', $course), $payload)
            ->assertRedirect();

        $subject = Subject::firstOrFail();
        $this->assertSame('NURS-101', $subject->subject_code);
        $this->assertNull($subject->start_date);
        $this->assertNull($subject->modality);

        $this->actingAs($instructor)
            ->post(route('admin.content.subjects.update', [$course, $subject]), [...$payload, 'title' => 'Advanced Medical-Surgical Nursing'])
            ->assertRedirect();

        $this->assertSame('Advanced Medical-Surgical Nursing', $subject->fresh()->title);
    }
}
