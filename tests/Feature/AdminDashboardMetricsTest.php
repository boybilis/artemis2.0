<?php

namespace Tests\Feature;

use App\Models\{AuditLog, Course, CourseBatch, PaymentTransaction, User, Voucher};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminDashboardMetricsTest extends TestCase
{
    use RefreshDatabase;

    public function test_metrics_count_monthly_sign_ins_and_confirmed_revenue_without_duplicates(): void
    {
        $this->travelTo(now()->startOfMonth()->addDays(10));
        $admin = User::factory()->create(['role' => 'admin']);
        $learner = User::factory()->create();
        $inactive = User::factory()->create(['is_active' => true]);
        foreach (['Login', 'Login', 'Admin Login'] as $action) {
            AuditLog::create(['user_id' => $learner->id, 'action' => $action, 'description' => 'Signed in']);
        }
        AuditLog::forceCreate(['user_id' => $inactive->id, 'action' => 'Login', 'description' => 'Older sign-in', 'created_at' => now()->startOfMonth()->subDay()]);
        $course = Course::create(['title' => 'Review course']);
        $batch = CourseBatch::create(['course_id' => $course->id, 'name' => 'Review batch', 'code' => 'REVIEW']);
        PaymentTransaction::create(['user_id' => $learner->id, 'batch_id' => $batch->id, 'reference' => 'PAID', 'amount' => 1000, 'provider_checkout_id' => 'paid-checkout', 'status' => 'paid']);
        PaymentTransaction::create(['user_id' => $learner->id, 'batch_id' => $batch->id, 'reference' => 'PENDING', 'amount' => 900, 'status' => 'pending_payment']);
        Voucher::create(['code' => 'MATCHED', 'price' => 1000, 'used' => true, 'payment_provider' => 'paymongo', 'provider_checkout_id' => 'paid-checkout']);
        Voucher::create(['code' => 'LEGACY', 'price' => 200, 'used' => true, 'payment_provider' => 'paymongo']);
        Voucher::create(['code' => 'ISSUED', 'price' => 299, 'used' => false]);
        Voucher::create(['code' => 'PENDING', 'price' => 900, 'used' => false, 'status' => 'pending_payment']);

        $response = $this->actingAs($admin)->get(route('admin.dashboard'))->assertOk();
        $this->assertSame([
                'Total users' => '3',
                'Active users' => '1',
                'Total courses' => '2',
                'Total class batches' => '1',
                'Certificates issued' => '0',
                'Vouchers issued' => '3',
                'Total revenue' => 'PHP 1,200.00',
            ], array_column($response->viewData('stats'), 'value', 'label'));
    }
}
