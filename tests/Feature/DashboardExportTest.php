<?php

namespace Tests\Feature;

use App\Models\AnalysisJob;
use App\Models\TextAnalysis;
use App\Models\ComparisonMetric;
use Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;

class DashboardExportTest extends TestCase
{
    use RefreshDatabase;

    private AnalysisJob $analysisJob;
    private TextAnalysis $textAnalysis;
    private ComparisonMetric $comparisonMetric;

    protected function setUp(): void
    {
        parent::setUp();

        $this->analysisJob = AnalysisJob::factory()->create([
            'status' => 'completed',
            'name' => 'Export Test Analysis'
        ]);

        $this->textAnalysis = TextAnalysis::factory()->create([
            'job_id' => $this->analysisJob->job_id,
            'model_name' => 'claude-opus-4',
            'status' => 'completed'
        ]);

        $this->comparisonMetric = ComparisonMetric::factory()->create([
            'job_id' => $this->analysisJob->job_id,
            'text_id' => $this->textAnalysis->text_id,
            'model_name' => 'claude-opus-4'
        ]);
    }

    public function test_dashboard_export_csv_returns_valid_response()
    {
        $response = $this->getJson('/api/dashboard/export?format=csv');
        
        $response->assertStatus(200)
                ->assertHeader('Content-Type', 'text/csv; charset=utf-8')
                ->assertHeader('Content-Disposition', 'attachment; filename="dashboard_statistics.csv"');
    }

    public function test_dashboard_export_json_returns_valid_response()
    {
        $response = $this->getJson('/api/dashboard/export?format=json');
        
        $response->assertStatus(200)
                ->assertHeader('Content-Type', 'application/json')
                ->assertJsonStructure([
                    'global_statistics',
                    'recent_analyses',
                    'model_statistics',
                    'export_timestamp'
                ]);
    }

    public function test_dashboard_export_excel_returns_valid_response()
    {
        $response = $this->getJson('/api/dashboard/export?format=excel');
        
        $response->assertStatus(200)
                ->assertHeader('Content-Type', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet')
                ->assertHeader('Content-Disposition', 'attachment; filename="dashboard_statistics.xlsx"');
    }

    public function test_dashboard_export_invalid_format_returns_error()
    {
        $response = $this->getJson('/api/dashboard/export?format=invalid');
        
        $response->assertStatus(400)
                ->assertJson([
                    'error' => 'Invalid export format. Supported formats: csv, json, excel'
                ]);
    }

    public function test_dashboard_export_defaults_to_json_when_no_format()
    {
        $response = $this->getJson('/api/dashboard/export');
        
        $response->assertStatus(200)
                ->assertHeader('Content-Type', 'application/json');
    }

    public function test_dashboard_export_includes_global_statistics()
    {
        $response = $this->getJson('/api/dashboard/export?format=json');
        
        $response->assertStatus(200)
                ->assertJsonStructure([
                    'global_statistics' => [
                        'total_analyses',
                        'completed_analyses',
                        'failed_analyses',
                        'total_texts',
                        'total_models_used'
                    ]
                ]);
    }

    public function test_dashboard_export_includes_recent_analyses()
    {
        $response = $this->getJson('/api/dashboard/export?format=json');
        
        $response->assertStatus(200)
                ->assertJsonStructure([
                    'recent_analyses' => [
                        '*' => [
                            'job_id',
                            'name',
                            'status',
                            'created_at'
                        ]
                    ]
                ]);
    }

    public function test_dashboard_export_includes_model_statistics()
    {
        $response = $this->getJson('/api/dashboard/export?format=json');
        
        $response->assertStatus(200)
                ->assertJsonStructure([
                    'model_statistics' => [
                        '*' => [
                            'model_name',
                            'usage_count',
                            'success_rate'
                        ]
                    ]
                ]);
    }

    public function test_dashboard_export_includes_timestamp()
    {
        $response = $this->getJson('/api/dashboard/export?format=json');
        
        $response->assertStatus(200)
                ->assertJsonStructure(['export_timestamp']);
        
        $data = $response->json();
        $this->assertNotEmpty($data['export_timestamp']);
    }

    public function test_dashboard_export_csv_has_proper_headers()
    {
        $response = $this->get('/api/dashboard/export?format=csv');
        
        $response->assertStatus(200);
        
        $content = $response->getContent();
        $lines = explode("\n", $content);
        $headers = str_getcsv($lines[0]);
        
        $this->assertContains('Analysis ID', $headers);
        $this->assertContains('Name', $headers);
        $this->assertContains('Status', $headers);
        $this->assertContains('Created At', $headers);
    }

    public function test_dashboard_export_csv_includes_data_rows()
    {
        $response = $this->get('/api/dashboard/export?format=csv');
        
        $response->assertStatus(200);
        
        $content = $response->getContent();
        $lines = explode("\n", $content);
        
        // Should have header + at least one data row
        $this->assertGreaterThan(1, count($lines));
        
        // Check that our test data is included
        $this->assertStringContainsString($this->analysisJob->job_id, $content);
        $this->assertStringContainsString('Export Test Analysis', $content);
    }

    public function test_dashboard_export_respects_data_privacy()
    {
        $response = $this->getJson('/api/dashboard/export?format=json');
        
        $response->assertStatus(200);
        
        $data = $response->json();
        
        // Should not include sensitive text content in export
        $this->assertArrayNotHasKey('text_content', $data);
        $this->assertArrayNotHasKey('annotations', $data);
    }
}