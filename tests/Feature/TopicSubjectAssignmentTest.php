<?php

namespace Tests\Feature;

use App\Models\Course;
use App\Models\Subject;
use App\Models\Topic;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TopicSubjectAssignmentTest extends TestCase
{
    use RefreshDatabase;

    public function test_topic_is_created_under_a_subject_from_the_same_course(): void
    {
        $instructor = User::factory()->create(['role' => 'instructor', 'is_admin' => false]);
        $course = Course::create(['title' => 'NCLEX Review', 'created_by' => $instructor->id]);
        $subject = Subject::create(['course_id' => $course->id, 'subject_code' => 'NURS-101', 'title' => 'Nursing']);

        $this->actingAs($instructor)->post(route('admin.content.topics.store', $course), [
            'subject_id' => $subject->id,
            'title' => 'Cardiovascular Care',
            'description' => 'Cardiac concepts',
        ])->assertRedirect();

        $topic = Topic::firstOrFail();
        $this->assertTrue($topic->subject->is($subject));
    }

    public function test_topic_cannot_use_a_subject_from_another_course(): void
    {
        $instructor = User::factory()->create(['role' => 'instructor', 'is_admin' => false]);
        $course = Course::create(['title' => 'NCLEX Review', 'created_by' => $instructor->id]);
        $otherCourse = Course::create(['title' => 'PNLE Review', 'created_by' => $instructor->id]);
        $otherSubject = Subject::create(['course_id' => $otherCourse->id, 'subject_code' => 'PNLE-1', 'title' => 'PNLE Subject']);

        $this->actingAs($instructor)->post(route('admin.content.topics.store', $course), [
            'subject_id' => $otherSubject->id,
            'title' => 'Invalid Topic',
        ])->assertSessionHasErrors('subject_id');

        $this->assertDatabaseCount('topics', 0);
    }
}
