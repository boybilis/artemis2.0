<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Pagination\LengthAwarePaginator;
use Tests\TestCase;

class AdminAjaxPaginationTest extends TestCase
{
    use RefreshDatabase;

    public function test_priority_admin_tables_use_independent_database_paginators(): void
    {
        $admin = User::factory()->create(['role' => 'admin', 'is_admin' => true]);
        $this->actingAs($admin);

        $progress = $this->get('/admin/progress')->assertOk()
            ->assertSee('id="admin-mobile-menu"', false)
            ->assertSee('id="admin-sidebar"', false)
            ->assertSee('data-ajax-table="attempts-table"', false);
        $this->assertPaginator($progress->viewData('attempts'), 15, 'attempts_page');

        $vouchers = $this->get('/admin/vouchers')->assertOk()
            ->assertSee('data-ajax-table="vouchers-table"', false)
            ->assertSee('data-ajax-table="redeemed-vouchers-table"', false);
        $this->assertPaginator($vouchers->viewData('vouchers'), 15, 'vouchers_page');
        $this->assertPaginator($vouchers->viewData('redeemedVouchers'), 15, 'redeemed_page');

        $certificates = $this->get('/admin/certificates')->assertOk()->assertSee('data-ajax-table="certificates-table"', false);
        $this->assertPaginator($certificates->viewData('certificates'), 15, 'certificates_page');

        $announcements = $this->get('/admin/notifications')->assertOk()->assertSee('data-ajax-table="announcements-table"', false);
        $this->assertPaginator($announcements->viewData('announcements'), 15, 'announcements_page');

        $auditLogs = $this->get('/admin/audit-logs')->assertOk()->assertSee('data-ajax-table="audit-logs-table"', false);
        $this->assertPaginator($auditLogs->viewData('logs'), 20, 'audit_page');
    }

    private function assertPaginator(mixed $value, int $perPage, string $pageName): void
    {
        $this->assertInstanceOf(LengthAwarePaginator::class, $value);
        $this->assertSame($perPage, $value->perPage());
        $this->assertSame($pageName, $value->getPageName());
    }
}
