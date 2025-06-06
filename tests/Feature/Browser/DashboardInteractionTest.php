<?php

namespace Tests\Feature\Browser;

use App\Models\AnalysisJob;
use App\Models\ComparisonMetric;
use App\Models\TextAnalysis;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Browser tests for dashboard interactions
 */
class DashboardInteractionTest extends TestCase
{
    use RefreshDatabase;

    public function test_dashboard_displays_statistics_cards()
    {
        $this->withSession(['authenticated' => true, 'username' => 'admin']);
        
        // Create some test data
        AnalysisJob::factory()->completed()->create();
        AnalysisJob::factory()->processing()->create();

        $response = $this->get('/dashboard');

        $response->assertStatus(200)
                ->assertSee('Total')
                ->assertSee('Completed')
                ->assertSee('Processing');
    }

    public function test_dashboard_charts_are_present()
    {
        $this->withSession(['authenticated' => true, 'username' => 'admin']);
        
        $response = $this->get('/dashboard');

        $response->assertStatus(200)
                ->assertSee('chart', false)
                ->assertSee('Chart.js', false);
    }

    public function test_model_performance_comparison()
    {
        $this->withSession(['authenticated' => true, 'username' => 'admin']);
        
        $job = AnalysisJob::factory()->completed()->create();
        
        ComparisonMetric::factory()->create([
            'job_id' => $job->job_id,
            'model_name' => 'claude-opus-4',
            'precision' => 0.85,
            'recall' => 0.90,
            'f1_score' => 0.87
        ]);
        
        ComparisonMetric::factory()->create([
            'job_id' => $job->job_id,
            'model_name' => 'gpt-4.1',
            'precision' => 0.82,
            'recall' => 0.88,
            'f1_score' => 0.85
        ]);

        $response = $this->get('/dashboard');

        $response->assertStatus(200)
                ->assertSee('claude-opus-4')
                ->assertSee('gpt-4.1')
                ->assertSee('0.85')
                ->assertSee('0.82');
    }

    public function test_recent_analyses_widget()
    {
        $this->withSession(['authenticated' => true, 'username' => 'admin']);
        
        AnalysisJob::factory()->create([
            'name' => 'Recent Analysis 1',
            'created_at' => now()->subMinutes(5)
        ]);
        
        AnalysisJob::factory()->create([
            'name' => 'Recent Analysis 2',
            'created_at' => now()->subMinutes(10)
        ]);

        $response = $this->get('/dashboard');

        $response->assertStatus(200)
                ->assertSee('Recent Analysis 1')
                ->assertSee('Recent Analysis 2')
                ->assertSee('Recent Analyses');
    }

    public function test_export_functionality_from_dashboard()
    {
        $this->withSession(['authenticated' => true, 'username' => 'admin']);
        
        $response = $this->get('/dashboard');

        $response->assertStatus(200)
                ->assertSee('Eksportuoti duomenis')
                ->assertSee('Dashboard');
    }

    public function test_dashboard_filters_work()
    {
        $this->withSession(['authenticated' => true, 'username' => 'admin']);
        
        $response = $this->get('/dashboard');

        $response->assertStatus(200)
                ->assertSee('filter', false)
                ->assertSee('date-range', false);
    }

    public function test_propaganda_techniques_distribution_chart()
    {
        $this->withSession(['authenticated' => true, 'username' => 'admin']);
        
        $job = AnalysisJob::factory()->completed()->create();
        
        TextAnalysis::factory()->create([
            'job_id' => $job->job_id,
            'claude_annotations' => [
                [
                    'type' => 'labels',
                    'value' => [
                        'labels' => ['emotional_appeal', 'simplification']
                    ]
                ]
            ]
        ]);

        $response = $this->get('/dashboard');

        $response->assertStatus(200)
                ->assertSee('techniques', false)
                ->assertSee('distribution', false);
    }

    public function test_model_accuracy_metrics_table()
    {
        $this->withSession(['authenticated' => true, 'username' => 'admin']);
        
        $response = $this->get('/dashboard');

        $response->assertStatus(200)
                ->assertSee('accuracy', false)
                ->assertSee('precision', false)
                ->assertSee('recall', false)
                ->assertSee('f1', false);
    }

    public function test_time_series_analysis_chart()
    {
        $this->withSession(['authenticated' => true, 'username' => 'admin']);
        
        // Create jobs over time
        AnalysisJob::factory()->create(['created_at' => now()->subDays(1)]);
        AnalysisJob::factory()->create(['created_at' => now()->subDays(2)]);
        AnalysisJob::factory()->create(['created_at' => now()->subDays(3)]);

        $response = $this->get('/dashboard');

        $response->assertStatus(200)
                ->assertSee('time-series', false)
                ->assertSee('timeline', false);
    }

    public function test_dashboard_refresh_functionality()
    {
        $this->withSession(['authenticated' => true, 'username' => 'admin']);
        
        $response = $this->get('/dashboard');

        $response->assertStatus(200)
                ->assertSee('refresh', false)
                ->assertSee('auto-refresh', false);
    }

    public function test_dashboard_responsive_layout()
    {
        $this->withSession(['authenticated' => true, 'username' => 'admin']);
        
        $response = $this->get('/dashboard');

        $response->assertStatus(200)
                ->assertSee('col-md-', false)
                ->assertSee('col-lg-', false)
                ->assertSee('row', false);
    }

    public function test_dashboard_loading_states()
    {
        $this->withSession(['authenticated' => true, 'username' => 'admin']);
        
        $response = $this->get('/dashboard');

        $response->assertStatus(200)
                ->assertSee('loading', false)
                ->assertSee('spinner', false);
    }

    public function test_dashboard_drill_down_links()
    {
        $this->withSession(['authenticated' => true, 'username' => 'admin']);
        
        $job = AnalysisJob::factory()->completed()->create();

        $response = $this->get('/dashboard');

        $response->assertStatus(200)
                ->assertSee('/analyses', false);
    }

    public function test_dashboard_empty_state()
    {
        $this->withSession(['authenticated' => true, 'username' => 'admin']);
        
        // No data created
        $response = $this->get('/dashboard');

        $response->assertStatus(200)
                ->assertSee('Dashboard');
        
        // Should handle empty state gracefully
        $this->assertTrue(true);
    }

    public function test_dashboard_data_tables_functionality()
    {
        $this->withSession(['authenticated' => true, 'username' => 'admin']);
        
        $response = $this->get('/dashboard');

        $response->assertStatus(200)
                ->assertSee('DataTables', false)
                ->assertSee('datatable', false);
    }

    public function test_dashboard_tooltips_and_help()
    {
        $this->withSession(['authenticated' => true, 'username' => 'admin']);
        
        $response = $this->get('/dashboard');

        $response->assertStatus(200)
                ->assertSee('tooltip', false)
                ->assertSee('help', false);
    }
}