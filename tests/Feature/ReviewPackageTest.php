<?php

namespace Tests\Feature;

use App\Models\Course;
use App\Models\CourseBatch;
use App\Models\PaymentTransaction;
use App\Models\ReviewPackage;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class ReviewPackageTest extends TestCase
{
    use RefreshDatabase;

    public function test_only_administrator_can_manage_packages(): void
    {
        $learner = User::factory()->create(['role'=>'student', 'is_admin'=>false]);
        $instructor = User::factory()->create(['role'=>'instructor', 'is_admin'=>false]);
        $admin = User::factory()->create(['role'=>'admin', 'is_admin'=>true]);

        $this->actingAs($learner)->get('/admin/packages')->assertNotFound();
        $this->actingAs($instructor)->get('/admin/packages')->assertNotFound();
        $this->actingAs($admin)->get('/admin/packages')->assertOk()->assertSee('Create Package');
    }

    public function test_learner_package_catalog_is_separate_and_checkout_uses_package_price(): void
    {
        config()->set('services.paymongo.secret_key', 'sk_test_artemis');
        Http::fake(['api.paymongo.com/v1/checkout_sessions' => Http::response(['data'=>['id'=>'cs_pkg','attributes'=>['checkout_url'=>'https://checkout.paymongo.com/pkg']]], 200)]);
        [$learner, $package] = $this->makePackage();

        $this->actingAs($learner)->getJson('/api/packages')->assertOk()
            ->assertJsonPath('packages.0.name', 'Nursing Power Bundle')->assertJsonCount(2, 'packages.0.batches');
        $this->actingAs($learner)->postJson("/api/packages/{$package->id}/buy")->assertOk()
            ->assertJsonPath('checkout_url', 'https://checkout.paymongo.com/pkg');
        Http::assertSent(fn ($request) => $request['data']['attributes']['line_items'][0]['amount'] === 399900);
        $this->assertDatabaseHas('payment_transactions', ['review_package_id'=>$package->id, 'batch_id'=>null, 'amount'=>3999, 'status'=>'pending_payment']);
    }

    public function test_paid_package_webhook_enrolls_every_included_batch_once(): void
    {
        config()->set('services.paymongo.secret_key', 'sk_test_artemis');
        config()->set('services.paymongo.webhook_secret', 'whsk_test_artemis');
        [$learner, $package] = $this->makePackage();
        $transaction = PaymentTransaction::create(['user_id'=>$learner->id, 'review_package_id'=>$package->id, 'reference'=>'ART2PKG-TEST', 'amount'=>3999, 'currency'=>'PHP', 'provider'=>'paymongo', 'status'=>'pending_payment', 'provider_checkout_id'=>'cs_pkg_paid']);
        $payload = json_encode(['data'=>['id'=>'evt_pkg','attributes'=>['type'=>'checkout_session.payment.paid','data'=>['id'=>'cs_pkg_paid','type'=>'checkout_session','attributes'=>['reference_number'=>$transaction->reference,'payments'=>[['id'=>'pay_pkg','attributes'=>['status'=>'paid']]]]]]]], JSON_UNESCAPED_SLASHES);
        $timestamp = time();
        $signature = hash_hmac('sha256', $timestamp.'.'.$payload, 'whsk_test_artemis');
        $server = ['CONTENT_TYPE'=>'application/json', 'HTTP_PAYMONGO_SIGNATURE'=>"t={$timestamp},te={$signature}"];

        $this->call('POST', '/api/payments/paymongo/webhook', [], [], [], $server, $payload)->assertOk();
        $this->call('POST', '/api/payments/paymongo/webhook', [], [], [], $server, $payload)->assertOk();
        $this->assertDatabaseHas('payment_transactions', ['id'=>$transaction->id, 'status'=>'paid']);
        $this->assertSame(2, $learner->enrollments()->where('status', 'active')->count());
        foreach ($package->batches as $batch) $this->assertDatabaseHas('course_enrollments', ['user_id'=>$learner->id, 'batch_id'=>$batch->id, 'status'=>'active']);
    }

    private function makePackage(): array
    {
        $learner = User::factory()->create();
        $batches = collect(['NCLEX'=>'NCLEX-B1', 'PNLE'=>'PNLE-B1'])->map(function ($code, $title) {
            $course = Course::create(['title'=>$title, 'is_published'=>true, 'approval_status'=>'approved']);
            return CourseBatch::create(['course_id'=>$course->id, 'name'=>$title.' Batch 1', 'code'=>$code, 'price'=>2500, 'status'=>'open', 'ends_at'=>now()->addMonths(3)]);
        });
        $package = ReviewPackage::create(['name'=>'Nursing Power Bundle', 'description'=>'Two review programs.', 'price'=>3999, 'starts_at'=>now()->addWeek(), 'class_type'=>'Live Online', 'status'=>'active']);
        $package->batches()->sync($batches->pluck('id'));
        return [$learner, $package->load('batches')];
    }
}
