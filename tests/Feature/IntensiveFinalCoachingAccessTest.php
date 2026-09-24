<?php

namespace Tests\Feature;

use App\Models\Course;
use App\Models\CourseBatch;
use App\Models\CourseEnrollment;
use App\Models\Subject;
use App\Models\Topic;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class IntensiveFinalCoachingAccessTest extends TestCase
{
    use RefreshDatabase;

    private function learningSetup(bool $includesCoaching): array
    {
        $learner = User::factory()->create();
        $course = Course::create(['title' => 'NCLEX Complete Review', 'approval_status' => 'approved', 'is_published' => true]);
        $batch = CourseBatch::create([
            'course_id' => $course->id, 'name' => 'Review Batch', 'code' => 'REVIEW-'.$includesCoaching,
            'status' => 'open', 'includes_intensive_final_coaching' => $includesCoaching,
        ]);
        CourseEnrollment::create(['user_id' => $learner->id, 'batch_id' => $batch->id, 'status' => 'active', 'enrolled_at' => now()]);
        $regular = Subject::create(['course_id' => $course->id, 'subject_code' => 'REG', 'title' => 'Regular Review', 'status' => 'approved']);
        $intensive = Subject::create(['course_id' => $course->id, 'subject_code' => 'IFC', 'title' => 'Intensive Final Coaching', 'status' => 'approved', 'is_intensive_final_coaching' => true]);
        Topic::create(['course_id' => $course->id, 'subject_id' => $regular->id, 'title' => 'Regular Topic', 'status' => 'approved']);
        Topic::create(['course_id' => $course->id, 'subject_id' => $intensive->id, 'title' => 'Coaching Topic', 'status' => 'approved']);
        return compact('learner', 'course');
    }

    public function test_regular_batch_hides_intensive_final_coaching_subjects_and_topics(): void
    {
        extract($this->learningSetup(false));
        $this->actingAs($learner)->getJson("/api/courses/{$course->id}/topics")
            ->assertOk()->assertJsonCount(1, 'subjects')->assertJsonPath('subjects.0.code', 'REG')
            ->assertJsonCount(1, 'topics')->assertJsonPath('topics.0.title', 'Regular Topic');
        $courseCard = collect($this->actingAs($learner)->getJson('/api/courses')->assertOk()->json('courses'))
            ->first(fn ($entry) => (int) $entry['id'] === $course->id && $entry['is_enrolled']);
        $this->assertSame(1, $courseCard['subject_count']);
        $this->assertSame(1, $courseCard['topic_count']);
        $this->assertFalse($courseCard['batch_includes_intensive_final_coaching']);
    }

    public function test_entitled_batch_shows_intensive_final_coaching_subjects_and_topics(): void
    {
        extract($this->learningSetup(true));
        $response = $this->actingAs($learner)->getJson("/api/courses/{$course->id}/topics")->assertOk();
        $this->assertEqualsCanonicalizing(['REG', 'IFC'], collect($response->json('subjects'))->pluck('code')->all());
        $this->assertEqualsCanonicalizing(['Regular Topic', 'Coaching Topic'], collect($response->json('topics'))->pluck('title')->all());
        $courseCard = collect($this->actingAs($learner)->getJson('/api/courses')->assertOk()->json('courses'))
            ->first(fn ($entry) => (int) $entry['id'] === $course->id && $entry['is_enrolled']);
        $this->assertTrue($courseCard['batch_includes_intensive_final_coaching']);
    }

    public function test_admin_can_tag_subject_and_batch_for_intensive_final_coaching(): void
    {
        $admin = User::factory()->create(['role' => 'admin', 'is_admin' => true]);
        $course = Course::create(['title' => 'NCLEX', 'approval_status' => 'approved', 'is_published' => true]);
        $this->actingAs($admin)->post(route('admin.content.subjects.store', $course), [
            'subject_code' => 'IFC', 'title' => 'Final Coaching', 'is_intensive_final_coaching' => '1',
        ])->assertSessionHas('success');
        $this->actingAs($admin)->post(route('admin.classes.batches.store'), [
            'name' => 'Full Review', 'price' => 5000, 'status' => 'open', 'course_ids' => [$course->id],
            'includes_intensive_final_coaching' => '1',
        ])->assertSessionHas('success');
        $this->assertDatabaseHas('subjects', ['course_id' => $course->id, 'is_intensive_final_coaching' => true]);
        $this->assertDatabaseHas('course_batches', ['includes_intensive_final_coaching' => true]);
    }
}
