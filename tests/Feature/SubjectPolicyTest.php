<?php

namespace Tests\Feature;

use App\Models\{User, Course, CourseBatch, CourseEnrollment, Subject, Topic, Subtopic};
use App\Services\SubjectPolicy;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\{DB, Storage};
use Tests\TestCase;

class SubjectPolicyTest extends TestCase
{
    use RefreshDatabase;
    use \Tests\Concerns\CreatesPolicyTemplate;

    public function test_new_subject_gets_a_policy_and_independent_copy_of_existing_agreement(): void
    {
        Storage::fake('public');
        $this->createPolicyTemplate();
        $template = Subtopic::firstOrFail();
        Storage::disk('public')->put('documentation/agreement.pdf', '%PDF-1.4 agreement');
        $template->update(['documentation_path'=>'/storage/documentation/agreement.pdf']);
        $course = Course::create(['title'=>'New Course']);
        $admin = User::factory()->create(['role'=>'admin', 'is_admin'=>true]);
        $this->actingAs($admin)->post(route('admin.content.subjects.store', $course), ['subject_code'=>'NEW', 'title'=>'New Subject'])
            ->assertSessionHasNoErrors()->assertSessionHas('success');
        $subject = $course->subjects()->firstOrFail();
        $policy = $subject->topics()->firstOrFail();
        $copy = $policy->subtopics()->firstOrFail();
        $this->assertSame('Policy', $policy->title);
        $this->assertSame(0, $policy->sort_order);
        $this->assertSame('End User Agreement', $copy->title);
        $this->assertNotSame($template->documentation_path, $copy->documentation_path);
        Storage::disk('public')->assertExists(ltrim(str_replace('/storage/', '', $copy->documentation_path), '/'));
        $this->assertSame('%PDF-1.4 agreement', Storage::disk('public')->get(ltrim(str_replace('/storage/', '', $copy->documentation_path), '/')));
    }

    public function test_backfill_is_idempotent_and_preserves_existing_sections_and_contents(): void
    {
        $this->createPolicyTemplate();
        $course = Course::create(['title'=>'Old Course']);
        $subject = Subject::create(['course_id'=>$course->id, 'title'=>'Existing']);
        $lesson = Topic::create(['course_id'=>$course->id, 'subject_id'=>$subject->id, 'title'=>'Existing Lecture', 'sort_order'=>5]);
        $migration = require database_path('migrations/2026_11_07_000001_add_default_policy_to_existing_subjects.php');
        $migration->up(); $migration->up();
        $this->assertSame(2, $subject->topics()->count());
        $this->assertSame(5, $lesson->fresh()->sort_order);
        $policy = app(SubjectPolicy::class)->policy($subject->id);
        $this->assertSame(1, $policy->subtopics()->count());
        $this->assertDatabaseCount('subject_policy_acceptances', 0);
    }

    public function test_consent_is_checked_saved_once_per_subject_and_not_shared_with_other_learners(): void
    {
        $this->createPolicyTemplate();
        $user = User::factory()->create();
        $course = Course::create(['title'=>'Review', 'approval_status'=>'approved', 'is_published'=>true]);
        $batch = CourseBatch::create(['course_id'=>$course->id, 'name'=>'Batch', 'code'=>'POLICY', 'status'=>'open']);
        $batch->courses()->sync([$course->id]);
        CourseEnrollment::create(['user_id'=>$user->id, 'batch_id'=>$batch->id, 'status'=>'active', 'enrolled_at'=>now()]);
        $subject = Subject::create(['course_id'=>$course->id, 'title'=>'Subject A', 'status'=>'approved']);
        app(SubjectPolicy::class)->ensureDefault($subject);
        $policy = app(SubjectPolicy::class)->policy($subject->id);
        $doc = $policy->subtopics()->firstOrFail();
        $lecture = Topic::create(['course_id'=>$course->id, 'subject_id'=>$subject->id, 'title'=>'Lecture', 'status'=>'approved']);
        $video = Subtopic::create(['topic_id'=>$lecture->id, 'title'=>'Video', 'content_type'=>'subtopic', 'video_url'=>'https://example.test/video', 'status'=>'approved']);
        $accept = "/api/courses/{$course->id}/subjects/{$subject->id}/policy/accept";
        $topics = "/api/courses/{$course->id}/topics?batch_id={$batch->id}";
        $this->actingAs($user);
        $this->postJson($accept, ['agreed'=>true, 'batch_id'=>$batch->id])->assertUnprocessable();
        $this->postJson('/api/progress/start', ['topic_id'=>$lecture->id])->assertForbidden();
        $this->postJson('/api/progress/unlock', ['topic_id'=>$lecture->id, 'subtopic_id'=>$video->id, 'item_type'=>'video'])->assertForbidden();
        $this->postJson('/api/progress/unlock', ['topic_id'=>$policy->id, 'subtopic_id'=>$doc->id, 'item_type'=>'doc'])->assertOk();
        $this->postJson($accept, ['agreed'=>false])->assertUnprocessable();
        $this->postJson($accept, [])->assertUnprocessable();
        $this->assertDatabaseCount('subject_policy_acceptances', 0);
        $this->getJson($topics)->assertOk()->assertJsonPath('subjects.0.policyAccepted', false);
        $this->postJson($accept, ['agreed'=>true, 'batch_id'=>$batch->id])->assertOk()->assertJsonPath('policyAccepted', true);
        $firstTime = DB::table('subject_policy_acceptances')->value('accepted_at');
        $this->postJson($accept, ['agreed'=>true, 'batch_id'=>$batch->id])->assertOk();
        $this->assertDatabaseCount('subject_policy_acceptances', 1);
        $this->assertSame($firstTime, DB::table('subject_policy_acceptances')->value('accepted_at'));
        $this->getJson($topics)->assertOk()->assertJsonPath('subjects.0.policyAccepted', true);
        $this->postJson('/api/progress/start', ['topic_id'=>$lecture->id])->assertOk();
        $other = Subject::create(['course_id'=>$course->id, 'title'=>'Subject B', 'status'=>'approved']);
        app(SubjectPolicy::class)->ensureDefault($other);
        $this->assertFalse(app(SubjectPolicy::class)->accepted($user->id, $other->id));
        $this->assertFalse(app(SubjectPolicy::class)->accepted(User::factory()->create()->id, $subject->id));
        $this->postJson("/api/courses/999/subjects/{$subject->id}/policy/accept", ['agreed'=>true])->assertNotFound();
        $this->actingAs(User::factory()->create())->postJson($accept, ['agreed'=>true])->assertNotFound();
    }

    public function test_management_roles_can_still_add_policy_contents(): void
    {
        $this->createPolicyTemplate();
        $policy = Topic::firstOrFail();
        foreach (['admin', 'assistant_admin', 'encoder'] as $role) {
            $user = User::factory()->create(['role'=>$role, 'is_admin'=>false]);
            $this->actingAs($user)->post(route('admin.content.subtopics.store', $policy->course_id), [
                'topic_id'=>$policy->id, 'content_type'=>'subtopic', 'title'=>'Additional Policy '.$role,
            ])->assertSessionHasNoErrors()->assertRedirect();
            $this->assertDatabaseHas('subtopics', ['topic_id'=>$policy->id, 'title'=>'Additional Policy '.$role]);
        }
    }
}
