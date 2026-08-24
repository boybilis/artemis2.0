<?php

namespace Tests\Feature;

use App\Models\Course;
use App\Models\CourseEnrollment;
use App\Models\Topic;
use App\Models\User;
use App\Models\Voucher;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CourseEnrollmentTest extends TestCase
{
    use RefreshDatabase;

    public function test_only_the_paid_course_is_unlocked(): void
    {
        $user = User::factory()->create();
        $paid = Course::create(['title' => 'PNLE Review', 'price' => 499, 'access_days' => 30, 'is_published' => true]);
        $locked = Course::create(['title' => 'NCLEX Review', 'price' => 999, 'access_days' => 30, 'is_published' => true]);
        Topic::create(['course_id' => $paid->id, 'title' => 'PNLE Module', 'sort_order' => 1, 'status' => 'approved']);
        Topic::create(['course_id' => $locked->id, 'title' => 'NCLEX Module', 'sort_order' => 1, 'status' => 'approved']);
        CourseEnrollment::create(['user_id' => $user->id, 'course_id' => $paid->id, 'status' => 'active', 'enrolled_at' => now(), 'expires_at' => now()->addDays(30)]);

        $this->actingAs($user)->getJson("/api/courses/{$paid->id}/topics")->assertOk();
        $this->actingAs($user)->getJson("/api/courses/{$locked->id}/topics")->assertForbidden();

        $courses = $this->actingAs($user)->getJson('/api/courses')->assertOk()->json('courses');
        $this->assertTrue(collect($courses)->firstWhere('id', $paid->id)['is_enrolled']);
        $this->assertFalse(collect($courses)->firstWhere('id', $locked->id)['is_enrolled']);
    }

    public function test_redeeming_a_voucher_enrolls_only_its_course(): void
    {
        $user = User::factory()->create();
        $course = Course::create(['title' => 'Civil Service Review', 'price' => 399, 'access_days' => 45, 'is_published' => true]);
        $voucher = Voucher::create(['course_id' => $course->id, 'code' => 'ART2-TEST-0001', 'price' => 399, 'duration_days' => 45, 'used' => false, 'status' => 'active']);

        $this->actingAs($user)->postJson('/api/voucher/redeem', ['code' => $voucher->code, 'course_id' => $course->id])
            ->assertOk()->assertJsonPath('courseId', $course->id);

        $this->assertTrue($user->fresh()->hasActiveEnrollment($course->id));
    }
}
