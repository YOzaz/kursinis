<?php

namespace Tests\Feature\Browser;

use App\Models\AnalysisJob;
use App\Models\TextAnalysis;
use Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;

/**
 * Core user workflow browser tests for the propaganda analysis system.
 * Tests the main user journeys without requiring full Dusk setup.
 */
class CoreWorkflowTest extends TestCase
{
    use RefreshDatabase;

    public function test_home_page_loads_successfully()
    {
        $this->withSession(['authenticated' => true, 'username' => 'admin']);
        
        $response = $this->get('/');

        $response->assertStatus(200)
                ->assertSee('Propagandos analizės sistema');
    }

    public function test_analyses_page_displays_completed_jobs()
    {
        $this->withSession(['authenticated' => true, 'username' => 'admin']);
        
        $job = AnalysisJob::factory()->completed()->create([
            'name' => 'Test Analysis Job'
        ]);

        $response = $this->get('/analyses');

        $response->assertStatus(200)
                ->assertSee('Test Analysis Job')
                ->assertSee('Analizių sąrašas');
    }

    public function test_analysis_details_page_loads()
    {
        $this->withSession(['authenticated' => true, 'username' => 'admin']);
        
        $job = AnalysisJob::factory()->completed()->create();
        TextAnalysis::factory()->create([
            'job_id' => $job->job_id,
            'content' => 'Test propaganda text'
        ]);

        $response = $this->get("/analyses/{$job->job_id}");

        $response->assertStatus(200)
                ->assertSee('Test propaganda text')
                ->assertSee('Analizės rezultatai');
    }

    public function test_dashboard_page_loads_with_statistics()
    {
        $this->withSession(['authenticated' => true, 'username' => 'admin']);
        
        $response = $this->get('/dashboard');

        $response->assertStatus(200)
                ->assertSee('Dashboard');
    }

    public function test_settings_page_loads()
    {
        $this->withSession(['authenticated' => true, 'username' => 'admin']);
        
        $response = $this->get('/settings');

        $response->assertStatus(200)
                ->assertSee('Nustatymai');
    }

    public function test_help_page_loads()
    {
        $this->withSession(['authenticated' => true, 'username' => 'admin']);
        
        $response = $this->get('/help');

        $response->assertStatus(200)
                ->assertSee('Pagalba');
    }

    public function test_authentication_required_for_protected_routes()
    {
        $protectedRoutes = [
            '/analyses',
            '/dashboard', 
            '/settings'
        ];

        foreach ($protectedRoutes as $route) {
            $response = $this->get($route);
            $response->assertRedirect('/login');
        }
    }

    public function test_navigation_menu_is_present()
    {
        $this->withSession(['authenticated' => true, 'username' => 'admin']);
        
        $response = $this->get('/');

        $response->assertStatus(200)
                ->assertSee('Nauja analizė')
                ->assertSee('Analizės')
                ->assertSee('Dashboard')
                ->assertSee('Nustatymai')
                ->assertSee('Pagalba');
    }

    public function test_file_upload_form_is_present()
    {
        $this->withSession(['authenticated' => true, 'username' => 'admin']);
        
        $response = $this->get('/');

        $response->assertStatus(200)
                ->assertSee('name="json_file"', false)
                ->assertSee('name="models[]"', false)
                ->assertSee('type="file"', false);
    }

    public function test_model_selection_checkboxes_are_present()
    {
        $this->withSession(['authenticated' => true, 'username' => 'admin']);
        
        $response = $this->get('/create');

        $response->assertStatus(200)
                ->assertSee('claude-opus-4')
                ->assertSee('gpt-4.1')
                ->assertSee('gemini-2.5-pro');
    }
}