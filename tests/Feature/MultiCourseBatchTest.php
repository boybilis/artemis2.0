<?php

namespace Tests\Feature;

use App\Models\Course;
use App\Models\CourseBatch;
use App\Models\CourseEnrollment;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MultiCourseBatchTest extends TestCase
{
    use RefreshDatabase;

    public function test_administrator_can_create_one_batch_with_multiple_master_courses(): void
    {
        $admin = User::factory()->create(['role'=>'admin', 'is_admin'=>true]);
        $courses = collect([
            Course::create(['title'=>'NCLEX-RN', 'is_published'=>true, 'approval_status'=>'approved']),
            Course::create(['title'=>'PNLE', 'is_published'=>true, 'approval_status'=>'approved']),
        ]);

        $this->actingAs($admin)->post('/admin/classes/batches', [
            'name'=>'OCTOBER 2026 (MWF) FULL', 'code'=>'OCT-2026-MWF-FULL',
            'schedule_day'=>'Monday, Wednesday, Friday', 'modality'=>'Live via Zoom',
            'price'=>5999, 'status'=>'open', 'course_ids'=>$courses->pluck('id')->all(),
        ])->assertRedirect('/admin/classes');

        $batch = CourseBatch::where('code', 'OCTOBER-2026-MWF-FULL')->firstOrFail();
        $this->assertEqualsCanonicalizing($courses->pluck('id')->all(), $batch->courses()->pluck('courses.id')->all());

        $this->actingAs($admin)->post('/admin/classes/batches', [
            'name'=>'OCTOBER 2026 (MWF) FULL', 'schedule_day'=>'Monday, Wednesday, Friday',
            'modality'=>'Live via Zoom', 'price'=>5999, 'status'=>'open',
            'course_ids'=>$courses->pluck('id')->all(),
        ])->assertRedirect('/admin/classes');
        $this->assertDatabaseHas('course_batches', ['code'=>'OCTOBER-2026-MWF-FULL-2']);
    }

    public function test_one_batch_enrollment_unlocks_every_assigned_master_course(): void
    {
        $learner = User::factory()->create();
        $courses = collect([
            Course::create(['title'=>'NCLEX-RN', 'is_published'=>true, 'approval_status'=>'approved']),
            Course::create(['title'=>'PNLE', 'is_published'=>true, 'approval_status'=>'approved']),
        ]);
        $batch = CourseBatch::create(['course_id'=>$courses->first()->id, 'name'=>'October Full', 'code'=>'OCT-FULL', 'price'=>5999, 'status'=>'open']);
        $batch->courses()->sync($courses->pluck('id'));
        CourseEnrollment::create(['user_id'=>$learner->id, 'batch_id'=>$batch->id, 'status'=>'active', 'enrolled_at'=>now(), 'expires_at'=>now()->addMonth()]);

        $response = $this->actingAs($learner)->getJson('/api/courses')->assertOk();
        $entries = collect($response->json('courses'))->where('batch_id', $batch->id);

        $this->assertCount(2, $entries);
        $this->assertTrue($entries->every(fn ($entry) => $entry['is_enrolled'] === true));
        $this->assertEqualsCanonicalizing($courses->pluck('id')->all(), $entries->pluck('id')->all());
    }
}
