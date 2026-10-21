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

    public function test_batch_class_days_support_multiple_selections_and_clearing(): void
    {
        $admin = User::factory()->create(['role'=>'assistant_admin', 'is_admin'=>false]);
        $course = Course::create(['title'=>'Nursing', 'is_published'=>true, 'approval_status'=>'approved']);
        $payload = ['name'=>'Weekday Batch','price'=>100,'status'=>'open','course_ids'=>[$course->id],'schedule_days_present'=>1,'schedule_days'=>['Friday','Monday','Wednesday']];
        $this->actingAs($admin)->post('/admin/classes/batches', $payload)->assertRedirect('/admin/classes');
        $batch = CourseBatch::where('name','Weekday Batch')->firstOrFail();
        $this->assertSame('Monday, Wednesday, Friday', $batch->schedule_day);
        $this->actingAs($admin)->get('/admin/classes')->assertOk()->assertSee('schedule_days[]', false);
        $payload['schedule_days'] = ['Funday'];
        $this->actingAs($admin)->putJson('/admin/classes/batches/'.$batch->id, $payload)->assertUnprocessable();
        $this->assertSame('Monday, Wednesday, Friday', $batch->fresh()->schedule_day);
        unset($payload['schedule_days']);
        $this->actingAs($admin)->put('/admin/classes/batches/'.$batch->id, $payload)->assertRedirect('/admin/classes');
        $this->assertNull($batch->fresh()->schedule_day);
    }

    public function test_administrator_can_create_one_batch_with_multiple_master_courses(): void
    {
        $admin = User::factory()->create(['role'=>'admin', 'is_admin'=>true]);
        $courses = collect([
            Course::create(['title'=>'NCLEX-RN', 'is_published'=>true, 'approval_status'=>'approved']),
            Course::create(['title'=>'PNLE', 'is_published'=>true, 'approval_status'=>'approved']),
        ]);
        $description = "NCLEX COMPLETE REVIEW COURSE\nwith INTENSIVE FINAL COACHING\nLive online via Zoom Classes plus Online Practice Tests";

        $this->actingAs($admin)->post('/admin/classes/batches', [
            'name'=>'OCTOBER 2026 (MWF) FULL', 'code'=>'OCT-2026-MWF-FULL',
            'schedule_day'=>'Monday, Wednesday, Friday', 'modality'=>'Live via Zoom',
            'description'=>$description, 'price'=>5999, 'status'=>'open', 'course_ids'=>$courses->pluck('id')->all(),
        ])->assertRedirect('/admin/classes');

        $batch = CourseBatch::where('code', 'OCTOBER-2026-MWF-FULL')->firstOrFail();
        $this->assertSame($description, $batch->description);
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
