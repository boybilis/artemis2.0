<?php

namespace Tests\Feature;

use App\Http\Middleware\AdministratorMiddleware;
use App\Http\Middleware\AdminMiddleware;
use App\Http\Middleware\RoleMiddleware;
use App\Models\User;
use Illuminate\Support\Facades\Route;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AssistantAdminAccessTest extends TestCase
{
    use RefreshDatabase;
    protected function setUp(): void
    {
        parent::setUp();
        Route::middleware(['web', AdminMiddleware::class, AdministratorMiddleware::class, RoleMiddleware::class.':admin'])->group(function () {
            Route::get('/assistant-access-test', fn () => response('ok'));
            Route::delete('/assistant-delete-test', fn () => response('deleted'));
        });
    }

    public function test_assistant_has_admin_operational_access_without_admin_flag(): void
    {
        $user = User::factory()->make(['id'=>123, 'role'=>'assistant_admin', 'is_admin'=>false]);
        $this->assertTrue($user->hasAdminAccess());
        $this->assertFalse((bool) $user->is_admin);
        $this->actingAs($user)->get('/assistant-access-test')->assertOk();
    }

    public function test_assistant_cannot_use_restricted_routes_even_with_admin_flag(): void
    {
        foreach ([false, true] as $adminFlag) {
            $this->actingAs(User::factory()->make(['id'=>123,'role'=>'assistant_admin','is_admin'=>$adminFlag]));
            $this->get('/admin/audit-logs')->assertForbidden();
            $this->post('/admin/users/123/role', ['role'=>'admin'])->assertForbidden();
            $this->delete('/assistant-delete-test')->assertForbidden();
            $this->post('/admin/content/courses/1/assessment-attempts/reset')->assertForbidden();
            $this->post('/admin/content/courses/1/enrollments/123/unenroll')->assertForbidden();
        }
    }

    public function test_full_admin_and_student_permissions_are_unchanged(): void
    {
        $this->actingAs(User::factory()->make(['id'=>124,'role'=>'admin','is_admin'=>true]))->delete('/assistant-delete-test')->assertOk();
        $this->actingAs(User::factory()->create(['role'=>'student','is_admin'=>false]))->get('/assistant-access-test')->assertNotFound();
    }

    public function test_only_full_admin_can_assign_assistant_role(): void
    {
        $admin = User::factory()->create(['role'=>'admin','is_admin'=>true]);
        $target = User::factory()->create(['role'=>'student','is_admin'=>false]);
        $this->actingAs($admin)->postJson('/admin/users/'.$target->id.'/role', ['role'=>'assistant_admin'])->assertOk();
        $this->assertSame('assistant_admin', $target->fresh()->role);
        $this->assertFalse((bool) $target->fresh()->is_admin);
        $this->actingAs($target->fresh())->postJson('/admin/users/'.$target->id.'/role', ['role'=>'admin'])->assertForbidden();
        $this->assertSame('assistant_admin', $target->fresh()->role);
        $this->actingAs($target->fresh())->getJson('/admin/users?client_data=1')->assertOk();
    }
}
