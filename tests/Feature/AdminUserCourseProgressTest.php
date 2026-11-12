<?php

namespace Tests\Feature;

use App\Models\{Course, CourseBatch, CourseEnrollment, QuizAttempt, Subject, Subtopic, Topic, User, UserProgress};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class AdminUserCourseProgressTest extends TestCase
{
    use RefreshDatabase;

    public function test_progress_loads_only_after_an_enrolled_course_is_selected_and_is_paginated(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $learner = User::factory()->create(['role' => 'student']);
        $course = Course::create(['title' => 'Enrolled Course']);
        $batch = CourseBatch::create(['course_id' => $course->id, 'name' => 'Enrolled Batch', 'code' => 'ENROLLED']);
        CourseEnrollment::create(['user_id' => $learner->id, 'batch_id' => $batch->id, 'status' => 'active']);
        Storage::fake('local');
        $subject = Subject::create(['course_id' => $course->id, 'subject_code' => 'SUB1', 'title' => 'Enrolled Subject', 'status' => 'approved', 'sort_order' => 1]);
        $other = Course::create(['title' => 'Unrelated Course']);
        for ($i = 2; $i <= 11; $i++) {
            Subject::create(['course_id' => $course->id, 'subject_code' => 'SUB'.$i, 'title' => 'Enrolled Subject '.$i, 'status' => 'approved', 'sort_order' => $i]);
        }
        $topic = Topic::create(['course_id' => $course->id, 'subject_id' => $subject->id, 'title' => 'Individual learning activity', 'status' => 'approved']);
        Subtopic::create(['topic_id' => $topic->id, 'title' => 'Handout activity', 'content_type' => 'subtopic', 'status' => 'approved', 'documentation_path' => 'sample.pdf', 'sort_order' => 1]);
        $pre = Subtopic::create(['topic_id' => $topic->id, 'title' => 'Diagnostic', 'content_type' => 'pre_test', 'status' => 'approved', 'sort_order' => 2]);
        Subtopic::create(['topic_id' => $topic->id, 'title' => 'Post assessment', 'content_type' => 'post_test', 'status' => 'approved', 'sort_order' => 3]);
        UserProgress::create(['user_id' => $learner->id, 'course_id' => $course->id, 'topic_id' => $topic->id, 'max_unlocked_index' => 2]);
        QuizAttempt::create(['user_id' => $learner->id, 'course_id' => $course->id, 'batch_id' => $batch->id, 'topic_id' => $topic->id, 'subtopic_id' => $pre->id, 'assessment_type' => 'pre_test', 'score' => 4, 'total' => 5, 'passed' => true]);
        QuizAttempt::create(['user_id' => $learner->id, 'course_id' => $course->id, 'batch_id' => $batch->id, 'score' => 8, 'total' => 10, 'passed' => true]);
        $url = route('admin.users.show', $learner);
        $headers = ['X-Requested-With' => 'XMLHttpRequest', 'X-Admin-Table' => 'user-course-progress'];
        $this->actingAs($admin)->get($url)->assertOk()->assertSee('Enrolled Course')
            ->assertDontSee('Unrelated Course')->assertDontSee('data-user-progress-tables>', false);
        $selection = '?batch_id='.$batch->id.'&course_id='.$course->id;
        $this->get($url.$selection, $headers)->assertOk()
            ->assertViewHas('subjects', fn ($page) => $page->count() === 10 && $page->total() === 11)
            ->assertSee('8/10')->assertSee('67%')->assertSee('4/5')->assertSee('Not taken')->assertSee('Not enabled')
            ->assertSee('Expand all')->assertSee('role="switch"', false)->assertSee('aria-expanded="false"', false)
            ->assertDontSee('Individual learning activity')->assertDontSee('Handout activity')->assertDontSee('Enrolled Subject 11');
        $this->get($url.$selection.'&subjects_page=2', $headers)->assertOk()->assertSee('Enrolled Subject 11');
        Storage::disk('local')->put("course-gamification/{$course->id}.json", json_encode(['enabled' => true, 'points_enabled' => true, 'points' => ['content' => 25, 'assessment' => 100]]));
        $this->get($url.$selection, $headers)->assertOk()->assertViewHas('subjects', fn ($page) => $page->items()[0]['exp'] === 125);
        $this->get($url.'?batch_id='.$batch->id.'&course_id='.$other->id, $headers)->assertNotFound();
    }
}
