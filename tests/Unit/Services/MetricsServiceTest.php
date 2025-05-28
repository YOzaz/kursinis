<?php

namespace Tests\Unit\Services;

use App\Services\MetricsService;
use App\Models\TextAnalysis;
use App\Models\AnalysisJob;
use App\Models\ComparisonMetric;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MetricsServiceTest extends TestCase
{
    use RefreshDatabase;

    private MetricsService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new MetricsService();
    }

    public function test_can_instantiate_service(): void
    {
        $this->assertInstanceOf(MetricsService::class, $this->service);
    }

    public function test_calculates_metrics_for_text_with_matching_annotations(): void
    {
        $analysisJob = AnalysisJob::factory()->create();
        $textAnalysis = TextAnalysis::factory()->create([
            'job_id' => $analysisJob->job_id,
            'expert_annotations' => [
                ['result' => [
                    ['type' => 'labels', 'value' => ['start' => 0, 'end' => 10, 'text' => 'propaganda', 'labels' => ['propaganda']]]
                ]]
            ],
            'claude_annotations' => [
                'annotations' => [
                    ['type' => 'labels', 'value' => ['start' => 0, 'end' => 10, 'text' => 'propaganda', 'labels' => ['propaganda']]]
                ]
            ]
        ]);

        $metric = $this->service->calculateMetricsForText($textAnalysis, 'claude', $analysisJob->job_id);

        $this->assertInstanceOf(ComparisonMetric::class, $metric);
        $this->assertEquals(1, $metric->true_positives);
        $this->assertEquals(0, $metric->false_positives);
        $this->assertEquals(0, $metric->false_negatives);
    }

    public function test_calculates_metrics_for_text_with_no_matches(): void
    {
        $analysisJob = AnalysisJob::factory()->create();
        $textAnalysis = TextAnalysis::factory()->create([
            'job_id' => $analysisJob->job_id,
            'expert_annotations' => [
                ['result' => [
                    ['type' => 'labels', 'value' => ['start' => 0, 'end' => 10, 'text' => 'propaganda', 'labels' => ['propaganda']]]
                ]]
            ],
            'claude_annotations' => [
                'annotations' => [
                    ['type' => 'labels', 'value' => ['start' => 50, 'end' => 60, 'text' => 'different', 'labels' => ['different']]]
                ]
            ]
        ]);

        $metric = $this->service->calculateMetricsForText($textAnalysis, 'claude', $analysisJob->job_id);

        $this->assertEquals(0, $metric->true_positives);
        $this->assertEquals(1, $metric->false_positives);
        $this->assertEquals(1, $metric->false_negatives);
    }

    public function test_handles_empty_expert_annotations(): void
    {
        $analysisJob = AnalysisJob::factory()->create();
        $textAnalysis = TextAnalysis::factory()->create([
            'job_id' => $analysisJob->job_id,
            'expert_annotations' => [],
            'claude_annotations' => [
                'annotations' => [
                    ['type' => 'labels', 'value' => ['start' => 0, 'end' => 10, 'text' => 'test', 'labels' => ['test']]]
                ]
            ]
        ]);

        $metric = $this->service->calculateMetricsForText($textAnalysis, 'claude', $analysisJob->job_id);

        $this->assertEquals(0, $metric->true_positives);
        $this->assertEquals(0, $metric->false_positives);
        $this->assertEquals(0, $metric->false_negatives);
    }

    public function test_handles_empty_model_annotations(): void
    {
        $analysisJob = AnalysisJob::factory()->create();
        $textAnalysis = TextAnalysis::factory()->create([
            'job_id' => $analysisJob->job_id,
            'expert_annotations' => [
                ['result' => [
                    ['type' => 'labels', 'value' => ['start' => 0, 'end' => 10, 'text' => 'propaganda', 'labels' => ['propaganda']]]
                ]]
            ],
            'claude_annotations' => null
        ]);

        $metric = $this->service->calculateMetricsForText($textAnalysis, 'claude', $analysisJob->job_id);

        $this->assertEquals(0, $metric->true_positives);
        $this->assertEquals(0, $metric->false_positives);
        $this->assertEquals(0, $metric->false_negatives);
    }

    public function test_calculates_aggregated_metrics(): void
    {
        $analysisJob = AnalysisJob::factory()->create();
        
        // Create some test metrics
        ComparisonMetric::factory()->create([
            'job_id' => $analysisJob->job_id,
            'model_name' => 'claude-4',
            'precision' => 0.8,
            'recall' => 0.9,
            'f1_score' => 0.85,
            'position_accuracy' => 0.75
        ]);
        
        ComparisonMetric::factory()->create([
            'job_id' => $analysisJob->job_id,
            'model_name' => 'claude-4',
            'precision' => 0.9,
            'recall' => 0.8,
            'f1_score' => 0.85,
            'position_accuracy' => 0.85
        ]);

        $results = $this->service->calculateAggregatedMetrics($analysisJob->job_id);

        $this->assertArrayHasKey('claude-4', $results);
        $this->assertEquals(0.85, $results['claude-4']['precision']);
        $this->assertEquals(0.85, $results['claude-4']['recall']);
        $this->assertEquals(0.85, $results['claude-4']['f1_score']);
        $this->assertEquals(0.8, $results['claude-4']['position_accuracy']);
    }

    public function test_handles_partial_matches(): void
    {
        $analysisJob = AnalysisJob::factory()->create();
        $textAnalysis = TextAnalysis::factory()->create([
            'job_id' => $analysisJob->job_id,
            'expert_annotations' => [
                ['result' => [
                    ['type' => 'labels', 'value' => ['start' => 0, 'end' => 10, 'text' => 'propaganda', 'labels' => ['propaganda']]],
                    ['type' => 'labels', 'value' => ['start' => 20, 'end' => 30, 'text' => 'technique', 'labels' => ['technique']]]
                ]]
            ],
            'claude_annotations' => [
                'annotations' => [
                    ['type' => 'labels', 'value' => ['start' => 0, 'end' => 10, 'text' => 'propaganda', 'labels' => ['propaganda']]]
                ]
            ]
        ]);

        $metric = $this->service->calculateMetricsForText($textAnalysis, 'claude', $analysisJob->job_id);

        $this->assertEquals(1, $metric->true_positives);
        $this->assertEquals(0, $metric->false_positives);
        $this->assertEquals(1, $metric->false_negatives);
    }

    public function test_calculates_position_accuracy_with_tolerance(): void
    {
        $analysisJob = AnalysisJob::factory()->create();
        $textAnalysis = TextAnalysis::factory()->create([
            'job_id' => $analysisJob->job_id,
            'expert_annotations' => [
                ['result' => [
                    ['type' => 'labels', 'value' => ['start' => 0, 'end' => 10, 'text' => 'propaganda', 'labels' => ['propaganda']]]
                ]]
            ],
            'claude_annotations' => [
                'annotations' => [
                    ['type' => 'labels', 'value' => ['start' => 2, 'end' => 12, 'text' => 'propaganda', 'labels' => ['propaganda']]]
                ]
            ]
        ]);

        $metric = $this->service->calculateMetricsForText($textAnalysis, 'claude', $analysisJob->job_id);

        // Should still match due to position tolerance
        $this->assertEquals(1, $metric->true_positives);
        $this->assertGreaterThan('0.0000', $metric->position_accuracy);
    }

    public function test_calculates_cohens_kappa(): void
    {
        $analysisJob = AnalysisJob::factory()->create();
        
        // Create metrics with some agreement and disagreement
        ComparisonMetric::factory()->count(5)->create([
            'job_id' => $analysisJob->job_id,
            'model_name' => 'claude-4',
            'true_positives' => 8,
            'false_positives' => 2,
            'false_negatives' => 1
        ]);

        $results = $this->service->calculateAggregatedMetrics($analysisJob->job_id);

        $this->assertArrayHasKey('claude-4', $results);
        $this->assertArrayHasKey('cohen_kappa', $results['claude-4']);
        $this->assertIsFloat($results['claude-4']['cohen_kappa']);
    }

    public function test_handles_false_positives(): void
    {
        $analysisJob = AnalysisJob::factory()->create();
        $textAnalysis = TextAnalysis::factory()->create([
            'job_id' => $analysisJob->job_id,
            'expert_annotations' => [], // No expert annotations
            'claude_annotations' => [
                'annotations' => [
                    ['type' => 'labels', 'value' => ['start' => 0, 'end' => 10, 'text' => 'false', 'labels' => ['propaganda']]]
                ]
            ]
        ]);

        $metric = $this->service->calculateMetricsForText($textAnalysis, 'claude', $analysisJob->job_id);

        $this->assertEquals(0, $metric->true_positives);
        $this->assertEquals(0, $metric->false_positives);
        $this->assertEquals(0, $metric->false_negatives);
    }

    public function test_handles_false_negatives(): void
    {
        $analysisJob = AnalysisJob::factory()->create();
        $textAnalysis = TextAnalysis::factory()->create([
            'job_id' => $analysisJob->job_id,
            'expert_annotations' => [
                ['result' => [
                    ['type' => 'labels', 'value' => ['start' => 0, 'end' => 10, 'text' => 'missed', 'labels' => ['propaganda']]]
                ]]
            ],
            'claude_annotations' => [
                'annotations' => [] // No model annotations
            ]
        ]);

        $metric = $this->service->calculateMetricsForText($textAnalysis, 'claude', $analysisJob->job_id);

        $this->assertEquals(0, $metric->true_positives);
        $this->assertEquals(0, $metric->false_positives);
        $this->assertEquals(1, $metric->false_negatives);
    }

    public function test_supports_different_technique_types(): void
    {
        $analysisJob = AnalysisJob::factory()->create();
        $textAnalysis = TextAnalysis::factory()->create([
            'job_id' => $analysisJob->job_id,
            'expert_annotations' => [
                ['result' => [
                    ['type' => 'labels', 'value' => ['start' => 0, 'end' => 10, 'text' => 'fear', 'labels' => ['appeal_to_fear']]],
                    ['type' => 'labels', 'value' => ['start' => 20, 'end' => 30, 'text' => 'loaded', 'labels' => ['loaded_language']]]
                ]]
            ],
            'claude_annotations' => [
                'annotations' => [
                    ['type' => 'labels', 'value' => ['start' => 0, 'end' => 10, 'text' => 'fear', 'labels' => ['appeal_to_fear']]],
                    ['type' => 'labels', 'value' => ['start' => 20, 'end' => 30, 'text' => 'loaded', 'labels' => ['loaded_language']]]
                ]
            ]
        ]);

        $metric = $this->service->calculateMetricsForText($textAnalysis, 'claude', $analysisJob->job_id);

        $this->assertEquals(2, $metric->true_positives);
        $this->assertEquals(0, $metric->false_positives);
        $this->assertEquals(0, $metric->false_negatives);
    }

    public function test_category_mapping_between_expert_and_ai_annotations(): void
    {
        $analysisJob = AnalysisJob::factory()->create();
        $textAnalysis = TextAnalysis::factory()->create([
            'job_id' => $analysisJob->job_id,
            'expert_annotations' => [
                ['result' => [
                    // Expert uses simplified category
                    ['type' => 'labels', 'value' => ['start' => 0, 'end' => 50, 'text' => 'expert text', 'labels' => ['simplification']]]
                ]]
            ],
            'claude_annotations' => [
                'annotations' => [
                    // AI uses ATSPARA category that maps to expert category
                    ['type' => 'labels', 'value' => ['start' => 0, 'end' => 50, 'text' => 'expert text', 'labels' => ['causalOversimplification']]]
                ]
            ]
        ]);

        $metric = $this->service->calculateMetricsForText($textAnalysis, 'claude', $analysisJob->job_id);

        // Should match due to category mapping
        $this->assertEquals(1, $metric->true_positives);
        $this->assertEquals(0, $metric->false_positives);
        $this->assertEquals(0, $metric->false_negatives);
    }

    public function test_filters_out_invalid_annotations(): void
    {
        $analysisJob = AnalysisJob::factory()->create();
        $textAnalysis = TextAnalysis::factory()->create([
            'job_id' => $analysisJob->job_id,
            'expert_annotations' => [
                ['result' => [
                    // Invalid annotation with empty text
                    ['type' => 'labels', 'value' => ['start' => 0, 'end' => 0, 'text' => '', 'labels' => []]],
                    // Valid annotation
                    ['type' => 'labels', 'value' => ['start' => 10, 'end' => 20, 'text' => 'valid', 'labels' => ['propaganda']]],
                    // Non-label annotation (should be skipped)
                    ['type' => 'choices', 'value' => ['choices' => ['yes']]]
                ]]
            ],
            'claude_annotations' => [
                'annotations' => [
                    ['type' => 'labels', 'value' => ['start' => 10, 'end' => 20, 'text' => 'valid', 'labels' => ['propaganda']]]
                ]
            ]
        ]);

        $metric = $this->service->calculateMetricsForText($textAnalysis, 'claude', $analysisJob->job_id);

        // Should only count the valid annotation
        $this->assertEquals(1, $metric->true_positives);
        $this->assertEquals(0, $metric->false_positives);
        $this->assertEquals(0, $metric->false_negatives);
    }

    public function test_dynamic_model_detection_in_aggregated_metrics(): void
    {
        $analysisJob = AnalysisJob::factory()->create();
        
        // Create metrics for different models (not hardcoded)
        ComparisonMetric::factory()->create([
            'job_id' => $analysisJob->job_id,
            'model_name' => 'claude-opus-4', // Actual model name
            'precision' => 0.8,
            'recall' => 0.9,
            'f1_score' => 0.85
        ]);
        
        ComparisonMetric::factory()->create([
            'job_id' => $analysisJob->job_id,
            'model_name' => 'gemini-2.5-pro', // Another actual model name
            'precision' => 0.7,
            'recall' => 0.8,
            'f1_score' => 0.75
        ]);

        $results = $this->service->calculateAggregatedMetrics($analysisJob->job_id);

        // Should find both models dynamically
        $this->assertArrayHasKey('claude-opus-4', $results);
        $this->assertArrayHasKey('gemini-2.5-pro', $results);
        $this->assertEquals(0.8, $results['claude-opus-4']['precision']);
        $this->assertEquals(0.7, $results['gemini-2.5-pro']['precision']);
    }

    public function test_handles_real_world_annotation_format(): void
    {
        $analysisJob = AnalysisJob::factory()->create();
        $textAnalysis = TextAnalysis::factory()->create([
            'job_id' => $analysisJob->job_id,
            'expert_annotations' => [
                [
                    'id' => 828,
                    'result' => [
                        ['type' => 'choices', 'value' => ['choices' => ['yes']]],
                        ['type' => 'labels', 'value' => [
                            'start' => 0, 'end' => 100, 
                            'text' => 'Real propaganda text from experts',
                            'labels' => ['emotionalExpression']
                        ]]
                    ]
                ]
            ],
            'claude_annotations' => [
                'primaryChoice' => ['choices' => ['yes']],
                'annotations' => [
                    ['type' => 'labels', 'value' => [
                        'start' => 0, 'end' => 100,
                        'text' => 'Real propaganda text from experts', 
                        'labels' => ['loadedLanguage']
                    ]]
                ]
            ]
        ]);

        $metric = $this->service->calculateMetricsForText($textAnalysis, 'claude-opus-4', $analysisJob->job_id);

        // Should match due to emotionalExpression -> loadedLanguage mapping
        $this->assertEquals(1, $metric->true_positives);
        $this->assertEquals(0, $metric->false_positives);
        $this->assertEquals(0, $metric->false_negatives);
    }
}