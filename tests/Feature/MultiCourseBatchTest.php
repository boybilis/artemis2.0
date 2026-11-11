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

    public function test_batch_saves_an_international_conversion_but_bills_php(): void
    {
        \Illuminate\Support\Facades\Cache::forget('batch-fx:php:EUR');
        \Illuminate\Support\Facades\Http::fake(['api.frankfurter.dev/v2/rate/PHP/EUR'=>\Illuminate\Support\Facades\Http::response([
            'base'=>'PHP', 'quote'=>'EUR', 'rate'=>0.015, 'date'=>now()->toDateString(),
        ])]);
        $admin = User::factory()->create(['role'=>'assistant_admin', 'is_admin'=>false]);
        $course = Course::create(['title'=>'International review', 'is_published'=>true, 'approval_status'=>'approved']);
        $payload = ['name'=>'International batch', 'price'=>2500, 'status'=>'open', 'course_ids'=>[$course->id], 'international_currency'=>'EUR'];
        $this->actingAs($admin)->getJson('/admin/classes/batch-price-quote?currency=EUR&price=2500')->assertOk()->assertJsonPath('amount', 37.5);
        $this->postJson('/admin/classes/batches', $payload)->assertCreated();
        $batch = CourseBatch::where('name', 'International batch')->firstOrFail();
        $this->assertSame('EUR', $batch->internationalPrice()['currency']);
        $this->assertSame(37.5, $batch->internationalPrice()['amount']);
        $learner = User::factory()->create(['role'=>'student', 'country_code'=>'INTL']);
        $this->actingAs($learner)->getJson('/api/courses')->assertOk()
            ->assertJsonPath('courses.0.currency_code', 'EUR')->assertJsonPath('courses.0.billing_currency_code', 'PHP')
            ->assertJsonPath('courses.0.display_price', 37.5);
        $payload['international_currency'] = null;
        $this->actingAs($admin)->putJson('/admin/classes/batches/'.$batch->id, $payload)->assertOk();
        $this->assertNull($batch->fresh()->internationalPrice());
        $this->get('/admin/classes')->assertOk()->assertSee('Not Available');
        $this->actingAs($learner)->getJson('/api/courses')->assertJsonPath('courses.0.currency_code', 'PHP');
    }

    public function test_batch_class_days_support_multiple_selections_and_clearing(): void
    {
        $admin = User::factory()->create(['role'=>'assistant_admin', 'is_admin'=>false]);
        $course = Course::create(['title'=>'Nursing', 'is_published'=>true, 'approval_status'=>'approved']);
        $payload = ['name'=>'Weekday Batch','price'=>100,'status'=>'open','course_ids'=>[$course->id],'schedule_days_present'=>1,'schedule_days'=>['Friday','Monday','Wednesday']];
        $this->actingAs($admin)->post('/admin/classes/batches', $payload)->assertRedirect('/admin/classes');
        $batch = CourseBatch::where('name','Weekday Batch')->firstOrFail();
        $this->assertSame('M,W,F', $batch->schedule_day);
        $this->actingAs($admin)->get('/admin/classes')->assertOk()->assertSee('Weekly class sessions');
        $payload['schedule_days'] = ['Funday'];
        $this->actingAs($admin)->putJson('/admin/classes/batches/'.$batch->id, $payload)->assertUnprocessable();
        $this->assertSame('M,W,F', $batch->fresh()->schedule_day);
        unset($payload['schedule_days']);
        $this->actingAs($admin)->put('/admin/classes/batches/'.$batch->id, $payload)->assertRedirect('/admin/classes');
        $this->assertNull($batch->fresh()->schedule_day);
    }

    public function test_batch_sessions_can_have_different_times_and_can_be_cleared(): void
    {
        $admin = User::factory()->create(['role'=>'assistant_admin', 'is_admin'=>false]);
        $course = Course::create(['title'=>'Nursing', 'is_published'=>true, 'approval_status'=>'approved']);
        $payload = ['name'=>'Variable hours', 'price'=>100, 'status'=>'open', 'starts_at'=>'2030-01-16', 'ends_at'=>'2030-03-04', 'course_ids'=>[$course->id], 'class_sessions'=>[
            ['day'=>'Friday', 'start_time'=>'18:00', 'end_time'=>'20:00'],
            ['day'=>'Monday', 'start_time'=>'19:00', 'end_time'=>'21:00'],
            ['day'=>null, 'start_time'=>null, 'end_time'=>null],
        ]];
        $this->actingAs($admin)->postJson('/admin/classes/batches', $payload)->assertCreated();
        $batch = CourseBatch::where('name', 'Variable hours')->firstOrFail();
        $this->assertSame([$payload['class_sessions'][1], $payload['class_sessions'][0]], $batch->weeklySessions());
        $this->assertSame('M,F', $batch->schedule_day);
        $this->assertSame(['M', 'F'], array_column($batch->class_sessions, 'day'));
        $this->assertNull($batch->start_time);
        $this->assertSame('2030-01-16 00:00:00', $batch->starts_at->format('Y-m-d H:i:s'));
        $this->assertSame('2030-03-04 23:59:59', $batch->ends_at->format('Y-m-d H:i:s'));
        $this->get('/admin/classes')->assertOk()->assertSee('name="starts_at" type="date"', false)->assertSee('name="ends_at" type="date"', false)
            ->assertSee('Monday · 7:00 PM – 9:00 PM');
        $payload['class_sessions'][0]['end_time'] = '17:00';
        $this->putJson('/admin/classes/batches/'.$batch->id, $payload)->assertUnprocessable()->assertJsonValidationErrors('class_sessions.0.end_time');
        $payload['class_sessions'] = [];
        $this->putJson('/admin/classes/batches/'.$batch->id, $payload)->assertOk();
        $this->assertSame([], $batch->fresh()->weeklySessions());
    }

    public function test_adding_sessions_keeps_the_legacy_day_summary_within_the_database_limit(): void
    {
        $admin = User::factory()->create(['role'=>'assistant_admin', 'is_admin'=>false]);
        $course = Course::create(['title'=>'Review', 'is_published'=>true, 'approval_status'=>'approved']);
        $batch = CourseBatch::create(['course_id'=>$course->id, 'name'=>'Review batch', 'code'=>'REVIEW', 'price'=>100,
            'status'=>'open', 'schedule_day'=>'MWF', 'start_time'=>'19:00', 'end_time'=>'21:00']);
        $sessions = array_map(fn ($day) => ['day'=>$day, 'start_time'=>'19:00', 'end_time'=>'21:00'], CourseBatch::CLASS_DAYS);
        $this->actingAs($admin)->post('/admin/classes/batches/'.$batch->id, ['_method'=>'PUT', 'name'=>$batch->name,
            'code'=>$batch->code, 'price'=>100, 'status'=>'open', 'course_ids'=>[$course->id], 'class_sessions'=>$sessions],
            ['Accept'=>'application/json', 'X-Requested-With'=>'XMLHttpRequest'])->assertOk()->assertJsonPath('success', true);
        $this->assertSame($sessions, $batch->refresh()->weeklySessions());
        $this->assertSame('M,T,W,TH,F,Sa,Su', $batch->schedule_day);
        $this->assertSame(['M','T','W','TH','F','Sa','Su'], array_column($batch->class_sessions, 'day'));
        $this->assertLessThanOrEqual(30, strlen($batch->schedule_day));
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
