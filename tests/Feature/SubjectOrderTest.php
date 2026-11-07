<?php

namespace Tests\Feature;

use App\Models\{User, Course, Subject};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SubjectOrderTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_and_assistant_can_reorder_only_the_complete_current_course_list(): void
    {
        $course = Course::create(['title'=>'Review']);
        $first = Subject::create(['course_id'=>$course->id, 'title'=>'First', 'sort_order'=>1]);
        $second = Subject::create(['course_id'=>$course->id, 'title'=>'Second', 'sort_order'=>2]);
        $other = Course::create(['title'=>'Other']);
        $outside = Subject::create(['course_id'=>$other->id, 'title'=>'Outside', 'sort_order'=>7]);
        $url = route('admin.content.subjects.reorder', $course);
        foreach (['admin', 'assistant_admin'] as $role) {
            $this->actingAs(User::factory()->create(['role'=>$role, 'is_admin'=>false]));
            $this->get(route('admin.content.subjects', $course))->assertOk()->assertSee('class="subject-drag-handle"', false);
            $this->postJson($url, ['ordered_ids'=>[$second->id, $first->id]])->assertOk()->assertJsonPath('success', true);
            $this->assertSame(1, $second->fresh()->sort_order);
            $this->assertSame(2, $first->fresh()->sort_order);
            foreach ([[$first->id], [$first->id, $first->id], [$first->id, $outside->id], []] as $ids) {
                $this->postJson($url, ['ordered_ids'=>$ids])->assertUnprocessable();
            }
            $this->assertSame(7, $outside->fresh()->sort_order);
            $this->assertSame(1, $second->fresh()->sort_order);
        }
        $this->assertDatabaseCount('audit_logs', 2);
    }

    public function test_other_roles_cannot_reorder_subjects(): void
    {
        $course = Course::create(['title'=>'Review']);
        $subject = Subject::create(['course_id'=>$course->id, 'title'=>'First', 'sort_order'=>5]);
        foreach (['instructor', 'encoder', 'staff', 'learner'] as $role) {
            $this->actingAs(User::factory()->create(['role'=>$role, 'is_admin'=>false]));
            if (in_array($role, ['instructor', 'encoder'])) {
                $this->get(route('admin.content.subjects', $course))->assertOk()->assertDontSee('class="subject-drag-handle"', false);
            }
            $result = $this->postJson(route('admin.content.subjects.reorder', $course), ['ordered_ids'=>[$subject->id]]);
            $this->assertContains($result->status(), [403, 404]);
            $this->assertSame(5, $subject->fresh()->sort_order);
        }
    }
}
