<?php
namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BrandingTest extends TestCase
{
    use RefreshDatabase;

    public function test_powered_caption_uses_explicit_dark_surface_and_certificate_overrides(): void
    {
        $css=file_get_contents(public_path('branding.css'));
        $this->assertStringContainsString('color:var(--gra-powered-color,var(--text,#073b65))',$css);
        $this->assertStringContainsString('.learner-dashboard-sidebar .gra-brand,.lesson-sidebar .gra-brand',$css);
        $this->assertStringContainsString('--gra-powered-color:#fff',$css);
        $this->assertStringContainsString('.cert-paper-logo .gra-brand{--gra-powered-color:#1e293b}',$css);
        $this->assertStringNotContainsString('#landing-screen .gra-brand{color:#fff}',$css);
    }

    public function test_learner_branding_uses_supplied_logo_and_preserves_exam_controls(): void
    {
        $response=$this->get('/')->assertOk()->assertSee('branding.css?v=4',false)
            ->assertSee('alt="GRA Test Prep"',false)->assertSee('Powered by Artemis 2.0');
        $html=$response->getContent();
        $this->assertGreaterThan(10,substr_count($html,'/images/gra-test-prep-logo.png'));
        $this->assertSame(substr_count($html,'/images/gra-test-prep-logo.png'),substr_count($html,'Powered by Artemis 2.0'));
        $this->assertSame(2,substr_count($html,'/images/gra-test-prep-white.svg'));
        $this->assertStringContainsString('Artemis360',$html);
        foreach(['quiz-assessment-title','assessment-summary-assessment-title','logout-btn','learner-course-context-name'] as $id) {
            $this->assertStringContainsString('id="'.$id.'"',$html);
        }
        $this->assertStringNotContainsString('class="exam-brand-mark">A2',$html);
    }

    public function test_management_sign_in_and_password_pages_use_shared_branding(): void
    {
        $this->get('/admin/login')->assertOk()->assertSee('GRA Test Prep')->assertSee('Powered by Artemis 2.0')->assertSee('branding.css');
        $this->get(route('admin.password.request'))->assertOk()->assertSee('GRA Test Prep')->assertSee('Powered by Artemis 2.0');
    }
}
