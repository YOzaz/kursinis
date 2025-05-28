<?php

namespace Tests\Unit\Services;

use Tests\TestCase;
use App\Services\MetricsService;
use App\Models\AnalysisJob;
use App\Models\TextAnalysis;
use App\Models\ComparisonMetric;
use Illuminate\Foundation\Testing\RefreshDatabase;

class MetricsServiceStatisticsTest extends TestCase
{
    use RefreshDatabase;

    private MetricsService $metricsService;

    protected function setUp(): void
    {
        parent::setUp();
        $this->metricsService = new MetricsService();
    }

    /**
     * Test that job statistics calculation returns the correct structure.
     */
    public function test_calculate_job_statistics_returns_correct_structure(): void
    {
        // Create a test analysis job
        $job = AnalysisJob::factory()->completed()->create([
            'total_texts' => 2,
            'processed_texts' => 2
        ]);

        // Create text analyses
        $textAnalysis1 = TextAnalysis::factory()->create([
            'job_id' => $job->job_id,
            'text_id' => 'text1'
        ]);

        $textAnalysis2 = TextAnalysis::factory()->create([
            'job_id' => $job->job_id,
            'text_id' => 'text2'
        ]);

        // Create comparison metrics
        ComparisonMetric::factory()->create([
            'job_id' => $job->job_id,
            'text_id' => 'text1',
            'model_name' => 'claude-opus-4',
            'precision' => 0.85,
            'recall' => 0.75,
            'f1_score' => 0.80,
            'true_positives' => 8,
            'false_positives' => 2,
            'false_negatives' => 3
        ]);

        ComparisonMetric::factory()->create([
            'job_id' => $job->job_id,
            'text_id' => 'text2',
            'model_name' => 'claude-opus-4',
            'precision' => 0.90,
            'recall' => 0.80,
            'f1_score' => 0.85,
            'true_positives' => 9,
            'false_positives' => 1,
            'false_negatives' => 2
        ]);

        $statistics = $this->metricsService->calculateJobStatistics($job);

        // Check overall structure
        $this->assertArrayHasKey('total_texts', $statistics);
        $this->assertArrayHasKey('models_used', $statistics);
        $this->assertArrayHasKey('overall_metrics', $statistics);
        $this->assertArrayHasKey('per_model_metrics', $statistics);
        $this->assertArrayHasKey('execution_time', $statistics);

        // Check overall metrics structure
        $this->assertArrayHasKey('avg_precision', $statistics['overall_metrics']);
        $this->assertArrayHasKey('avg_recall', $statistics['overall_metrics']);
        $this->assertArrayHasKey('avg_f1', $statistics['overall_metrics']);
        $this->assertArrayHasKey('total_comparisons', $statistics['overall_metrics']);

        // Check calculated values
        $this->assertEquals(2, $statistics['total_texts']);
        $this->assertContains('claude-opus-4', $statistics['models_used']);
        $this->assertEquals(0.875, $statistics['overall_metrics']['avg_precision']); // (0.85 + 0.90) / 2
        $this->assertEquals(0.775, $statistics['overall_metrics']['avg_recall']); // (0.75 + 0.80) / 2
        $this->assertEquals(0.825, $statistics['overall_metrics']['avg_f1']); // (0.80 + 0.85) / 2
    }

    /**
     * Test that statistics work with multiple models.
     */
    public function test_calculate_job_statistics_with_multiple_models(): void
    {
        $job = AnalysisJob::factory()->completed()->create();

        $textAnalysis = TextAnalysis::factory()->create([
            'job_id' => $job->job_id,
            'text_id' => 'text1'
        ]);

        // Create metrics for two different models
        ComparisonMetric::factory()->create([
            'job_id' => $job->job_id,
            'text_id' => 'text1',
            'model_name' => 'claude-opus-4',
            'precision' => 0.85,
            'recall' => 0.75,
            'f1_score' => 0.80
        ]);

        ComparisonMetric::factory()->create([
            'job_id' => $job->job_id,
            'text_id' => 'text1',
            'model_name' => 'claude-sonnet-4',
            'precision' => 0.70,
            'recall' => 0.80,
            'f1_score' => 0.75
        ]);

        $statistics = $this->metricsService->calculateJobStatistics($job);

        // Check that both models are included
        $this->assertContains('claude-opus-4', $statistics['models_used']);
        $this->assertContains('claude-sonnet-4', $statistics['models_used']);

        // Check per-model metrics
        $this->assertArrayHasKey('claude-opus-4', $statistics['per_model_metrics']);
        $this->assertArrayHasKey('claude-sonnet-4', $statistics['per_model_metrics']);

        // Check overall averages
        $this->assertEquals(0.775, $statistics['overall_metrics']['avg_precision']); // (0.85 + 0.70) / 2
        $this->assertEquals(0.775, $statistics['overall_metrics']['avg_recall']); // (0.75 + 0.80) / 2
        $this->assertEquals(0.775, $statistics['overall_metrics']['avg_f1']); // (0.80 + 0.75) / 2
    }

    /**
     * Test that empty job returns proper default values.
     */
    public function test_calculate_job_statistics_empty_job(): void
    {
        $job = AnalysisJob::factory()->completed()->create();

        $statistics = $this->metricsService->calculateJobStatistics($job);

        $this->assertEquals(0, $statistics['total_texts']);
        $this->assertEmpty($statistics['models_used']);
        $this->assertEmpty($statistics['overall_metrics']);
        $this->assertEmpty($statistics['per_model_metrics']);
        $this->assertEquals(0, $statistics['execution_time']);
    }
}