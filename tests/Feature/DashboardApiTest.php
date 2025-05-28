<?php

namespace Tests\Feature;

use Tests\TestCase;
use App\Models\AnalysisJob;
use App\Models\TextAnalysis;
use App\Models\ComparisonMetric;
use Illuminate\Foundation\Testing\RefreshDatabase;

class DashboardApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_dashboard_export_api_returns_csv(): void
    {
        // Create test data
        $job = AnalysisJob::factory()->completed()->create();
        
        TextAnalysis::factory()->create([
            'job_id' => $job->job_id,
            'text_id' => 'test-text-1'
        ]);

        ComparisonMetric::factory()->create([
            'job_id' => $job->job_id,
            'text_id' => 'test-text-1',
            'model_name' => 'claude-opus-4',
            'precision' => 0.85,
            'recall' => 0.75,
            'f1_score' => 0.80
        ]);

        $response = $this->getJson('/api/dashboard/export');

        $response->assertStatus(200)
                ->assertHeader('Content-Type', 'text/csv; charset=UTF-8')
                ->assertHeader('Content-Disposition', 'attachment; filename="dashboard_statistics.csv"');

        $content = $response->getContent();
        $this->assertStringContainsString('job_id,text_count,model_count', $content);
        $this->assertStringContainsString($job->job_id, $content);
    }

    public function test_dashboard_export_api_with_no_data(): void
    {
        $response = $this->getJson('/api/dashboard/export');

        $response->assertStatus(200)
                ->assertHeader('Content-Type', 'text/csv; charset=UTF-8');

        $content = $response->getContent();
        $this->assertStringContainsString('job_id,text_count,model_count', $content);
    }

    public function test_dashboard_export_api_with_authentication(): void
    {
        // Test without authentication in production mode
        config(['app.env' => 'production']);
        
        $response = $this->getJson('/api/dashboard/export');

        // Should work for API endpoints even in production
        $response->assertStatus(200);
    }
}