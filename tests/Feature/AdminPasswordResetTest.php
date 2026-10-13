<?php

namespace Tests\Feature;

use App\Models\User;
use App\Notifications\AdminResetPassword;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Password;
use Tests\TestCase;

class AdminPasswordResetTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_request_a_link_and_reset_once(): void
    {
        Notification::fake();
        $admin = User::factory()->create(['role' => 'admin', 'is_admin' => true]);
        $this->get(route('admin.login'))->assertOk()->assertSee('Forgot password?');
        $this->get(route('admin.password.request'))->assertOk();
        $this->post(route('admin.password.email'), ['email' => $admin->email])->assertSessionHas('status');
        $token = null;
        Notification::assertSentTo($admin, AdminResetPassword::class, function ($notification) use ($admin, &$token) {
            $token = $notification->token;
            $this->assertStringContainsString('/admin/reset-password/', $notification->toMail($admin)->actionUrl);
            return true;
        });
        $this->get(route('admin.password.reset', ['token' => $token, 'email' => $admin->email]))->assertOk()->assertSee('Confirm new password');
        $payload = ['email' => $admin->email, 'token' => $token, 'password' => 'NewSecurePassword123!', 'password_confirmation' => 'NewSecurePassword123!'];
        $this->post(route('admin.password.update'), $payload)->assertRedirect(route('admin.login'))->assertSessionHas('status');
        $this->assertTrue(Hash::check($payload['password'], $admin->fresh()->password));
        $this->post(route('admin.password.update'), $payload)->assertSessionHasErrors('email');
    }

    public function test_unknown_and_learner_emails_receive_same_response_without_notification(): void
    {
        Notification::fake();
        $learner = User::factory()->create(['role' => 'student', 'is_admin' => false]);
        foreach ([$learner->email, 'unknown@example.com'] as $email) {
            $this->post(route('admin.password.email'), ['email' => $email])->assertSessionHas('status')->assertSessionHasNoErrors();
        }
        Notification::assertNothingSent();
        $token = Password::createToken($learner);
        $this->post(route('admin.password.update'), ['email' => $learner->email, 'token' => $token, 'password' => 'NewSecurePassword123!', 'password_confirmation' => 'NewSecurePassword123!'])->assertSessionHasErrors('email');
    }

    public function test_expired_tokens_and_mismatched_passwords_cannot_reset(): void
    {
        $admin = User::factory()->create(['role' => 'admin', 'is_admin' => true]);
        $oldHash = $admin->password;
        $token = Password::createToken($admin);
        $payload = ['email' => $admin->email, 'token' => $token, 'password' => 'NewSecurePassword123!', 'password_confirmation' => 'DifferentPassword123!'];
        $this->post(route('admin.password.update'), $payload)->assertSessionHasErrors('password');
        $payload['password_confirmation'] = $payload['password'];
        $this->travel(61)->minutes();
        $this->post(route('admin.password.update'), $payload)->assertSessionHasErrors('email');
        $this->assertSame($oldHash, $admin->fresh()->password);
    }
}
