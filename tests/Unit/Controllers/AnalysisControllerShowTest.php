<?php

namespace Tests\Unit\Controllers;

use Tests\TestCase;
use App\Http\Controllers\AnalysisController;
use App\Models\AnalysisJob;
use App\Models\TextAnalysis;
use App\Models\ComparisonMetric;
use App\Services\MetricsService;
use App\Services\ExportService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;

class AnalysisControllerShowTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Test that the show method provides correct text count and model count data.
     */
    public function test_show_method_provides_correct_text_and_model_counts(): void
    {
        // Create a job
        $job = AnalysisJob::factory()->completed()->create([
            'total_texts' => 2  // This represents 1 text × 2 models = 2 analysis jobs
        ]);

        // Create one unique text analysis
        $textAnalysis = TextAnalysis::factory()->create([
            'job_id' => $job->job_id,
            'text_id' => 'unique_text_1',
            'claude_annotations' => ['test' => 'data'],
            'gemini_annotations' => ['test' => 'data2']
        ]);

        // Create comparison metrics for two models
        ComparisonMetric::factory()->create([
            'job_id' => $job->job_id,
            'text_id' => 'unique_text_1',
            'model_name' => 'claude-opus-4'
        ]);

        ComparisonMetric::factory()->create([
            'job_id' => $job->job_id,
            'text_id' => 'unique_text_1',
            'model_name' => 'claude-sonnet-4'
        ]);

        // Create controller and call show method
        $controller = new AnalysisController(new MetricsService(), new ExportService());
        $request = new Request();
        
        $response = $controller->show($job->job_id, $request);

        // Extract view data
        $viewData = $response->getData();

        // Check that the correct counts are calculated
        $this->assertEquals(1, $viewData['actualTextCount']); // Only 1 unique text
        $this->assertEquals(2, $viewData['modelCount']); // 2 models used
        $this->assertEquals(2, $viewData['analysis']->total_texts); // 2 total analysis jobs

        // Check that models are correctly identified
        $this->assertContains('claude-opus-4', $viewData['usedModels']);
        $this->assertContains('claude-sonnet-4', $viewData['usedModels']);
    }

    /**
     * Test with multiple texts and multiple models.
     */
    public function test_show_method_with_multiple_texts_and_models(): void
    {
        $job = AnalysisJob::factory()->completed()->create([
            'total_texts' => 6  // 3 texts × 2 models = 6 analysis jobs
        ]);

        // Create three unique text analyses
        for ($i = 1; $i <= 3; $i++) {
            TextAnalysis::factory()->create([
                'job_id' => $job->job_id,
                'text_id' => "unique_text_{$i}",
                'claude_annotations' => ['test' => 'data'],
                'gpt_annotations' => ['test' => 'data2']
            ]);

            // Create metrics for each text with two models
            ComparisonMetric::factory()->create([
                'job_id' => $job->job_id,
                'text_id' => "unique_text_{$i}",
                'model_name' => 'claude-opus-4'
            ]);

            ComparisonMetric::factory()->create([
                'job_id' => $job->job_id,
                'text_id' => "unique_text_{$i}",
                'model_name' => 'gpt-4.1'
            ]);
        }

        $controller = new AnalysisController(new MetricsService(), new ExportService());
        $request = new Request();
        
        $response = $controller->show($job->job_id, $request);
        $viewData = $response->getData();

        $this->assertEquals(3, $viewData['actualTextCount']); // 3 unique texts
        $this->assertEquals(2, $viewData['modelCount']); // 2 models used
        $this->assertEquals(6, $viewData['analysis']->total_texts); // 6 total analysis jobs
    }

    /**
     * Test with no analyses.
     */
    public function test_show_method_with_no_analyses(): void
    {
        $job = AnalysisJob::factory()->completed()->create([
            'total_texts' => 0
        ]);

        $controller = new AnalysisController(new MetricsService(), new ExportService());
        $request = new Request();
        
        $response = $controller->show($job->job_id, $request);
        $viewData = $response->getData();

        $this->assertEquals(0, $viewData['actualTextCount']);
        $this->assertEquals(0, $viewData['modelCount']);
        $this->assertEmpty($viewData['usedModels']);
    }
}