<?php

namespace Tests\Feature;

use App\Models\{Course, CourseBatch, CourseEnrollment, QuizAttempt, Subject, Topic, User};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminUserSubjectProgressTest extends TestCase
{
    use RefreshDatabase;

    public function test_progress_loads_only_after_an_enrolled_subject_is_selected_and_is_paginated(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $learner = User::factory()->create(['role' => 'student']);
        $course = Course::create(['title' => 'Enrolled Course']);
        $batch = CourseBatch::create(['course_id' => $course->id, 'name' => 'Enrolled Batch', 'code' => 'ENROLLED']);
        CourseEnrollment::create(['user_id' => $learner->id, 'batch_id' => $batch->id, 'status' => 'active']);
        $subject = Subject::create(['course_id' => $course->id, 'subject_code' => 'SUB1', 'title' => 'Enrolled Subject', 'status' => 'approved']);
        $other = Course::create(['title' => 'Unrelated Course']);
        $unavailable = Subject::create(['course_id' => $other->id, 'subject_code' => 'SUB2', 'title' => 'Unrelated Subject', 'status' => 'approved']);
        for ($i = 1; $i <= 11; $i++) {
            Topic::create(['course_id' => $course->id, 'subject_id' => $subject->id, 'title' => 'Topic number '.$i, 'status' => 'approved', 'sort_order' => $i]);
        }
        QuizAttempt::create(['user_id' => $learner->id, 'course_id' => $course->id, 'batch_id' => $batch->id, 'score' => 8, 'total' => 10, 'passed' => true]);
        $url = route('admin.users.show', $learner);
        $headers = ['X-Requested-With' => 'XMLHttpRequest', 'X-Admin-Table' => 'user-subject-progress'];
        $this->actingAs($admin)->get($url)->assertOk()->assertSee('Enrolled Subject')
            ->assertDontSee('Unrelated Subject')->assertDontSee('Topic Completion');
        $selection = '?batch_id='.$batch->id.'&subject_id='.$subject->id;
        $this->get($url.$selection, $headers)->assertOk()
            ->assertViewHas('topics', fn ($page) => $page->count() === 10 && $page->total() === 11)
            ->assertSee('8/10')->assertDontSee('Topic number 11');
        $this->get($url.$selection.'&topics_page=2', $headers)->assertOk()->assertSee('Topic number 11');
        $this->get($url.'?batch_id='.$batch->id.'&subject_id='.$unavailable->id, $headers)->assertNotFound();
    }
}
