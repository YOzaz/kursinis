<?php

namespace Tests\Unit\Unit\Services;

use App\Services\MetricsService;
use App\Models\AnalysisJob;
use App\Models\TextAnalysis;
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

    public function test_calculates_basic_metrics(): void
    {
        $expertAnnotations = [
            [
                'type' => 'labels',
                'value' => [
                    'start' => 0,
                    'end' => 50,
                    'text' => 'Test propaganda text',
                    'labels' => ['simplification']
                ]
            ]
        ];

        $llmAnnotations = [
            [
                'type' => 'labels',
                'value' => [
                    'start' => 0,
                    'end' => 50,
                    'text' => 'Test propaganda text',
                    'labels' => ['simplification']
                ]
            ]
        ];

        $metrics = $this->service->calculateMetrics($expertAnnotations, $llmAnnotations, 'claude-4');

        $this->assertIsArray($metrics);
        $this->assertArrayHasKey('precision', $metrics);
        $this->assertArrayHasKey('recall', $metrics);
        $this->assertArrayHasKey('f1_score', $metrics);
        $this->assertArrayHasKey('position_accuracy', $metrics);
        
        $this->assertEquals(1.0, $metrics['precision']);
        $this->assertEquals(1.0, $metrics['recall']);
        $this->assertEquals(1.0, $metrics['f1_score']);
        $this->assertEquals(1.0, $metrics['position_accuracy']);
    }

    public function test_calculates_partial_match_metrics(): void
    {
        $expertAnnotations = [
            [
                'type' => 'labels',
                'value' => [
                    'start' => 0,
                    'end' => 50,
                    'text' => 'Test propaganda text',
                    'labels' => ['simplification', 'doubt']
                ]
            ]
        ];

        $llmAnnotations = [
            [
                'type' => 'labels',
                'value' => [
                    'start' => 0,
                    'end' => 50,
                    'text' => 'Test propaganda text',
                    'labels' => ['simplification'] // Missing 'doubt'
                ]
            ]
        ];

        $metrics = $this->service->calculateMetrics($expertAnnotations, $llmAnnotations, 'claude-4');

        $this->assertEquals(1.0, $metrics['precision']); // Found 1 correct out of 1 predicted
        $this->assertEquals(0.5, $metrics['recall']); // Found 1 correct out of 2 actual
        $this->assertEquals(2/3, $metrics['f1_score'], '', 0.01); // Harmonic mean
    }

    public function test_calculates_position_accuracy_with_tolerance(): void
    {
        $expertAnnotations = [
            [
                'type' => 'labels',
                'value' => [
                    'start' => 0,
                    'end' => 50,
                    'text' => 'Test propaganda text',
                    'labels' => ['simplification']
                ]
            ]
        ];

        $llmAnnotations = [
            [
                'type' => 'labels',
                'value' => [
                    'start' => 5, // Slightly off but within tolerance
                    'end' => 45,
                    'text' => 'Test propaganda text',
                    'labels' => ['simplification']
                ]
            ]
        ];

        $metrics = $this->service->calculateMetrics($expertAnnotations, $llmAnnotations, 'claude-4');

        $this->assertGreaterThan(0.0, $metrics['position_accuracy']);
    }

    public function test_handles_empty_annotations(): void
    {
        $expertAnnotations = [];
        $llmAnnotations = [];

        $metrics = $this->service->calculateMetrics($expertAnnotations, $llmAnnotations, 'claude-4');

        $this->assertEquals(0.0, $metrics['precision']);
        $this->assertEquals(0.0, $metrics['recall']);
        $this->assertEquals(0.0, $metrics['f1_score']);
        $this->assertEquals(0.0, $metrics['position_accuracy']);
    }

    public function test_handles_false_positives(): void
    {
        $expertAnnotations = []; // No expert annotations

        $llmAnnotations = [
            [
                'type' => 'labels',
                'value' => [
                    'start' => 0,
                    'end' => 50,
                    'text' => 'Test propaganda text',
                    'labels' => ['simplification']
                ]
            ]
        ];

        $metrics = $this->service->calculateMetrics($expertAnnotations, $llmAnnotations, 'claude-4');

        $this->assertEquals(0.0, $metrics['precision']); // All predictions are false positives
        $this->assertEquals(0.0, $metrics['recall']); // No true positives to recall
    }

    public function test_handles_false_negatives(): void
    {
        $expertAnnotations = [
            [
                'type' => 'labels',
                'value' => [
                    'start' => 0,
                    'end' => 50,
                    'text' => 'Test propaganda text',
                    'labels' => ['simplification']
                ]
            ]
        ];

        $llmAnnotations = []; // LLM missed the annotation

        $metrics = $this->service->calculateMetrics($expertAnnotations, $llmAnnotations, 'claude-4');

        $this->assertEquals(0.0, $metrics['precision']); // No predictions made
        $this->assertEquals(0.0, $metrics['recall']); // Missed all expert annotations
        $this->assertEquals(0.0, $metrics['f1_score']);
    }

    public function test_calculates_cohens_kappa(): void
    {
        $expertAnnotations = [
            [
                'type' => 'labels',
                'value' => [
                    'start' => 0,
                    'end' => 50,
                    'text' => 'Test propaganda text',
                    'labels' => ['simplification']
                ]
            ]
        ];

        $llmAnnotations = [
            [
                'type' => 'labels',
                'value' => [
                    'start' => 0,
                    'end' => 50,
                    'text' => 'Test propaganda text',
                    'labels' => ['simplification']
                ]
            ]
        ];

        $metrics = $this->service->calculateMetrics($expertAnnotations, $llmAnnotations, 'claude-4');

        $this->assertArrayHasKey('cohens_kappa', $metrics);
        $this->assertIsFloat($metrics['cohens_kappa']);
        $this->assertGreaterThanOrEqual(-1.0, $metrics['cohens_kappa']);
        $this->assertLessThanOrEqual(1.0, $metrics['cohens_kappa']);
    }

    public function test_supports_different_technique_types(): void
    {
        $techniques = ['simplification', 'emotionalExpression', 'uncertainty', 'doubt', 'wavingTheFlag', 'reductioAdHitlerum', 'repetition'];

        foreach ($techniques as $technique) {
            $expertAnnotations = [
                [
                    'type' => 'labels',
                    'value' => [
                        'start' => 0,
                        'end' => 50,
                        'text' => 'Test text',
                        'labels' => [$technique]
                    ]
                ]
            ];

            $llmAnnotations = [
                [
                    'type' => 'labels',
                    'value' => [
                        'start' => 0,
                        'end' => 50,
                        'text' => 'Test text',
                        'labels' => [$technique]
                    ]
                ]
            ];

            $metrics = $this->service->calculateMetrics($expertAnnotations, $llmAnnotations, 'claude-4');

            $this->assertEquals(1.0, $metrics['precision'], "Failed for technique: {$technique}");
            $this->assertEquals(1.0, $metrics['recall'], "Failed for technique: {$technique}");
        }
    }
}