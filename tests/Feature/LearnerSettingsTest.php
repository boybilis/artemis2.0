<?php

namespace Tests\Feature;

use App\Mail\EmailChangeVerificationCode;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class LearnerSettingsTest extends TestCase
{
    use RefreshDatabase;

    public function test_learner_can_update_phone_without_changing_credentials(): void
    {
        $learner = User::factory()->create(['email'=>'learner@example.com','phone'=>'09170000000','password'=>Hash::make('old-password')]);

        $this->actingAs($learner)->postJson('/api/profile/settings', [
            'email'=>'learner@example.com', 'phone'=>'+63 917 123 4567',
            'current_password'=>'', 'password'=>'', 'password_confirmation'=>'',
        ])->assertOk()->assertJsonPath('user.phone', '+63 917 123 4567');

        $this->assertDatabaseHas('users', ['id'=>$learner->id,'phone'=>'+63 917 123 4567']);
    }

    public function test_email_or_password_change_requires_the_correct_current_password(): void
    {
        Mail::fake();
        $learner = User::factory()->create(['email'=>'learner@example.com','password'=>Hash::make('old-password')]);

        $this->actingAs($learner)->postJson('/api/profile/settings', [
            'email'=>'updated@example.com', 'phone'=>'09170000000',
            'current_password'=>'wrong-password', 'password'=>'new-password', 'password_confirmation'=>'new-password',
        ])->assertUnprocessable()->assertJsonValidationErrors('current_password');

        $this->postJson('/api/profile/settings', [
            'email'=>'updated@example.com', 'phone'=>'09170000000',
            'current_password'=>'old-password', 'password'=>'new-password', 'password_confirmation'=>'new-password',
        ])->assertOk()->assertJsonPath('verification_required', true)->assertJsonPath('user.email', 'learner@example.com');

        $learner->refresh();
        $this->assertTrue(Hash::check('new-password', $learner->password));
        $this->assertSame('learner@example.com', $learner->email);

        $code = null;
        Mail::assertSent(EmailChangeVerificationCode::class, function ($mail) use (&$code) {
            $code = $mail->code;
            return $mail->hasTo('updated@example.com');
        });
        $this->postJson('/api/profile/email/verify', ['code' => $code])
            ->assertOk()->assertJsonPath('user.email', 'updated@example.com');
        $this->assertDatabaseHas('users', ['id' => $learner->id, 'email' => 'updated@example.com']);
        $this->assertDatabaseMissing('pending_email_changes', ['user_id' => $learner->id]);
    }

    public function test_incorrect_email_change_code_does_not_replace_current_email(): void
    {
        Mail::fake();
        $learner = User::factory()->create(['email' => 'current@example.com', 'password' => Hash::make('old-password')]);
        $this->actingAs($learner)->postJson('/api/profile/settings', [
            'email' => 'pending@example.com', 'phone' => '09170000000',
            'current_password' => 'old-password', 'password' => '', 'password_confirmation' => '',
        ])->assertOk();

        $this->postJson('/api/profile/email/verify', ['code' => '000000'])->assertUnprocessable();
        $this->assertDatabaseHas('users', ['id' => $learner->id, 'email' => 'current@example.com']);
    }
}
