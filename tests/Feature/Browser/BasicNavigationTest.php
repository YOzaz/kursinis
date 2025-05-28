<?php

namespace Tests\Feature\Browser;

use App\Models\AnalysisJob;
use Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;

/**
 * Basic browser-style navigation tests without requiring full Dusk setup
 * Tests basic page navigation and form interactions
 */
class BasicNavigationTest extends TestCase
{
    use RefreshDatabase;

    private AnalysisJob $analysisJob;

    protected function setUp(): void
    {
        parent::setUp();

        $this->analysisJob = AnalysisJob::factory()->create([
            'status' => 'completed',
            'name' => 'Navigation Test Analysis'
        ]);
    }

    public function test_home_page_navigation_elements()
    {
        $this->withSession(['authenticated' => true, 'username' => 'admin']);
        
        $response = $this->get('/');
        
        $response->assertStatus(200)
                ->assertSee('navbar')
                ->assertSee('Dashboard')
                ->assertSee('Analizės')
                ->assertSee('Nustatymai')
                ->assertSee('Pagalba')
                ->assertSee('Atsijungti');
    }

    public function test_navigation_menu_contains_all_links()
    {
        $this->withSession(['authenticated' => true, 'username' => 'admin']);
        
        $response = $this->get('/');
        
        $content = $response->getContent();
        
        $this->assertStringContainsString('href="/dashboard"', $content);
        $this->assertStringContainsString('href="/analyses"', $content);
        $this->assertStringContainsString('href="/settings"', $content);
        $this->assertStringContainsString('href="/help"', $content);
    }

    public function test_file_upload_form_elements()
    {
        $this->withSession(['authenticated' => true, 'username' => 'admin']);
        
        $response = $this->get('/');
        
        $response->assertStatus(200)
                ->assertSee('JSON failas')
                ->assertSee('input type="file"', false)
                ->assertSee('name="json_file"', false)
                ->assertSee('Pradėti analizę');
    }

    public function test_model_selection_checkboxes()
    {
        $this->withSession(['authenticated' => true, 'username' => 'admin']);
        
        $response = $this->get('/');
        
        $content = $response->getContent();
        
        // Check for model selection checkboxes
        $this->assertStringContainsString('type="checkbox"', $content);
        $this->assertStringContainsString('name="models[]"', $content);
        $this->assertStringContainsString('Claude', $content);
        $this->assertStringContainsString('GPT', $content);
        $this->assertStringContainsString('Gemini', $content);
    }

    public function test_recent_analyses_table_structure()
    {
        $this->withSession(['authenticated' => true, 'username' => 'admin']);
        
        $response = $this->get('/');
        
        $response->assertStatus(200)
                ->assertSee('Paskutinės analizės')
                ->assertSee('table')
                ->assertSee('thead')
                ->assertSee('tbody')
                ->assertSee('Navigation Test Analysis');
    }

    public function test_dashboard_cards_layout()
    {
        $this->withSession(['authenticated' => true, 'username' => 'admin']);
        
        $response = $this->get('/dashboard');
        
        $content = $response->getContent();
        
        // Check for Bootstrap card structure
        $this->assertStringContainsString('class="card"', $content);
        $this->assertStringContainsString('card-body', $content);
        
        // Check for dashboard content instead of specific card-title class
        $this->assertStringContainsString('Viso analizių', $content);
        $this->assertStringContainsString('Dashboard', $content);
        $this->assertStringContainsString('tachometer-alt', $content);
    }

    public function test_settings_page_form_elements()
    {
        $this->withSession(['authenticated' => true, 'username' => 'admin']);
        
        $response = $this->get('/settings');
        
        $response->assertStatus(200)
                ->assertSee('Sistemos nustatymai')
                ->assertSee('LLM modeliai')
                ->assertSee('Temperature')
                ->assertSee('Max tokens');
    }

    public function test_help_page_navigation()
    {
        $this->withSession(['authenticated' => true, 'username' => 'admin']);
        
        $response = $this->get('/help');
        
        $response->assertStatus(200)
                ->assertSee('Pagalba')
                ->assertSee('FAQ')
                ->assertSee('/help/faq');
    }

    public function test_faq_page_content_structure()
    {
        $this->withSession(['authenticated' => true, 'username' => 'admin']);
        
        $response = $this->get('/help/faq');
        
        $response->assertStatus(200)
                ->assertSee('Dažnai užduodami klausimai')
                ->assertSee('accordion', false); // Bootstrap accordion for FAQ
    }

    public function test_analyses_list_page_structure()
    {
        $this->withSession(['authenticated' => true, 'username' => 'admin']);
        
        $response = $this->get('/analyses');
        
        $response->assertStatus(200)
                ->assertSee('Analizės')
                ->assertSee('table')
                ->assertSee('Navigation Test Analysis')
                ->assertSee('Baigta'); // Status
    }

    public function test_analysis_details_modal_trigger()
    {
        $this->withSession(['authenticated' => true, 'username' => 'admin']);
        
        $response = $this->get("/analyses/{$this->analysisJob->job_id}");
        
        $content = $response->getContent();
        
        // Check for modal triggers
        $this->assertStringContainsString('data-bs-toggle="modal"', $content);
        $this->assertStringContainsString('data-bs-target="#repeatAnalysisModal', $content);
    }

    public function test_responsive_layout_classes()
    {
        $this->withSession(['authenticated' => true, 'username' => 'admin']);
        
        $response = $this->get('/');
        
        $content = $response->getContent();
        
        // Check for Bootstrap responsive classes
        $this->assertStringContainsString('container', $content);
        $this->assertStringContainsString('row', $content);
        $this->assertStringContainsString('col-', $content);
        $this->assertStringContainsString('d-flex', $content);
    }

    public function test_form_validation_attributes()
    {
        $this->withSession(['authenticated' => true, 'username' => 'admin']);
        
        $response = $this->get('/');
        
        $content = $response->getContent();
        
        // Check for HTML5 validation attributes
        $this->assertStringContainsString('required', $content);
        $this->assertStringContainsString('accept=".json"', $content);
    }

    public function test_csrf_protection_in_forms()
    {
        $this->withSession(['authenticated' => true, 'username' => 'admin']);
        
        $response = $this->get('/');
        
        $content = $response->getContent();
        
        // Check for CSRF token
        $this->assertStringContainsString('_token', $content);
        $this->assertStringContainsString('name="_token"', $content);
    }

    public function test_javascript_functions_included()
    {
        $this->withSession(['authenticated' => true, 'username' => 'admin']);
        
        $response = $this->get('/');
        
        $content = $response->getContent();
        
        // Check for Bootstrap JavaScript
        $this->assertStringContainsString('bootstrap', $content);
        $this->assertStringContainsString('<script', $content);
    }
}