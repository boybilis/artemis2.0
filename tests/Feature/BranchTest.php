<?php

namespace Tests\Feature;

use App\Models\Branch;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class BranchTest extends TestCase
{
    use RefreshDatabase;

    public function test_assistant_can_publish_a_branch_on_the_current_domain_and_configure_a_base_domain(): void
    {
        Storage::fake('public');
        Storage::fake('local');
        $this->actingAs(User::factory()->create(['role'=>'assistant_admin', 'is_admin'=>false]));
        $this->get('/admin/branches')->assertOk()->assertSee('Add New Branch');
        $hero = UploadedFile::fake()->createWithContent('hero.png', base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAwMCAO+aX1sAAAAASUVORK5CYII='));
        $this->postJson('/admin/branches', ['name'=>'Cebu Review Center', 'slug'=>'Cebu', 'description'=>'Welcome to Cebu.', 'hero'=>$hero])
            ->assertCreated()->assertJsonPath('url', '/branches/cebu');
        Storage::disk('public')->assertExists(Branch::firstOrFail()->hero_path);
        $this->get('/branches/cebu')->assertOk()->assertSee('Cebu Review Center')
            ->assertSee('id="nav-login-btn"', false)->assertDontSee('id="hero-login-btn"', false)
            ->assertSee('id="modal-login"', false)->assertSee('<base href="/">', false)
            ->assertSee('class="jumbotron branch-hero"', false)
            ->assertSeeInOrder(['src="/branches/cebu/hero"', '<h1>Cebu Review Center</h1>', 'Welcome to Cebu.'], false)
            ->assertDontSee('/?login=1');
        $this->get('/branches/cebu/hero')->assertOk()->assertHeader('Content-Type', 'image/png');
        $this->putJson('/admin/branches/domain', ['base_domain'=>'example.test'])->assertOk();
        $this->get('https://cebu.example.test/')->assertOk()->assertSee('Cebu Review Center')->assertSee('id="modal-login"', false)->assertDontSee('https://example.test/?login=1');
        $this->get('https://unknown.example.test/')->assertNotFound();
        $this->get('/branches/cebu')->assertOk();
    }

    public function test_instructors_cannot_manage_branches(): void
    {
        $this->actingAs(User::factory()->create(['role'=>'instructor', 'is_admin'=>false]));
        $this->get('/admin/branches')->assertNotFound();
        $this->postJson('/admin/branches', [])->assertNotFound();
        $this->putJson('/admin/branches/domain', [])->assertNotFound();
    }
}
