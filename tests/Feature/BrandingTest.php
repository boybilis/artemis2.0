<?php
namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BrandingTest extends TestCase
{
    use RefreshDatabase;

    public function test_learner_branding_uses_supplied_logo_and_preserves_exam_controls(): void
    {
        $response=$this->get('/')->assertOk()->assertSee('branding.css?v=1',false)
            ->assertSee('alt="GRA Test Prep"',false)->assertSee('Powered by Artemis 2.0');
        $html=$response->getContent();
        $this->assertGreaterThan(10,substr_count($html,'/images/gra-test-prep-logo.png'));
        $this->assertSame(substr_count($html,'/images/gra-test-prep-logo.png'),substr_count($html,'Powered by Artemis 2.0'));
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
