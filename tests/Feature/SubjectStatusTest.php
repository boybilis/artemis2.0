<?php

namespace Tests\Feature;

use App\Models\{User, Course, CourseBatch, CourseEnrollment, Subject, Topic};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SubjectStatusTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_and_assistant_can_change_status_and_pending_hides_subject_and_sections(): void
    {
        $admin = User::factory()->create(['role'=>'admin', 'is_admin'=>true]);
        $assistant = User::factory()->create(['role'=>'assistant_admin', 'is_admin'=>false]);
        $learner = User::factory()->create(['role'=>'learner', 'is_admin'=>false]);
        $course = Course::create(['title'=>'Review', 'approval_status'=>'approved', 'is_published'=>true]);
        $subject = Subject::create(['course_id'=>$course->id, 'title'=>'Developing Subject', 'status'=>'approved']);
        $topic = Topic::create(['course_id'=>$course->id, 'subject_id'=>$subject->id, 'title'=>'Section', 'status'=>'approved']);
        $batch = CourseBatch::create(['course_id'=>$course->id, 'name'=>'Class', 'code'=>'CLASS', 'status'=>'open']);
        $batch->courses()->sync([$course->id]);
        CourseEnrollment::create(['user_id'=>$learner->id, 'batch_id'=>$batch->id, 'status'=>'active', 'enrolled_at'=>now(), 'expires_at'=>now()->addMonth()]);
        $url = route('admin.content.subjects.status', [$course, $subject]);
        $learnerUrl = '/api/courses/'.$course->id.'/topics?batch_id='.$batch->id;
        $this->actingAs($learner)->getJson($learnerUrl)->assertOk()
            ->assertJsonFragment(['title'=>'Developing Subject'])->assertJsonFragment(['title'=>'Section']);
        foreach ([$admin, $assistant] as $manager) {
            $this->actingAs($manager)->get(route('admin.content.subjects', $course))
                ->assertOk()->assertSee('class="status subject-status-toggle', false);
            $this->post($url, ['status'=>'pending'])->assertRedirect()->assertSessionHas('success');
            $this->assertSame('pending', $subject->fresh()->status);
            $this->actingAs($learner)->getJson($learnerUrl)->assertOk()
                ->assertJsonMissing(['title'=>'Developing Subject'])->assertJsonMissing(['title'=>'Section']);
            $this->actingAs($manager)->post($url, ['status'=>'approved'])->assertRedirect();
            $this->actingAs($learner)->getJson($learnerUrl)->assertOk()
                ->assertJsonFragment(['title'=>'Developing Subject'])->assertJsonFragment(['title'=>'Section']);
        }
        $this->assertSame('approved', $topic->fresh()->status);
        $this->assertDatabaseCount('topics', 1);
        $this->assertDatabaseCount('audit_logs', 4);
        $this->actingAs($assistant)->postJson($url, ['status'=>'pending'])->assertOk()
            ->assertJsonPath('success', true)->assertJsonPath('status', 'pending')
            ->assertJsonPath('message', 'Subject set to pending and hidden from learners.');
        $this->assertSame('pending', $subject->fresh()->status);
        $this->postJson($url, ['status'=>'approved'])->assertOk()->assertJsonPath('status', 'approved');
        $this->actingAs($admin)->postJson($url, ['status'=>'invalid'])->assertUnprocessable();
        $otherCourse = Course::create(['title'=>'Other']);
        $this->post(route('admin.content.subjects.status', [$otherCourse, $subject]), ['status'=>'pending'])->assertNotFound();
        $this->assertSame('approved', $subject->fresh()->status);
    }

    public function test_other_roles_cannot_change_subject_status(): void
    {
        $course = Course::create(['title'=>'Review']);
        $subject = Subject::create(['course_id'=>$course->id, 'title'=>'Subject', 'status'=>'approved']);
        foreach (['instructor', 'encoder', 'staff', 'learner'] as $role) {
            $user = User::factory()->create(['role'=>$role, 'is_admin'=>false]);
            $this->actingAs($user);
            if (in_array($role, ['instructor', 'encoder'])) {
                $this->get(route('admin.content.subjects', $course))->assertOk()
                    ->assertDontSee('class="status subject-status-toggle', false);
            }
            $response = $this->post(route('admin.content.subjects.status', [$course, $subject]), ['status'=>'pending']);
            $this->assertContains($response->status(), [403, 404]);
            $this->assertSame('approved', $subject->fresh()->status);
        }
    }
}
