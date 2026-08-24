<?php

namespace Tests\Feature;

use App\Models\Course;
use App\Models\CourseBatch;
use App\Models\CourseEnrollment;
use App\Models\QuizAttempt;
use App\Models\Subtopic;
use App\Models\Topic;
use App\Models\User;
use App\Models\UserProgress;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LearnerProgressSecurityTest extends TestCase
{
    use RefreshDatabase;

    public function test_learner_cannot_skip_to_a_later_learning_item(): void
    {
        [$learner, $topic, $first, $second] = $this->learningTopic();

        $this->actingAs($learner)->postJson('/api/progress/unlock', [
            'topic_id' => $topic->id,
            'subtopic_id' => $second->id,
            'item_type' => 'doc',
        ])->assertStatus(409);

        $this->assertDatabaseMissing('user_progress', [
            'user_id' => $learner->id,
            'topic_id' => $topic->id,
            'max_unlocked_index' => 1,
        ]);
    }

    public function test_server_advances_only_one_verified_sequential_item_per_request(): void
    {
        [$learner, $topic, $first, $second] = $this->learningTopic();

        $this->actingAs($learner)->postJson('/api/progress/unlock', [
            'topic_id' => $topic->id,
            'subtopic_id' => $first->id,
            'item_type' => 'doc',
        ])->assertOk()->assertJsonPath('max_unlocked_index', 1);

        $this->actingAs($learner)->postJson('/api/progress/unlock', [
            'topic_id' => $topic->id,
            'subtopic_id' => $first->id,
            'item_type' => 'doc',
        ])->assertStatus(409);

        $this->assertSame(1, UserProgress::where('user_id', $learner->id)->where('topic_id', $topic->id)->value('max_unlocked_index'));
    }

    public function test_assessment_item_requires_a_scored_submission_in_the_active_batch(): void
    {
        [$learner, $topic, $first] = $this->learningTopic();
        $assessment = Subtopic::create(['topic_id' => $topic->id, 'title' => 'Pre-test', 'content_type' => 'pre_test', 'status' => 'approved', 'sort_order' => 3]);
        $batch = CourseEnrollment::where('user_id', $learner->id)->firstOrFail()->batch;
        UserProgress::create(['user_id' => $learner->id, 'course_id' => $topic->course_id, 'topic_id' => $topic->id, 'max_unlocked_index' => 2]);

        $payload = ['topic_id' => $topic->id, 'subtopic_id' => $assessment->id, 'item_type' => 'assessment'];
        $this->actingAs($learner)->postJson('/api/progress/unlock', $payload)->assertStatus(422);

        QuizAttempt::create([
            'user_id' => $learner->id, 'course_id' => $topic->course_id, 'batch_id' => $batch->id,
            'topic_id' => $topic->id, 'subtopic_id' => $assessment->id, 'assessment_type' => 'pre_test',
            'score' => 1, 'total' => 1, 'points_earned' => 1, 'points_possible' => 1, 'passed' => true,
        ]);

        $this->actingAs($learner)->postJson('/api/progress/unlock', $payload)
            ->assertOk()->assertJsonPath('max_unlocked_index', 3);
    }

    private function learningTopic(): array
    {
        $learner = User::factory()->create(['role' => 'student', 'is_admin' => false]);
        $course = Course::create(['title' => 'Secure Review', 'approval_status' => 'approved', 'is_published' => true]);
        $batch = CourseBatch::create(['course_id' => $course->id, 'name' => 'Batch 1', 'code' => 'SEC-B1', 'status' => 'open']);
        CourseEnrollment::create(['user_id' => $learner->id, 'batch_id' => $batch->id, 'status' => 'active', 'enrolled_at' => now()]);
        $topic = Topic::create(['course_id' => $course->id, 'title' => 'Policy', 'status' => 'approved', 'sort_order' => 1]);
        $first = Subtopic::create(['topic_id' => $topic->id, 'title' => 'First', 'content_type' => 'subtopic', 'documentation_path' => '/storage/first.pdf', 'status' => 'approved', 'sort_order' => 1]);
        $second = Subtopic::create(['topic_id' => $topic->id, 'title' => 'Second', 'content_type' => 'subtopic', 'documentation_path' => '/storage/second.pdf', 'status' => 'approved', 'sort_order' => 2]);

        return [$learner, $topic, $first, $second];
    }
}
