<?php

namespace Tests\Feature\Controllers;

use Tests\TestCase;
use App\Models\AnalysisJob;
use App\Models\TextAnalysis;
use App\Models\ComparisonMetric;
use Illuminate\Foundation\Testing\RefreshDatabase;

class DashboardControllerFeatureTest extends TestCase
{
    use RefreshDatabase;

    public function test_dashboard_index_loads_with_statistics(): void
    {
        $this->withSession(['authenticated' => true, 'username' => 'admin']);

        // Create test data
        $job = AnalysisJob::factory()->completed()->create();
        
        TextAnalysis::factory()->create([
            'job_id' => $job->job_id,
            'text_id' => 'test-text-1'
        ]);

        ComparisonMetric::factory()->create([
            'job_id' => $job->job_id,
            'text_id' => 'test-text-1',
            'model_name' => 'claude-opus-4'
        ]);

        $response = $this->get('/dashboard');

        $response->assertStatus(200)
                ->assertSee('Dashboard')
                ->assertSee('Viso analizių')
                ->assertSee('tachometer-alt')
                ->assertViewIs('dashboard.index');
    }

    public function test_dashboard_redirects_when_not_authenticated(): void
    {
        $response = $this->get('/dashboard');

        $response->assertRedirect('/login');
    }

    public function test_dashboard_displays_global_statistics(): void
    {
        $this->withSession(['authenticated' => true, 'username' => 'admin']);

        // Create multiple completed jobs
        for ($i = 0; $i < 3; $i++) {
            $job = AnalysisJob::factory()->completed()->create();
            
            TextAnalysis::factory()->create([
                'job_id' => $job->job_id,
                'text_id' => "test-text-{$i}"
            ]);
        }

        $response = $this->get('/dashboard');

        $content = $response->getContent();
        $this->assertStringContainsString('3', $content); // Should show 3 analyses
        $this->assertStringContainsString('card', $content); // Bootstrap cards
        $this->assertStringContainsString('bg-gradient', $content); // Styled cards
    }

    public function test_dashboard_with_empty_data(): void
    {
        $this->withSession(['authenticated' => true, 'username' => 'admin']);

        $response = $this->get('/dashboard');

        $response->assertStatus(200)
                ->assertSee('Dashboard')
                ->assertSee('0'); // Should show 0 for empty stats
    }

    public function test_dashboard_contains_export_functionality(): void
    {
        $this->withSession(['authenticated' => true, 'username' => 'admin']);

        $response = $this->get('/dashboard');

        $content = $response->getContent();
        $this->assertStringContainsString('exportStatsBtn', $content);
        $this->assertStringContainsString('Eksportuoti', $content);
    }

    public function test_dashboard_performance_table_exists(): void
    {
        $this->withSession(['authenticated' => true, 'username' => 'admin']);

        // Create test data with metrics
        $job = AnalysisJob::factory()->completed()->create();
        
        ComparisonMetric::factory()->create([
            'job_id' => $job->job_id,
            'text_id' => 'test-text-1',
            'model_name' => 'claude-opus-4',
            'precision' => 0.85,
            'recall' => 0.75,
            'f1_score' => 0.80
        ]);

        $response = $this->get('/dashboard');

        $content = $response->getContent();
        $this->assertStringContainsString('table', $content);
        $this->assertStringContainsString('claude-opus-4', $content);
    }
}