<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class AdminUserProfileTest extends TestCase
{
    use RefreshDatabase;

    public function test_profile_pictures_are_private_and_can_be_replaced_by_the_owner_or_an_admin(): void
    {
        Storage::fake('local');
        $learner = User::factory()->create(['role' => 'student']);
        $other = User::factory()->create(['role' => 'student']);
        $admin = User::factory()->create(['role' => 'assistant_admin']);
        $image = fn () => UploadedFile::fake()->createWithContent('avatar.png',
            base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAwMCAO+jv1sAAAAASUVORK5CYII='));
        $url = route('profile.picture.show', $learner, false);
        $this->actingAs($learner)->getJson('/api/profile/picture')->assertOk()->assertJsonPath('url', null);
        $this->post('/api/profile/picture', ['profile_picture' => UploadedFile::fake()->create('avatar.svg')], ['Accept' => 'application/json'])
            ->assertUnprocessable();
        $this->post('/api/profile/picture', ['profile_picture' => $image()], ['Accept' => 'application/json'])
            ->assertOk()->assertJsonPath('url', $url);
        $previous = $learner->fresh()->profile_picture_path;
        Storage::disk('local')->assertExists($previous);
        $this->get($url)->assertOk();
        $this->actingAs($other)->get($url)->assertNotFound();
        $this->actingAs($admin)->get($url)->assertOk();
        $this->post(route('admin.users.profile.update', $learner), [
            '_method' => 'PATCH', 'profile_picture' => $image(), 'first_name' => 'Updated', 'last_name' => 'Learner',
            'country_code' => 'PH', 'phone' => '', 'birthdate' => '', 'affiliation_type' => '', 'affiliation_name' => '',
        ], ['Accept' => 'application/json'])->assertOk()->assertJsonPath('profile_picture_url', $url);
        Storage::disk('local')->assertMissing($previous);
        Storage::disk('local')->assertExists($learner->fresh()->profile_picture_path);
    }

    public function test_admins_and_assistants_can_view_and_update_profile_fields_only(): void
    {
        $user = User::factory()->create(['name' => 'Original Name', 'role' => 'student']);
        $url = route('admin.users.profile', $user);
        $password = $user->password;
        $profile = [
            'first_name' => 'Updated', 'middle_name' => 'Middle', 'last_name' => 'Name', 'phone' => '1234',
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
            'id' => $user->id, 'name' => 'Updated Middle Name', 'middle_name' => 'Middle', 'affiliation_name' => 'Example School',
            'email' => $user->email, 'password' => $password, 'role' => 'student',
        ]);

        $this->actingAs(User::factory()->create(['role' => 'staff', 'is_admin' => false]));
        $this->getJson($url)->assertNotFound();
        $this->patchJson($url, $profile)->assertNotFound();
    }
}
