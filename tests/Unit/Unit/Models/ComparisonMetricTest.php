<?php

namespace Tests\Unit\Unit\Models;

use App\Models\ComparisonMetric;
use App\Models\AnalysisJob;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ComparisonMetricTest extends TestCase
{
    use RefreshDatabase;

    public function test_comparison_metric_can_be_created(): void
    {
        $job = AnalysisJob::factory()->create();
        
        $metric = ComparisonMetric::factory()->create([
            'job_id' => $job->job_id,
            'text_id' => 'test-123',
            'model_name' => 'claude-sonnet-4',
            'true_positives' => 5,
            'false_positives' => 2,
            'false_negatives' => 1,
            'position_accuracy' => 0.85,
        ]);

        $this->assertDatabaseHas('comparison_metrics', [
            'job_id' => $job->job_id,
            'text_id' => 'test-123',
            'model_name' => 'claude-sonnet-4',
            'true_positives' => 5,
            'false_positives' => 2,
            'false_negatives' => 1,
        ]);
    }

    public function test_comparison_metric_has_required_fields(): void
    {
        $job = AnalysisJob::factory()->create();
        $metric = ComparisonMetric::factory()->forJob($job)->create();

        $this->assertNotNull($metric->job_id);
        $this->assertNotNull($metric->text_id);
        $this->assertNotNull($metric->model_name);
        $this->assertNotNull($metric->true_positives);
        $this->assertNotNull($metric->false_positives);
        $this->assertNotNull($metric->false_negatives);
        $this->assertNotNull($metric->position_accuracy);
    }

    public function test_comparison_metric_has_analysis_job_relationship(): void
    {
        $job = AnalysisJob::factory()->create();
        $metric = ComparisonMetric::factory()->forJob($job)->create();

        $this->assertInstanceOf(\Illuminate\Database\Eloquent\Relations\BelongsTo::class, $metric->analysisJob());
        $this->assertEquals($job->job_id, $metric->analysisJob->job_id);
    }

    public function test_factory_model_states(): void
    {
        $job = AnalysisJob::factory()->create();
        
        $claudeMetric = ComparisonMetric::factory()->claude()->forJob($job)->create();
        $geminiMetric = ComparisonMetric::factory()->gemini()->forJob($job)->create();
        $openaiMetric = ComparisonMetric::factory()->openai()->forJob($job)->create();

        $this->assertEquals('claude-sonnet-4', $claudeMetric->model_name);
        $this->assertEquals('gemini-2.5-pro', $geminiMetric->model_name);
        $this->assertEquals('gpt-4.1', $openaiMetric->model_name);
    }

    public function test_factory_accuracy_states(): void
    {
        $job = AnalysisJob::factory()->create();
        
        $perfectMetric = ComparisonMetric::factory()->perfectMatch()->forJob($job)->create();
        $noMatchMetric = ComparisonMetric::factory()->noMatch()->forJob($job)->create();
        $partialMetric = ComparisonMetric::factory()->partialMatch()->forJob($job)->create();
        $highAccuracyMetric = ComparisonMetric::factory()->highAccuracy()->forJob($job)->create();
        $lowAccuracyMetric = ComparisonMetric::factory()->lowAccuracy()->forJob($job)->create();

        // Perfect match
        $this->assertGreaterThan(0, $perfectMetric->true_positives);
        $this->assertEquals(0, $perfectMetric->false_positives);
        $this->assertEquals(0, $perfectMetric->false_negatives);
        $this->assertEquals(1.0, $perfectMetric->position_accuracy);

        // No match
        $this->assertEquals(0, $noMatchMetric->true_positives);
        $this->assertGreaterThan(0, $noMatchMetric->false_positives);
        $this->assertGreaterThan(0, $noMatchMetric->false_negatives);
        $this->assertEquals(0.0, $noMatchMetric->position_accuracy);

        // Partial match
        $this->assertGreaterThan(0, $partialMetric->true_positives);
        $this->assertGreaterThan(0, $partialMetric->false_positives);
        $this->assertGreaterThan(0, $partialMetric->false_negatives);
        $this->assertGreaterThan(0.0, $partialMetric->position_accuracy);

        // High accuracy
        $this->assertGreaterThanOrEqual(7, $highAccuracyMetric->true_positives);
        $this->assertLessThanOrEqual(1, $highAccuracyMetric->false_positives);
        $this->assertGreaterThanOrEqual(0.9, $highAccuracyMetric->position_accuracy);

        // Low accuracy
        $this->assertLessThanOrEqual(2, $lowAccuracyMetric->true_positives);
        $this->assertGreaterThanOrEqual(3, $lowAccuracyMetric->false_positives);
        $this->assertLessThanOrEqual(0.3, $lowAccuracyMetric->position_accuracy);
    }

    public function test_can_calculate_precision(): void
    {
        $job = AnalysisJob::factory()->create();
        $metric = ComparisonMetric::factory()->forJob($job)->create([
            'true_positives' => 8,
            'false_positives' => 2,
            'false_negatives' => 1,
        ]);

        // Precision = TP / (TP + FP) = 8 / (8 + 2) = 0.8
        $precision = $metric->true_positives / ($metric->true_positives + $metric->false_positives);
        $this->assertEquals(0.8, $precision);
    }

    public function test_can_calculate_recall(): void
    {
        $job = AnalysisJob::factory()->create();
        $metric = ComparisonMetric::factory()->forJob($job)->create([
            'true_positives' => 8,
            'false_positives' => 2,
            'false_negatives' => 1,
        ]);

        // Recall = TP / (TP + FN) = 8 / (8 + 1) = 0.889
        $recall = $metric->true_positives / ($metric->true_positives + $metric->false_negatives);
        $this->assertEqualsWithDelta(0.889, $recall, 0.001);
    }

    public function test_can_calculate_f1_score(): void
    {
        $job = AnalysisJob::factory()->create();
        $metric = ComparisonMetric::factory()->forJob($job)->create([
            'true_positives' => 8,
            'false_positives' => 2,
            'false_negatives' => 1,
        ]);

        $precision = $metric->true_positives / ($metric->true_positives + $metric->false_positives);
        $recall = $metric->true_positives / ($metric->true_positives + $metric->false_negatives);
        $f1 = 2 * ($precision * $recall) / ($precision + $recall);

        $this->assertEqualsWithDelta(0.842, $f1, 0.001);
    }

    public function test_handles_zero_division_cases(): void
    {
        $job = AnalysisJob::factory()->create();
        
        // Case where no predictions were made
        $noPredictionsMetric = ComparisonMetric::factory()->forJob($job)->create([
            'true_positives' => 0,
            'false_positives' => 0,
            'false_negatives' => 5,
        ]);

        // Case where no actual labels exist
        $noActualMetric = ComparisonMetric::factory()->forJob($job)->create([
            'true_positives' => 0,
            'false_positives' => 3,
            'false_negatives' => 0,
        ]);

        // These should not cause division by zero in calculations
        $this->assertEquals(0, $noPredictionsMetric->true_positives + $noPredictionsMetric->false_positives);
        $this->assertEquals(0, $noActualMetric->true_positives + $noActualMetric->false_negatives);
    }

    public function test_position_accuracy_is_decimal(): void
    {
        $job = AnalysisJob::factory()->create();
        $metric = ComparisonMetric::factory()->forJob($job)->create([
            'position_accuracy' => 0.756,
        ]);

        $this->assertEquals('0.7560', $metric->position_accuracy);
        $this->assertEquals(0.756, (float) $metric->position_accuracy);
    }

    public function test_belongs_to_specific_text_and_model(): void
    {
        $job = AnalysisJob::factory()->create();
        
        $metric1 = ComparisonMetric::factory()->forJob($job)->forText('text-1')->claude()->create();
        $metric2 = ComparisonMetric::factory()->forJob($job)->forText('text-1')->gemini()->create();
        $metric3 = ComparisonMetric::factory()->forJob($job)->forText('text-2')->claude()->create();

        $this->assertEquals('text-1', $metric1->text_id);
        $this->assertEquals('claude-sonnet-4', $metric1->model_name);
        
        $this->assertEquals('text-1', $metric2->text_id);
        $this->assertEquals('gemini-2.5-pro', $metric2->model_name);
        
        $this->assertEquals('text-2', $metric3->text_id);
        $this->assertEquals('claude-sonnet-4', $metric3->model_name);
    }

    public function test_can_have_different_metrics_for_same_job(): void
    {
        $job = AnalysisJob::factory()->create();
        
        ComparisonMetric::factory()->count(3)->forJob($job)->create();

        $this->assertDatabaseCount('comparison_metrics', 3);
        
        $metrics = ComparisonMetric::where('job_id', $job->job_id)->get();
        $this->assertCount(3, $metrics);
        
        // All should belong to the same job
        foreach ($metrics as $metric) {
            $this->assertEquals($job->job_id, $metric->job_id);
        }
    }
}