<?php

namespace Tests\Feature;

use App\Models\Course;
use App\Models\PaymentTransaction;
use App\Models\TestBank;
use App\Models\TestBankEnrollment;
use App\Models\Subject;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class TestBankEnrollmentTest extends TestCase
{
    use RefreshDatabase;

    public function test_each_test_bank_belongs_to_a_course_and_activation_uses_its_access_duration(): void
    {
        Carbon::setTestNow('2026-09-23 09:00:00');
        $learner = User::factory()->create();
        $course = Course::create(['title' => 'DOH-HAAD']);
        $testBank = TestBank::create([
            'course_id' => $course->id,
            'title' => 'DOH-HAAD Test Bank',
            'code' => 'TB-DOH-001',
            'access_days' => 30,
            'status' => 'active',
        ]);

        $enrollment = TestBankEnrollment::activate($testBank, $learner);

        $this->assertTrue($enrollment->enrolled_at->equalTo(now()));
        $this->assertTrue($enrollment->expires_at->equalTo(now()->addDays(30)));
        $this->assertTrue($testBank->course->is($course));
        Carbon::setTestNow();
    }

    public function test_learner_feed_only_returns_active_unexpired_test_bank_access(): void
    {
        $learner = User::factory()->create();
        $course = Course::create(['title' => 'NCLEX Review']);
        $active = TestBank::create([
            'course_id' => $course->id,
            'title' => 'NCLEX Test Bank',
            'code' => 'TB-NCLEX-001',
            'access_days' => 60,
            'status' => 'active',
        ]);
        $expired = TestBank::create([
            'course_id' => $course->id,
            'title' => 'Expired Test Bank',
            'code' => 'TB-NCLEX-OLD',
            'access_days' => 10,
            'status' => 'active',
        ]);
        TestBankEnrollment::activate($active, $learner);
        TestBankEnrollment::create([
            'test_bank_id' => $expired->id,
            'user_id' => $learner->id,
            'status' => 'active',
            'enrolled_at' => now()->subDays(20),
            'expires_at' => now()->subDays(10),
        ]);

        $this->actingAs($learner)->getJson('/api/test-banks/enrolled')
            ->assertOk()
            ->assertJsonCount(1, 'testBanks')
            ->assertJsonPath('testBanks.0.title', 'NCLEX Test Bank')
            ->assertJsonPath('testBanks.0.course.title', 'NCLEX Review');
    }

    public function test_enrolled_test_bank_feed_requires_authentication(): void
    {
        $this->getJson('/api/test-banks/enrolled')->assertUnauthorized();
    }

    public function test_admin_can_create_a_test_bank_for_a_master_course(): void
    {
        $admin = User::factory()->create(['role' => 'admin', 'is_admin' => true]);
        $course = Course::create(['title' => 'PNLE Review']);

        $this->actingAs($admin)->post("/admin/content/courses/{$course->id}/test-banks", [
            'title' => 'PNLE Practice Bank',
            'code' => 'TB-PNLE-001',
            'description' => 'Independent practice catalog.',
            'price' => 1499,
            'access_days' => 45,
        ])->assertRedirect();

        $this->assertDatabaseHas('test_banks', [
            'course_id' => $course->id,
            'code' => 'TB-PNLE-001',
            'access_days' => 45,
            'status' => 'active',
            'created_by' => $admin->id,
        ]);
    }

    public function test_instructor_cannot_manage_paid_test_bank_catalogs(): void
    {
        $instructor = User::factory()->create(['role' => 'instructor', 'is_admin' => false]);
        $course = Course::create(['title' => 'NCLEX Review']);

        $this->actingAs($instructor)
            ->get("/admin/content/courses/{$course->id}/test-banks")
            ->assertNotFound();
    }

    public function test_catalog_returns_every_active_test_bank_without_date_filtering(): void
    {
        $learner = User::factory()->create();
        $course = Course::create(['title' => 'Civil Service Review']);
        TestBank::create(['course_id' => $course->id, 'title' => 'Available Bank', 'code' => 'TB-CS-001', 'price' => 500, 'access_days' => 30, 'status' => 'active']);
        TestBank::create(['course_id' => $course->id, 'title' => 'Draft Bank', 'code' => 'TB-CS-002', 'price' => 500, 'access_days' => 30, 'status' => 'draft']);
        TestBank::create(['course_id' => $course->id, 'title' => 'Future Bank', 'code' => 'TB-CS-003', 'price' => 500, 'access_days' => 30, 'status' => 'active', 'starts_at' => now()->addDay()]);

        $this->actingAs($learner)->getJson('/api/test-banks')
            ->assertOk()
            ->assertJsonCount(2, 'testBanks')
            ->assertJsonPath('testBanks.0.title', 'Available Bank')
            ->assertJsonPath('testBanks.0.course.title', 'Civil Service Review')
            ->assertJsonPath('testBanks.1.title', 'Future Bank');
    }

    public function test_admin_can_deactivate_and_reactivate_a_test_bank(): void
    {
        $admin = User::factory()->create(['role' => 'admin', 'is_admin' => true]);
        $course = Course::create(['title' => 'DOH Review']);
        $testBank = TestBank::create(['course_id' => $course->id, 'title' => 'DOH Bank', 'code' => 'TB-DOH-STATUS', 'price' => 500, 'access_days' => 30, 'status' => 'active']);

        $url = "/admin/content/courses/{$course->id}/test-banks/{$testBank->id}/status";
        $this->actingAs($admin)->post($url)->assertRedirect();
        $this->assertSame('closed', $testBank->fresh()->status);

        $this->actingAs($admin)->post($url)->assertRedirect();
        $this->assertSame('active', $testBank->fresh()->status);
    }

    public function test_test_bank_subscription_creates_a_separate_paymongo_checkout(): void
    {
        config()->set('services.paymongo.secret_key', 'sk_test_artemis');
        config()->set('services.paymongo.payment_methods', ['qrph']);
        Http::fake(['api.paymongo.com/v1/checkout_sessions' => Http::response([
            'data' => ['id' => 'cs_test_bank', 'attributes' => ['checkout_url' => 'https://checkout.paymongo.com/test-bank']],
        ], 200)]);
        $learner = User::factory()->create();
        $course = Course::create(['title' => 'NCLEX Review']);
        $testBank = TestBank::create(['course_id' => $course->id, 'title' => 'NCLEX Bank', 'code' => 'TB-PAY-001', 'price' => 3000, 'access_days' => 30, 'status' => 'active']);

        $this->actingAs($learner)->postJson("/api/test-banks/{$testBank->id}/buy")
            ->assertOk()
            ->assertJsonPath('checkout_url', 'https://checkout.paymongo.com/test-bank');

        Http::assertSent(fn ($request) => $request['data']['attributes']['payment_method_types'] === ['qrph']
            && $request['data']['attributes']['line_items'][0]['amount'] === 300000);
        $this->assertDatabaseHas('payment_transactions', [
            'user_id' => $learner->id,
            'test_bank_id' => $testBank->id,
            'batch_id' => null,
            'status' => 'pending_payment',
        ]);
        $this->assertDatabaseCount('test_bank_enrollments', 0);
    }

    public function test_zero_priced_test_bank_is_rejected_before_contacting_paymongo(): void
    {
        config()->set('services.paymongo.secret_key', 'sk_test_artemis');
        Http::fake();
        $learner = User::factory()->create();
        $course = Course::create(['title' => 'NCLEX Review']);
        $testBank = TestBank::create(['course_id' => $course->id, 'title' => 'NCLEX Bank', 'code' => 'TB-ZERO', 'price' => 0, 'access_days' => 30, 'status' => 'active']);

        $this->actingAs($learner)->postJson("/api/test-banks/{$testBank->id}/buy")
            ->assertStatus(422)
            ->assertJsonPath('message', 'Test Bank subscription is unavailable because the administrator has not configured a valid price yet.');

        Http::assertNothingSent();
        $this->assertDatabaseCount('payment_transactions', 0);
    }

    public function test_paymongo_test_bank_validation_detail_is_returned_to_the_learner(): void
    {
        config()->set('services.paymongo.secret_key', 'sk_test_artemis');
        Http::fake(['api.paymongo.com/v1/checkout_sessions' => Http::response([
            'errors' => [['code' => 'parameter_below_minimum', 'detail' => 'The amount is below the allowed minimum.']],
        ], 400)]);
        $learner = User::factory()->create();
        $course = Course::create(['title' => 'NCLEX Review']);
        $testBank = TestBank::create(['course_id' => $course->id, 'title' => 'NCLEX Bank', 'code' => 'TB-REJECT', 'price' => 1, 'access_days' => 30, 'status' => 'active']);

        $this->actingAs($learner)->postJson("/api/test-banks/{$testBank->id}/buy")
            ->assertStatus(422)
            ->assertJsonPath('message', 'PayMongo rejected the Test Bank checkout: The amount is below the allowed minimum.');
    }

    public function test_paid_test_bank_webhook_activates_only_timed_test_bank_access_once(): void
    {
        Carbon::setTestNow('2026-09-24 08:00:00');
        config()->set('services.paymongo.secret_key', 'sk_test_artemis');
        config()->set('services.paymongo.webhook_secret', 'whsk_test_artemis');
        config()->set('services.paymongo.webhook_tolerance', 300);
        $learner = User::factory()->create();
        $course = Course::create(['title' => 'PNLE Review']);
        $testBank = TestBank::create(['course_id' => $course->id, 'title' => 'PNLE Bank', 'code' => 'TB-PAY-002', 'price' => 2000, 'access_days' => 45, 'status' => 'active']);
        $transaction = PaymentTransaction::create([
            'user_id' => $learner->id, 'test_bank_id' => $testBank->id, 'reference' => 'ART2TB-WEBHOOK',
            'amount' => 2000, 'currency' => 'PHP', 'provider' => 'paymongo', 'status' => 'pending_payment',
            'provider_checkout_id' => 'cs_test_bank_paid',
        ]);
        $payload = json_encode(['data' => ['id' => 'evt_tb', 'attributes' => [
            'type' => 'checkout_session.payment.paid',
            'data' => ['id' => 'cs_test_bank_paid', 'type' => 'checkout_session', 'attributes' => [
                'reference_number' => $transaction->reference,
                'payments' => [['id' => 'pay_tb', 'attributes' => ['status' => 'paid']]],
            ]],
        ]]], JSON_UNESCAPED_SLASHES);
        $timestamp = time();
        $signature = hash_hmac('sha256', $timestamp . '.' . $payload, 'whsk_test_artemis');
        $server = ['CONTENT_TYPE' => 'application/json', 'HTTP_PAYMONGO_SIGNATURE' => "t={$timestamp},te={$signature}"];

        $this->call('POST', '/api/payments/paymongo/webhook', [], [], [], $server, $payload)->assertOk();
        $this->call('POST', '/api/payments/paymongo/webhook', [], [], [], $server, $payload)->assertOk();

        $this->assertDatabaseHas('payment_transactions', ['id' => $transaction->id, 'status' => 'paid', 'provider_payment_id' => 'pay_tb']);
        $enrollment = TestBankEnrollment::where('user_id', $learner->id)->where('test_bank_id', $testBank->id)->firstOrFail();
        $this->assertTrue($enrollment->expires_at->equalTo(now()->addDays(45)));
        $this->assertDatabaseCount('test_bank_enrollments', 1);
        $this->assertDatabaseCount('course_enrollments', 0);
        $this->assertDatabaseCount('vouchers', 0);
        Carbon::setTestNow();
    }

    public function test_enrolled_learner_can_open_course_linked_test_bank_workspace(): void
    {
        $learner = User::factory()->create();
        $course = Course::create(['title' => 'NCLEX Review']);
        $subject = Subject::create(['course_id' => $course->id, 'subject_code' => 'NUR-101', 'title' => 'Fundamentals of Nursing', 'status' => 'approved']);
        $testBank = TestBank::create(['course_id' => $course->id, 'title' => 'NCLEX Bank', 'code' => 'TB-WORKSPACE', 'price' => 3000, 'access_days' => 30, 'status' => 'active']);
        TestBankEnrollment::activate($testBank, $learner);

        $this->actingAs($learner)->getJson("/api/test-banks/{$testBank->id}/workspace")
            ->assertOk()
            ->assertJsonPath('workspace.title', 'NCLEX Bank')
            ->assertJsonPath('workspace.courseTitle', 'NCLEX Review')
            ->assertJsonPath('workspace.subjects.0.id', $subject->id)
            ->assertJsonPath('workspace.subjects.0.title', 'Fundamentals of Nursing')
            ->assertJsonPath('workspace.subjects.0.questionCount', 0);
    }

    public function test_workspace_rejects_a_learner_without_active_test_bank_access(): void
    {
        $learner = User::factory()->create();
        $course = Course::create(['title' => 'PNLE Review']);
        $testBank = TestBank::create(['course_id' => $course->id, 'title' => 'PNLE Bank', 'code' => 'TB-NO-ACCESS', 'price' => 2000, 'access_days' => 30, 'status' => 'active']);

        $this->actingAs($learner)->getJson("/api/test-banks/{$testBank->id}/workspace")->assertNotFound();
    }

    public function test_renewal_adds_access_days_after_the_current_expiration(): void
    {
        Carbon::setTestNow('2026-09-24 08:00:00');
        $learner = User::factory()->create();
        $course = Course::create(['title' => 'HAAD Review']);
        $testBank = TestBank::create(['course_id' => $course->id, 'title' => 'HAAD Bank', 'code' => 'TB-RENEW', 'price' => 2000, 'access_days' => 30, 'status' => 'active']);
        $first = TestBankEnrollment::activate($testBank, $learner);
        $firstExpiration = $first->expires_at->copy();
        Carbon::setTestNow(now()->addDays(5));

        $renewed = TestBankEnrollment::activate($testBank, $learner);

        $this->assertTrue($renewed->expires_at->equalTo($firstExpiration->addDays(30)));
        Carbon::setTestNow();
    }
}
