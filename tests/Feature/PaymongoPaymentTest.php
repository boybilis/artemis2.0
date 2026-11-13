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
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class PaymongoPaymentTest extends TestCase
{
    use RefreshDatabase;

    public function test_paymongo_setup_requires_full_admin_password_and_paused_checkout(): void
    {
        Storage::fake('local');
        config()->set('app.key', 'base64:'.base64_encode(random_bytes(32)));
        config()->set('services.paymongo.secret_key', 'sk_test_environment');
        $credentials = app(\App\Services\PaymongoCredentials::class);
        $this->assertSame('sk_test_environment', $credentials->get()['secret_key']);
        $admin = User::factory()->create(['role'=>'admin', 'password'=>\Illuminate\Support\Facades\Hash::make('admin-password')]);
        $assistant = User::factory()->create(['role'=>'assistant_admin']);
        $url = route('admin.vouchers.payment-setup');
        $data = ['paymongo_secret_key'=>'sk_test_saved', 'paymongo_webhook_secret'=>'whsk_saved', 'current_password'=>'admin-password'];

        $this->actingAs($assistant)->get('/admin/vouchers')->assertOk()->assertDontSee('Save PayMongo Setup');
        $this->putJson($url, $data)->assertForbidden();
        $this->actingAs($admin)->putJson($url, $data)->assertConflict();
        $this->putJson('/admin/vouchers/payment-mode', ['enabled'=>false])->assertOk();
        $this->putJson($url, array_replace($data, ['current_password'=>'wrong']))->assertUnprocessable();
        $this->putJson($url, $data)->assertOk()->assertJsonPath('status', 'Test credentials configured');
        $this->assertSame('sk_test_saved', $credentials->get()['secret_key']);
        $encrypted = \Illuminate\Support\Facades\DB::table('payment_credentials')->where('provider', 'paymongo')->value('encrypted_credentials');
        $this->assertNotEmpty($encrypted);
        $this->assertStringNotContainsString('whsk_saved', $encrypted);
        $this->assertStringNotContainsString('sk_test_saved', $encrypted);
        $this->get('/admin/vouchers')->assertOk()->assertSee('Test credentials configured')->assertDontSee('sk_test_saved')->assertDontSee('whsk_saved');
        $this->assertFalse(app(\App\Services\AutomaticPayments::class)->enabled());
        $body = json_encode(['data'=>['attributes'=>['type'=>'payment.failed']]]);
        $timestamp = time();
        $signature = hash_hmac('sha256', $timestamp.'.'.$body, 'whsk_saved');
        $this->call('POST', '/api/payments/paymongo/webhook', [], [], [], [
            'CONTENT_TYPE'=>'application/json', 'HTTP_PAYMONGO_SIGNATURE'=>"t={$timestamp},te={$signature}",
        ], $body)->assertOk();
    }

    public function test_admin_payment_table_defaults_to_five_and_filters_across_pages(): void
    {
        foreach (range(1, 6) as $index) Voucher::create(['code'=>'TEST-CODE-'.$index]);
        $this->actingAs(User::factory()->create(['role'=>'admin', 'is_admin'=>true]));
        $headers = ['X-Requested-With' => 'XMLHttpRequest', 'X-Admin-Table' => 'vouchers-table'];
        $this->get('/admin/vouchers')->assertOk()->assertSee('data-payment-directory-lazy', false)->assertDontSee('TEST-CODE-6');
        $this->get('/admin/vouchers', $headers)->assertOk()->assertViewIs('admin.partials.payment-directory')
            ->assertViewHas('vouchers', fn ($page) => $page->count() === 5 && $page->total() === 6)
            ->assertSee('TEST-CODE-6')->assertDontSee('TEST-CODE-1');
        $this->get('/admin/vouchers?vouchers_page=2', $headers)->assertOk()->assertSee('TEST-CODE-1')->assertDontSee('TEST-CODE-6');
        $this->get('/admin/vouchers?voucher_search=TEST-CODE-2', $headers)->assertOk()->assertViewHas('vouchers', fn ($page) => $page->total() === 1)->assertSee('TEST-CODE-2');
    }

    public function test_maintenance_blocks_checkout_but_assigned_learner_can_redeem_the_emailed_code(): void
    {
        Storage::fake('local');
        Mail::fake();
        Http::fake();
        [$learner, $batch] = $this->learnerAndBatch();
        $admin = User::factory()->create(['role'=>'admin', 'is_admin'=>true]);
        $other = User::factory()->create(['role'=>'student', 'is_admin'=>false]);
        $this->actingAs($other)->putJson('/admin/vouchers/payment-mode', ['enabled'=>false])->assertNotFound();
        $this->actingAs($admin)->putJson('/admin/vouchers/payment-mode', ['enabled'=>false])->assertOk();
        $this->postJson('/admin/vouchers/assign', ['email'=>$learner->email, 'batch_id'=>$batch->id])
            ->assertCreated()->assertJsonPath('emailed', true);
        $voucher = Voucher::firstOrFail();
        $this->assertMatchesRegularExpression('/^ART2-[0-9A-F]{4}-[0-9A-F]{4}$/', $voucher->code);
        Mail::assertSent(\App\Mail\AssignedEnrollmentVoucher::class, fn ($mail) => $mail->hasTo($learner->email));
        $this->assertSame($learner->id, (int) $voucher->assigned_to);
        $this->get('/admin/vouchers', ['X-Requested-With' => 'XMLHttpRequest', 'X-Admin-Table' => 'vouchers-table'])
            ->assertOk()->assertSee($learner->email)->assertSee('Email sent')->assertSee('Active Code');

        $payload = ['code'=>$voucher->code, 'batch_id'=>$batch->id];
        $this->actingAs($other)->postJson('/api/voucher/verify', $payload)->assertForbidden();
        $this->postJson('/api/voucher/redeem', $payload)->assertForbidden();
        $this->actingAs($learner)->getJson('/api/payments/status')->assertJsonPath('enabled', false);
        $this->postJson('/api/voucher/buy', ['batch_id'=>$batch->id])->assertStatus(503)
            ->assertJsonPath('code', 'automatic_payment_disabled');
        Http::assertNothingSent();
        $this->postJson('/api/voucher/verify', $payload)->assertOk();
        $this->postJson('/api/voucher/redeem', $payload)->assertOk();
        $this->postJson('/api/voucher/redeem', $payload)->assertStatus(400);
        $this->assertDatabaseHas('course_enrollments', ['user_id'=>$learner->id, 'batch_id'=>$batch->id, 'voucher_id'=>$voucher->id]);
        $this->assertTrue($voucher->refresh()->used);
        $this->actingAs($admin)->get('/admin/vouchers', ['X-Requested-With' => 'XMLHttpRequest', 'X-Admin-Table' => 'vouchers-table'])
            ->assertOk()->assertSee('Redeemed / Enrolled')->assertSee('Redeemed date')->assertDontSee('Active Code');
        $this->actingAs($admin)->putJson('/admin/vouchers/payment-mode', ['enabled'=>true])->assertOk();
        $this->actingAs($learner)->getJson('/api/payments/status')->assertJsonPath('enabled', true);
    }

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
        $this->actingAs(User::factory()->create(['role' => 'admin', 'is_admin' => true]))
            ->get('/admin/vouchers', ['X-Requested-With' => 'XMLHttpRequest', 'X-Admin-Table' => 'vouchers-table'])
            ->assertOk()->assertSee('Paid / Enrolled')->assertSee('Redeemed date')->assertDontSee('Redeemed / Enrolled');
        $this->assertDatabaseHas('course_enrollments', ['user_id' => $user->id, 'batch_id' => $batch->id, 'status' => 'active']);
        $this->assertSame(1, CourseEnrollment::where('user_id', $user->id)->count());
    }

    public function test_zero_priced_batch_is_rejected_before_contacting_paymongo(): void
    {
        config()->set('services.paymongo.secret_key', 'sk_test_artemis');
        Http::fake();
        [$user, $batch] = $this->learnerAndBatch();
        $batch->update(['price' => 0]);

        $this->actingAs($user)->postJson('/api/voucher/buy', ['batch_id' => $batch->id])
            ->assertStatus(422)
            ->assertJsonPath('message', 'Online enrollment is unavailable because the administrator has not configured a valid batch price yet.');

        Http::assertNothingSent();
        $this->assertDatabaseCount('payment_transactions', 0);
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
