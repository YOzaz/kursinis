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

    public function test_region_based_evaluation_prevents_metrics_inflation(): void
    {
        // This test verifies the core improvement: region-based evaluation
        // Scenario: Expert marks 1 region, AI finds 2 overlapping pieces within it
        // Expected: 1 TP (region detected) + 1 FP (over-segmentation penalty)
        
        $analysisJob = AnalysisJob::factory()->create();
        $textAnalysis = TextAnalysis::factory()->create([
            'job_id' => $analysisJob->job_id,
            'expert_annotations' => [
                ['result' => [
                    // Expert marks one large propaganda region
                    ['type' => 'labels', 'value' => [
                        'start' => 100, 
                        'end' => 500, 
                        'text' => 'Expert marked propaganda region', 
                        'labels' => ['simplification']
                    ]]
                ]]
            ],
            'claude_annotations' => [
                'annotations' => [
                    // AI finds two smaller regions within the expert region
                    ['type' => 'labels', 'value' => [
                        'start' => 120, 
                        'end' => 200, 
                        'text' => 'AI region 1', 
                        'labels' => ['simplification']
                    ]],
                    ['type' => 'labels', 'value' => [
                        'start' => 250, 
                        'end' => 350, 
                        'text' => 'AI region 2', 
                        'labels' => ['simplification']
                    ]]
                ]
            ]
        ]);

        $metric = $this->service->calculateMetricsForText($textAnalysis, 'claude', $analysisJob->job_id);

        // Region-based evaluation: 1 expert region matched, 1 AI region excess
        $this->assertEquals(1, $metric->true_positives, 'Should detect the expert region once');
        $this->assertEquals(1, $metric->false_positives, 'Should penalize over-segmentation');
        $this->assertEquals(0, $metric->false_negatives, 'Expert region was detected');
        
        // Verify calculated metrics
        $this->assertEquals(0.5, $metric->precision, 'Precision: 1 valid AI region out of 2 total');
        $this->assertEquals(1.0, $metric->recall, 'Recall: 1 expert region detected out of 1 total');
        $this->assertEqualsWithDelta(0.6667, $metric->f1_score, 0.001, 'F1 should be harmonic mean');
    }

    public function test_region_containment_logic(): void
    {
        // Test that AI regions fully contained within expert regions are valid matches
        
        $analysisJob = AnalysisJob::factory()->create();
        $textAnalysis = TextAnalysis::factory()->create([
            'job_id' => $analysisJob->job_id,
            'expert_annotations' => [
                ['result' => [
                    ['type' => 'labels', 'value' => [
                        'start' => 0, 
                        'end' => 1000, 
                        'text' => 'Very large expert region', 
                        'labels' => ['emotionalexpression']
                    ]]
                ]]
            ],
            'claude_annotations' => [
                'annotations' => [
                    // Small AI region completely within expert region
                    ['type' => 'labels', 'value' => [
                        'start' => 200, 
                        'end' => 300, 
                        'text' => 'Small AI detection', 
                        'labels' => ['emotionalexpression']
                    ]]
                ]
            ]
        ]);

        $metric = $this->service->calculateMetricsForText($textAnalysis, 'claude', $analysisJob->job_id);

        $this->assertEquals(1, $metric->true_positives, 'Contained region should be valid match');
        $this->assertEquals(0, $metric->false_positives, 'No false detections');
        $this->assertEquals(0, $metric->false_negatives, 'Expert region was detected');
    }

    public function test_no_double_counting_in_region_matching(): void
    {
        // Test that each expert region can only match with one AI region
        
        $analysisJob = AnalysisJob::factory()->create();
        $textAnalysis = TextAnalysis::factory()->create([
            'job_id' => $analysisJob->job_id,
            'expert_annotations' => [
                ['result' => [
                    ['type' => 'labels', 'value' => [
                        'start' => 100, 
                        'end' => 200, 
                        'text' => 'Expert region 1', 
                        'labels' => ['doubt']
                    ]],
                    ['type' => 'labels', 'value' => [
                        'start' => 300, 
                        'end' => 400, 
                        'text' => 'Expert region 2', 
                        'labels' => ['doubt']
                    ]]
                ]]
            ],
            'claude_annotations' => [
                'annotations' => [
                    // AI finds regions that match both expert regions
                    ['type' => 'labels', 'value' => [
                        'start' => 110, 
                        'end' => 190, 
                        'text' => 'AI matches expert 1', 
                        'labels' => ['doubt']
                    ]],
                    ['type' => 'labels', 'value' => [
                        'start' => 310, 
                        'end' => 390, 
                        'text' => 'AI matches expert 2', 
                        'labels' => ['doubt']
                    ]]
                ]
            ]
        ]);

        $metric = $this->service->calculateMetricsForText($textAnalysis, 'claude', $analysisJob->job_id);

        $this->assertEquals(2, $metric->true_positives, 'Both expert regions matched');
        $this->assertEquals(0, $metric->false_positives, 'Perfect matching');
        $this->assertEquals(0, $metric->false_negatives, 'All expert regions detected');
        
        $this->assertEquals(1.0, $metric->precision, 'Perfect precision');
        $this->assertEquals(1.0, $metric->recall, 'Perfect recall');
        $this->assertEquals(1.0, $metric->f1_score, 'Perfect F1');
    }

    public function test_coverage_based_recall_with_spanning_ai_region(): void
    {
        // Test the improved coverage-based recall logic
        // Scenario: AI region spans multiple expert regions
        // Expected: All expert regions should count as detected for recall
        
        $analysisJob = AnalysisJob::factory()->create();
        $textAnalysis = TextAnalysis::factory()->create([
            'job_id' => $analysisJob->job_id,
            'expert_annotations' => [
                ['result' => [
                    // Two separate expert regions
                    ['type' => 'labels', 'value' => [
                        'start' => 100, 
                        'end' => 200, 
                        'text' => 'Expert region 1', 
                        'labels' => ['emotionalexpression']
                    ]],
                    ['type' => 'labels', 'value' => [
                        'start' => 300, 
                        'end' => 400, 
                        'text' => 'Expert region 2', 
                        'labels' => ['emotionalexpression']
                    ]]
                ]]
            ],
            'claude_annotations' => [
                'annotations' => [
                    // AI finds one large region that spans both expert regions
                    ['type' => 'labels', 'value' => [
                        'start' => 150, 
                        'end' => 350, 
                        'text' => 'Large AI region spanning both expert regions', 
                        'labels' => ['emotionalexpression']
                    ]]
                ]
            ]
        ]);

        $metric = $this->service->calculateMetricsForText($textAnalysis, 'claude', $analysisJob->job_id);

        // With coverage-based recall: Both expert regions have coverage
        $this->assertEquals(1, $metric->true_positives, 'One AI region can only match with one expert region (precision)');
        $this->assertEquals(0, $metric->false_positives, 'No excess AI regions');
        $this->assertEquals(0, $metric->false_negatives, 'Both expert regions have coverage (coverage-based recall)');
        
        // Metrics should reflect coverage effectiveness
        $this->assertEquals(1.0, $metric->precision, 'Precision: 1 valid AI region out of 1 total');
        $this->assertEquals(1.0, $metric->recall, 'Recall: Both expert regions have AI coverage');
        $this->assertEquals(1.0, $metric->f1_score, 'Perfect F1 due to full coverage');
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