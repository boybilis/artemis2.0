<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminUserProfileTest extends TestCase
{
    use RefreshDatabase;

    public function test_admins_and_assistants_can_view_and_update_profile_fields_only(): void
    {
        $user = User::factory()->create(['name' => 'Original Name', 'role' => 'student']);
        $url = route('admin.users.profile', $user);
        $password = $user->password;
        $profile = [
            'first_name' => 'Updated', 'last_name' => 'Name', 'phone' => '1234',
            'country_code' => 'PH', 'birthdate' => '2000-01-01',
            'affiliation_type' => 'school', 'affiliation_name' => 'Example School',
        ];

        foreach (['admin', 'assistant_admin'] as $role) {
            $this->actingAs(User::factory()->create(['role' => $role, 'is_admin' => false]));
            $page = route('admin.users.show', $user);
            $this->get(route('admin.users.index'))->assertOk()->assertSee('href="'.$page.'"', false)
                ->assertDontSee('userProfileDialog');
            $this->getJson(route('admin.users.index', ['table_data' => 'users']))->assertOk()
                ->assertJsonFragment(['profileUrl' => $page]);
            $this->get($page)->assertOk()->assertSee('form="user-profile-form"', false);
            $this->getJson($url)->assertOk()->assertJsonPath('profile.email', $user->email)
                ->assertJsonMissingPath('profile.password');
            $this->patchJson($url, $profile + [
                'email' => 'changed@example.com', 'password' => 'not-allowed', 'role' => 'admin',
            ])->assertUnprocessable()->assertJsonValidationErrors(['email', 'password', 'role']);
            $this->patchJson($url, $profile)->assertOk();
        }

        $this->assertDatabaseHas('users', [
            'id' => $user->id, 'name' => 'Updated Name', 'affiliation_name' => 'Example School',
            'email' => $user->email, 'password' => $password, 'role' => 'student',
        ]);

        $this->actingAs(User::factory()->create(['role' => 'staff', 'is_admin' => false]));
        $this->getJson($url)->assertNotFound();
        $this->patchJson($url, $profile)->assertNotFound();
    }
}
