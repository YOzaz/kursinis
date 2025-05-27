<?php

namespace Tests\Unit\Unit\Services;

use App\Services\ExportService;
use App\Models\AnalysisJob;
use App\Models\TextAnalysis;
use App\Models\ComparisonMetric;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ExportServiceTest extends TestCase
{
    use RefreshDatabase;

    private ExportService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new ExportService();
    }

    public function test_exports_analysis_results_to_csv(): void
    {
        $analysisJob = AnalysisJob::factory()->completed()->create([
            'name' => 'Test Analysis',
            'custom_prompt' => 'Test custom prompt'
        ]);
        
        $textAnalysis = TextAnalysis::factory()->create([
            'job_id' => $analysisJob->job_id,
            'text_id' => 'test-123',
            'content' => 'Test content for analysis'
        ]);
        
        ComparisonMetric::factory()->create([
            'job_id' => $analysisJob->job_id,
            'text_id' => 'test-123',
            'model_name' => 'claude-sonnet-4'
        ]);

        $response = $this->service->exportToCsv($analysisJob->job_id);

        $this->assertInstanceOf(\Illuminate\Http\Response::class, $response);
        $this->assertEquals(200, $response->getStatusCode());
        
        $content = $response->getContent();
        $this->assertStringContainsString('text_id', $content);
        $this->assertStringContainsString('model_name', $content);
        $this->assertStringContainsString('test-123', $content);
    }

    public function test_csv_export_has_correct_headers(): void
    {
        $analysisJob = AnalysisJob::factory()->completed()->create();
        
        TextAnalysis::factory()->create([
            'job_id' => $analysisJob->job_id,
            'text_id' => 'test-header',
        ]);

        $response = $this->service->exportToCsv($analysisJob->job_id);
        $content = $response->getContent();
        $lines = explode("\n", $content);
        $headers = str_getcsv($lines[0]);

        $expectedHeaders = [
            'job_id', 'text_id', 'text_content', 'model_name', 
            'llm_annotations', 'propaganda_detected', 'techniques_found', 'narratives_found'
        ];

        foreach ($expectedHeaders as $header) {
            $this->assertContains($header, $headers);
        }
    }

    public function test_exports_multiple_results(): void
    {
        $analysisJob = AnalysisJob::factory()->completed()->create();
        
        // Create multiple text analyses
        TextAnalysis::factory()->count(3)->create([
            'job_id' => $analysisJob->job_id,
        ]);

        $response = $this->service->exportToCsv($analysisJob->job_id);
        $content = $response->getContent();
        $lines = explode("\n", trim($content));

        // Should have header + at least 1 line (even if empty data)
        $this->assertGreaterThanOrEqual(2, count($lines));
    }

    public function test_handles_empty_analysis(): void
    {
        $analysisJob = AnalysisJob::factory()->completed()->create();

        $response = $this->service->exportToCsv($analysisJob->job_id);

        $this->assertInstanceOf(\Illuminate\Http\Response::class, $response);
        $content = $response->getContent();
        $this->assertStringContainsString('text_id', $content); // Headers should still be present
    }

    public function test_csv_format_is_valid(): void
    {
        $analysisJob = AnalysisJob::factory()->completed()->create();
        
        TextAnalysis::factory()->create([
            'job_id' => $analysisJob->job_id,
            'text_id' => 'csv-test',
        ]);

        $response = $this->service->exportToCsv($analysisJob->job_id);
        $content = $response->getContent();
        $lines = explode("\n", trim($content));

        // Each line should be parseable as CSV
        foreach ($lines as $line) {
            if (!empty(trim($line))) {
                $parsed = str_getcsv($line);
                $this->assertIsArray($parsed);
                $this->assertGreaterThan(0, count($parsed));
            }
        }
    }
}