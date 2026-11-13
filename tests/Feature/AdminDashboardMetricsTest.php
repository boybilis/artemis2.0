<?php

namespace Tests\Feature;

use App\Models\{AuditLog, Branch, Course, CourseBatch, CourseEnrollment, PaymentTransaction, User, Voucher};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminDashboardMetricsTest extends TestCase
{
    use RefreshDatabase;

    public function test_active_batches_chart_paginates_and_excludes_expired_enrollments(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $course = Course::create(['title' => 'NCLEX Review']);
        foreach (range(1, 11) as $i) {
            $batch = CourseBatch::create(['course_id' => $course->id, 'name' => 'Open batch '.$i,
                'code' => 'OPEN-'.$i, 'status' => 'open', 'starts_at' => now()->subDay(), 'ends_at' => now()->addDay()]);
        }
        CourseBatch::create(['name' => 'Future batch', 'code' => 'FUTURE', 'status' => 'open', 'starts_at' => now()->addDay()]);
        CourseBatch::create(['name' => 'Closed batch', 'code' => 'CLOSED', 'status' => 'closed']);
        foreach ([null, now()->subHour()] as $expiry) {
            CourseEnrollment::create(['user_id' => User::factory()->create()->id, 'batch_id' => $batch->id,
                'status' => 'active', 'expires_at' => $expiry]);
        }
        $url = route('admin.dashboard');
        $headers = ['X-Requested-With' => 'XMLHttpRequest', 'X-Admin-Table' => 'active-batches'];
        $this->actingAs($admin)->get($url, $headers)->assertOk()
            ->assertViewIs('admin.partials.dashboard-active-batches')
            ->assertViewHas('activeBatches', fn ($page) => $page->count() === 10 && $page->total() === 11
                && $page->items()[0]['count'] === 1 && $page->items()[0]['family'] === 'nclex')
            ->assertDontSee('Future batch')->assertDontSee('Closed batch')->assertDontSee('Monthly Registrations');
        $this->get($url.'?active_batches_page=2', $headers)->assertOk()
            ->assertViewHas('activeBatches', fn ($page) => $page->count() === 1);
    }

    public function test_registration_chart_shows_january_through_current_month_including_zeroes(): void
    {
        $this->travelTo(\Illuminate\Support\Carbon::parse('2026-04-15 12:00:00'));
        $admin = User::factory()->create(['role' => 'admin']);
        foreach (['2025-12-31', '2026-01-01', '2026-01-31', '2026-03-01', '2026-04-25', '2026-05-01'] as $date) {
            User::factory()->create(['created_at' => $date.' 12:00:00']);
        }
        User::factory()->count(5)->create(['name' => 'Older test account', 'created_at' => '2025-01-01 12:00:00']);

        $response = $this->actingAs($admin)->get(route('admin.dashboard'))->assertOk()
            ->assertSee('Monthly Registrations')->assertViewHas('registrationYear', 2026)
            ->assertViewHas('recentUsers', fn ($users) => $users->perPage() === 5 && $users->count() === 5 && $users->total() === 10)
            ->assertDontSee('>Email</th>', false)->assertDontSee('>Joined</th>', false);
        $this->get(route('admin.dashboard', ['dashboard_page' => 2]), ['X-Requested-With' => 'XMLHttpRequest'])
            ->assertOk()->assertViewIs('admin.partials.dashboard-recent-users')
            ->assertViewHas('recentUsers', fn ($users) => $users->currentPage() === 2 && $users->count() === 5 && !$users->hasMorePages())
            ->assertSee('data-ajax-table="recent-users"', false)->assertSee('data-recent-users-page=', false)
            ->assertDontSee('Monthly Registrations');
        $this->assertSame(['Jan' => 2, 'Feb' => 0, 'Mar' => 1, 'Apr' => 1],
            array_column($response->viewData('monthlyRegistrations'), 'count', 'month'));
    }

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
        Branch::create(['name' => 'Example Branch', 'slug' => 'example', 'description' => 'Example landing page', 'hero_path' => 'branches/example.png']);
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
                'Total branches' => '1',
                'Vouchers issued' => '3',
                'Total revenue' => 'PHP 1,200.00',
            ], array_column($response->viewData('stats'), 'value', 'label'));
    }
}
