<?php

namespace Tests\Feature;

use App\Models\Course;
use App\Models\CourseBatch;
use App\Models\CourseEnrollment;
use App\Models\User;
use App\Models\Voucher;
use App\Models\PaymentTransaction;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class PaymongoPaymentTest extends TestCase
{
    use RefreshDatabase;

    public function test_checkout_requests_qrph_for_the_selected_batch_price(): void
    {
        config()->set('services.paymongo.secret_key', 'sk_test_artemis');
        config()->set('services.paymongo.payment_methods', ['qrph']);
        Http::fake([
            'api.paymongo.com/v1/checkout_sessions' => Http::response([
                'data' => [
                    'id' => 'cs_test_123',
                    'attributes' => ['checkout_url' => 'https://checkout.paymongo.com/test/123'],
                ],
            ], 200),
        ]);

        [$user, $batch] = $this->learnerAndBatch();
        $this->actingAs($user)->postJson('/api/voucher/buy', ['batch_id' => $batch->id])
            ->assertOk()
            ->assertJsonPath('checkout_url', 'https://checkout.paymongo.com/test/123');

        Http::assertSent(fn ($request) =>
            $request->url() === 'https://api.paymongo.com/v1/checkout_sessions'
            && $request['data']['attributes']['payment_method_types'] === ['qrph']
            && $request['data']['attributes']['line_items'][0]['amount'] === 125000
        );
        $this->assertDatabaseHas('payment_transactions', [
            'batch_id' => $batch->id,
            'provider' => 'paymongo',
            'provider_checkout_id' => 'cs_test_123',
            'status' => 'pending_payment',
        ]);
        $this->assertDatabaseCount('vouchers', 0);
    }

    public function test_valid_paid_webhook_activates_only_its_batch_enrollment(): void
    {
        Mail::fake();
        config()->set('services.paymongo.secret_key', 'sk_test_artemis');
        config()->set('services.paymongo.webhook_secret', 'whsk_test_artemis');
        config()->set('services.paymongo.webhook_tolerance', 300);
        [$user, $batch] = $this->learnerAndBatch();
        $transaction = PaymentTransaction::create([
            'user_id' => $user->id,
            'batch_id' => $batch->id,
            'reference' => 'ART2PAY-QRPH-TEST',
            'amount' => $batch->price,
            'currency' => 'PHP',
            'provider' => 'paymongo',
            'status' => 'pending_payment',
            'provider_checkout_id' => 'cs_test_paid',
        ]);
        $payload = json_encode([
            'data' => [
                'id' => 'evt_test_paid',
                'attributes' => [
                    'type' => 'checkout_session.payment.paid',
                    'data' => [
                        'id' => 'cs_test_paid',
                        'type' => 'checkout_session',
                        'attributes' => [
                            'reference_number' => $transaction->reference,
                            'payments' => [['id' => 'pay_test_paid', 'attributes' => ['status' => 'paid']]],
                        ],
                    ],
                ],
            ],
        ], JSON_UNESCAPED_SLASHES);
        $timestamp = time();
        $signature = hash_hmac('sha256', $timestamp . '.' . $payload, 'whsk_test_artemis');

        $this->call('POST', '/api/payments/paymongo/webhook', [], [], [], [
            'CONTENT_TYPE' => 'application/json',
            'HTTP_PAYMONGO_SIGNATURE' => "t={$timestamp},te={$signature}",
        ], $payload)->assertOk()->assertJson(['received' => true]);

        $this->assertDatabaseHas('payment_transactions', ['id' => $transaction->id, 'status' => 'paid', 'provider_payment_id' => 'pay_test_paid']);
        $voucher = Voucher::where('provider_checkout_id', 'cs_test_paid')->firstOrFail();
        $this->assertTrue($voucher->used);
        $this->assertSame('Paid / Enrolled', $voucher->statusLabel());
        $this->assertDatabaseHas('course_enrollments', ['user_id' => $user->id, 'batch_id' => $batch->id, 'status' => 'active']);
        $this->assertSame(1, CourseEnrollment::where('user_id', $user->id)->count());
    }

    private function learnerAndBatch(): array
    {
        $user = User::factory()->create();
        $course = Course::create([
            'title' => 'DOH-HAAD',
            'description' => 'Review course',
            'is_published' => true,
            'approval_status' => 'approved',
        ]);
        $batch = CourseBatch::create([
            'course_id' => $course->id,
            'name' => 'DOH-HAAD Batch 1',
            'code' => 'HAAD-B1',
            'price' => 1250,
            'status' => 'open',
            'ends_at' => now()->addMonth(),
        ]);

        return [$user, $batch];
    }
}
