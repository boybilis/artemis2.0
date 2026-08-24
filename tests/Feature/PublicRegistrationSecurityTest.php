<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use Illuminate\Support\Facades\Mail;
use App\Mail\RegistrationVerificationCode;

class PublicRegistrationSecurityTest extends TestCase
{
    use RefreshDatabase;

    public function test_public_registration_can_never_assign_an_admin_or_instructor_role(): void
    {
        Mail::fake();
        $response = $this->postJson('/api/auth/register', [
            'su-fname'=>'Security','su-lname'=>'Test','su-email'=>'security@example.test',
            'su-bdate'=>'2000-01-01','su-afftype'=>'admin','su-affname'=>'Example Center',
            'su-phone'=>'09170000000','su-country'=>'PH','su-password'=>'secure-password',
        ]);

        $response->assertOk()->assertJsonPath('verification_required', true);
        $this->assertDatabaseMissing('users',['email'=>'security@example.test']);
        $code = null;
        Mail::assertSent(RegistrationVerificationCode::class, function ($mail) use (&$code) { $code=$mail->code; return true; });
        $this->postJson('/api/auth/register/verify',['email'=>'security@example.test','code'=>$code])->assertOk()->assertJsonPath('user.role','student');
        $user = User::where('email', 'security@example.test')->firstOrFail();
        $this->assertSame('student', $user->role);
        $this->assertFalse((bool) $user->is_admin);
        $this->assertSame('admin', $user->affiliation_type);
    }

    public function test_login_with_an_unknown_email_does_not_create_an_account(): void
    {
        $this->postJson('/api/auth/login', [
            'email' => 'unknown@example.test',
            'password' => 'secure-password',
        ])->assertUnauthorized()->assertJsonPath('success', false);

        $this->assertDatabaseMissing('users', ['email' => 'unknown@example.test']);
        $this->assertGuest();
    }

    public function test_unverified_account_cannot_log_in(): void
    {
        $user = User::factory()->unverified()->create([
            'email' => 'unverified@example.test',
            'password' => bcrypt('secure-password'),
            'is_active' => true,
        ]);

        $this->postJson('/api/auth/login', [
            'email' => $user->email,
            'password' => 'secure-password',
        ])->assertForbidden()->assertJsonPath('success', false);

        $this->assertGuest();
    }

    public function test_verified_active_account_can_log_in(): void
    {
        $user = User::factory()->create([
            'email' => 'verified@example.test',
            'password' => bcrypt('secure-password'),
            'is_active' => true,
        ]);

        $this->postJson('/api/auth/login', [
            'email' => $user->email,
            'password' => 'secure-password',
        ])->assertOk()->assertJsonPath('success', true);

        $this->assertAuthenticatedAs($user);
    }
}
