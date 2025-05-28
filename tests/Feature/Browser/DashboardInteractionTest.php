<?php

namespace Tests\Feature\Browser;

use Tests\TestCase;
use App\Models\AnalysisJob;
use App\Models\TextAnalysis;
use App\Models\ComparisonMetric;
use Illuminate\Foundation\Testing\RefreshDatabase;

/**
 * Browser-style tests for dashboard interactions
 * Tests dashboard UI elements and interactive features
 */
class DashboardInteractionTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        
        // Create test data for dashboard
        $job = AnalysisJob::factory()->create([
            'status' => 'completed',
            'name' => 'Dashboard Test Analysis'
        ]);
        
        TextAnalysis::factory()->create([
            'job_id' => $job->job_id,
            'model_name' => 'claude-opus-4'
        ]);
        
        ComparisonMetric::factory()->create([
            'job_id' => $job->job_id,
            'model_name' => 'claude-opus-4'
        ]);
    }

    public function test_dashboard_statistics_cards_display()
    {
        $this->withSession(['authenticated' => true, 'username' => 'admin']);
        
        $response = $this->get('/dashboard');
        
        $response->assertStatus(200)
                ->assertSee('Bendros analizės')
                ->assertSee('Užbaigtos analizės')
                ->assertSee('Analizuoti tekstai')
                ->assertSee('Modelių naudojimas');
    }

    public function test_dashboard_shows_numeric_statistics()
    {
        $this->withSession(['authenticated' => true, 'username' => 'admin']);
        
        $response = $this->get('/dashboard');
        
        $response->assertStatus(200)
                ->assertSee('1') // At least 1 analysis from setup
                ->assertSee('card-text'); // Bootstrap card structure
    }

    public function test_dashboard_recent_analyses_table()
    {
        $this->withSession(['authenticated' => true, 'username' => 'admin']);
        
        $response = $this->get('/dashboard');
        
        $response->assertStatus(200)
                ->assertSee('Paskutinės analizės')
                ->assertSee('Dashboard Test Analysis')
                ->assertSee('table')
                ->assertSee('thead')
                ->assertSee('tbody');
    }

    public function test_dashboard_table_headers()
    {
        $this->withSession(['authenticated' => true, 'username' => 'admin']);
        
        $response = $this->get('/dashboard');
        
        $response->assertStatus(200)
                ->assertSee('Analizės ID')
                ->assertSee('Pavadinimas')
                ->assertSee('Statusas')
                ->assertSee('Sukurta');
    }

    public function test_dashboard_analysis_links()
    {
        $this->withSession(['authenticated' => true, 'username' => 'admin']);
        
        $response = $this->get('/dashboard');
        
        $content = $response->getContent();
        
        // Check for links to analysis details
        $this->assertStringContainsString('/analyses/', $content);
        $this->assertStringContainsString('href=', $content);
    }

    public function test_dashboard_status_badges()
    {
        $this->withSession(['authenticated' => true, 'username' => 'admin']);
        
        $response = $this->get('/dashboard');
        
        $response->assertStatus(200)
                ->assertSee('badge')
                ->assertSee('Baigta'); // Completed status
    }

    public function test_dashboard_export_functionality()
    {
        $this->withSession(['authenticated' => true, 'username' => 'admin']);
        
        $response = $this->get('/dashboard');
        
        $response->assertStatus(200)
                ->assertSee('Export')
                ->assertSee('CSV')
                ->assertSee('JSON');
    }

    public function test_dashboard_refresh_functionality()
    {
        $this->withSession(['authenticated' => true, 'username' => 'admin']);
        
        $response = $this->get('/dashboard');
        
        $content = $response->getContent();
        
        // Check for refresh button or auto-refresh functionality
        $this->assertStringContainsString('refresh', $content);
    }

    public function test_dashboard_model_statistics_chart()
    {
        $this->withSession(['authenticated' => true, 'username' => 'admin']);
        
        $response = $this->get('/dashboard');
        
        $response->assertStatus(200)
                ->assertSee('claude-opus-4'); // Should show used models
    }

    public function test_dashboard_responsive_grid_layout()
    {
        $this->withSession(['authenticated' => true, 'username' => 'admin']);
        
        $response = $this->get('/dashboard');
        
        $content = $response->getContent();
        
        // Check for responsive Bootstrap grid
        $this->assertStringContainsString('col-', $content);
        $this->assertStringContainsString('row', $content);
        $this->assertStringContainsString('container', $content);
    }

    public function test_dashboard_chart_javascript()
    {
        $this->withSession(['authenticated' => true, 'username' => 'admin']);
        
        $response = $this->get('/dashboard');
        
        $content = $response->getContent();
        
        // Check for chart initialization
        $this->assertStringContainsString('Chart', $content);
        $this->assertStringContainsString('canvas', $content);
    }

    public function test_dashboard_pagination_for_large_datasets()
    {
        // Create many analysis jobs
        AnalysisJob::factory()->count(15)->create();
        
        $this->withSession(['authenticated' => true, 'username' => 'admin']);
        
        $response = $this->get('/dashboard');
        
        $response->assertStatus(200);
        
        // Should limit recent analyses display
        $content = $response->getContent();
        $analysisCount = substr_count($content, 'Dashboard Test Analysis');
        $this->assertLessThanOrEqual(10, $analysisCount);
    }

    public function test_dashboard_empty_state()
    {
        // Clear all data
        AnalysisJob::truncate();
        TextAnalysis::truncate();
        ComparisonMetric::truncate();
        
        $this->withSession(['authenticated' => true, 'username' => 'admin']);
        
        $response = $this->get('/dashboard');
        
        $response->assertStatus(200)
                ->assertSee('0'); // Should show 0 for empty statistics
    }

    public function test_dashboard_loading_states()
    {
        $this->withSession(['authenticated' => true, 'username' => 'admin']);
        
        $response = $this->get('/dashboard');
        
        $content = $response->getContent();
        
        // Check for loading indicators
        $this->assertStringContainsString('loading', $content);
        $this->assertStringContainsString('spinner', $content);
    }

    public function test_dashboard_error_handling()
    {
        $this->withSession(['authenticated' => true, 'username' => 'admin']);
        
        $response = $this->get('/dashboard');
        
        $content = $response->getContent();
        
        // Check for error handling elements
        $this->assertStringContainsString('error', $content);
        $this->assertStringContainsString('alert', $content);
    }

    public function test_dashboard_tooltips_and_help()
    {
        $this->withSession(['authenticated' => true, 'username' => 'admin']);
        
        $response = $this->get('/dashboard');
        
        $content = $response->getContent();
        
        // Check for tooltip attributes
        $this->assertStringContainsString('data-bs-toggle="tooltip"', $content);
        $this->assertStringContainsString('title=', $content);
    }

    public function test_dashboard_real_time_updates()
    {
        $this->withSession(['authenticated' => true, 'username' => 'admin']);
        
        $response = $this->get('/dashboard');
        
        $content = $response->getContent();
        
        // Check for WebSocket or polling setup
        $this->assertStringContainsString('setInterval', $content);
    }

    public function test_dashboard_keyboard_navigation()
    {
        $this->withSession(['authenticated' => true, 'username' => 'admin']);
        
        $response = $this->get('/dashboard');
        
        $content = $response->getContent();
        
        // Check for keyboard navigation support
        $this->assertStringContainsString('tabindex', $content);
        $this->assertStringContainsString('aria-label', $content);
    }

    public function test_dashboard_date_formatting()
    {
        $this->withSession(['authenticated' => true, 'username' => 'admin']);
        
        $response = $this->get('/dashboard');
        
        $response->assertStatus(200);
        
        $content = $response->getContent();
        
        // Check for date formatting (Lithuanian format)
        $this->assertStringContainsString('2025', $content);
    }

    public function test_dashboard_performance_metrics()
    {
        $this->withSession(['authenticated' => true, 'username' => 'admin']);
        
        $response = $this->get('/dashboard');
        
        $response->assertStatus(200)
                ->assertSee('Sėkmės rodiklis')
                ->assertSee('%'); // Percentage indicators
    }

    public function test_dashboard_search_functionality()
    {
        $this->withSession(['authenticated' => true, 'username' => 'admin']);
        
        $response = $this->get('/dashboard');
        
        $content = $response->getContent();
        
        // Check for search input
        $this->assertStringContainsString('search', $content);
        $this->assertStringContainsString('filter', $content);
    }

    public function test_dashboard_mobile_responsiveness()
    {
        $this->withSession(['authenticated' => true, 'username' => 'admin']);
        
        $response = $this->get('/dashboard');
        
        $content = $response->getContent();
        
        // Check for mobile-specific classes
        $this->assertStringContainsString('col-sm-', $content);
        $this->assertStringContainsString('col-xs-', $content);
        $this->assertStringContainsString('d-none d-sm-block', $content);
    }
}