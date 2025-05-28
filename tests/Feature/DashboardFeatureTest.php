<?php

namespace Tests\Feature;

use App\Models\AnalysisJob;
use App\Models\TextAnalysis;
use App\Models\ComparisonMetric;
use Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;

class DashboardFeatureTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // Create test data
        $analysisJob = AnalysisJob::factory()->create([
            'status' => 'completed',
            'name' => 'Dashboard Test Analysis'
        ]);

        TextAnalysis::factory()->create([
            'job_id' => $analysisJob->job_id,
            'claude_actual_model' => 'claude-opus-4'
        ]);

        ComparisonMetric::factory()->create([
            'job_id' => $analysisJob->job_id,
            'model_name' => 'claude-opus-4'
        ]);
    }

    public function test_dashboard_requires_authentication()
    {
        $response = $this->get('/dashboard');
        
        $response->assertRedirect('/login');
    }

    public function test_dashboard_accessible_when_authenticated()
    {
        $this->withSession(['authenticated' => true, 'username' => 'admin']);
        
        $response = $this->get('/dashboard');
        
        $response->assertStatus(200)
                ->assertSee('Dashboard')
                ->assertSee('Viso analizių');
    }

    public function test_dashboard_displays_global_statistics()
    {
        $this->withSession(['authenticated' => true, 'username' => 'admin']);
        
        $response = $this->get('/dashboard');
        
        $response->assertStatus(200)
                ->assertSee('Viso analizių')
                ->assertSee('Vidutinis F1 balas')
                ->assertSee('Aktyvūs modeliai')
                ->assertSee('Vidutinis laikas');
    }

    public function test_dashboard_displays_recent_analyses()
    {
        $this->withSession(['authenticated' => true, 'username' => 'admin']);
        
        $response = $this->get('/dashboard');
        
        $response->assertStatus(200)
                ->assertSee('Paskutinės analizės')
                ->assertSee('Dashboard Test Analysis');
    }

    public function test_dashboard_uses_correct_view()
    {
        $this->withSession(['authenticated' => true, 'username' => 'admin']);
        
        $response = $this->get('/dashboard');
        
        $response->assertStatus(200)
                ->assertViewIs('dashboard.index');
    }

    public function test_dashboard_passes_required_data_to_view()
    {
        $this->withSession(['authenticated' => true, 'username' => 'admin']);
        
        $response = $this->get('/dashboard');
        
        $response->assertStatus(200)
                ->assertViewHas('globalStats')
                ->assertViewHas('recentAnalyses');
    }

    public function test_dashboard_shows_statistics_cards()
    {
        $this->withSession(['authenticated' => true, 'username' => 'admin']);
        
        $response = $this->get('/dashboard');
        
        $response->assertStatus(200)
                ->assertSee('1') // Should show 1 total analysis
                ->assertSee('card'); // Bootstrap cards should be present
    }

    public function test_dashboard_recent_analyses_table()
    {
        $this->withSession(['authenticated' => true, 'username' => 'admin']);
        
        $response = $this->get('/dashboard');
        
        $response->assertStatus(200)
                ->assertSee('Modelių našumo palyginimas')
                ->assertSee('Modelis')
                ->assertSee('F1 balas')
                ->assertSee('Tikslumas');
    }

    public function test_dashboard_handles_empty_data()
    {
        // Clear all test data
        AnalysisJob::truncate();
        TextAnalysis::truncate();
        ComparisonMetric::truncate();
        
        $this->withSession(['authenticated' => true, 'username' => 'admin']);
        
        $response = $this->get('/dashboard');
        
        $response->assertStatus(200)
                ->assertSee('0'); // Should show 0 for empty statistics
    }

    public function test_dashboard_shows_model_usage_statistics()
    {
        $this->withSession(['authenticated' => true, 'username' => 'admin']);
        
        $response = $this->get('/dashboard');
        
        $response->assertStatus(200)
                ->assertSee('claude-opus-4'); // Should show the model used in test data
    }
}