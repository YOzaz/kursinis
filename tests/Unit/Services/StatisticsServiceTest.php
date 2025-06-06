<?php

namespace Tests\Unit\Services;

use App\Models\AnalysisJob;
use App\Models\TextAnalysis;
use App\Models\ComparisonMetric;
use App\Services\StatisticsService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class StatisticsServiceTest extends TestCase
{
    use RefreshDatabase;

    private StatisticsService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new StatisticsService();
    }

    public function test_calculates_global_statistics(): void
    {
        $statistics = $this->service->getGlobalStatistics();

        $this->assertIsArray($statistics);
        $this->assertArrayHasKey('total_analyses', $statistics);
        $this->assertArrayHasKey('total_texts', $statistics);
        $this->assertArrayHasKey('total_metrics', $statistics);
        $this->assertArrayHasKey('model_performance', $statistics);
        $this->assertArrayHasKey('avg_execution_times', $statistics);
        $this->assertArrayHasKey('top_techniques', $statistics);
        $this->assertArrayHasKey('time_series_data', $statistics);
        
        $this->assertIsInt($statistics['total_analyses']);
        $this->assertIsInt($statistics['total_texts']);
        $this->assertIsInt($statistics['total_metrics']);
        $this->assertIsArray($statistics['model_performance']);
        $this->assertIsArray($statistics['avg_execution_times']);
        $this->assertIsArray($statistics['top_techniques']);
        $this->assertIsArray($statistics['time_series_data']);
    }

    public function test_calculates_job_statistics(): void
    {
        $job = AnalysisJob::factory()->create();
        $textAnalysis = TextAnalysis::factory()->create([
            'job_id' => $job->job_id,
            'expert_annotations' => [
                [
                    'result' => [
                        [
                            'type' => 'choices',
                            'value' => ['choices' => ['yes']]
                        ],
                        [
                            'type' => 'labels',
                            'value' => [
                                'start' => 0,
                                'end' => 10,
                                'text' => 'propaganda',
                                'labels' => ['propaganda']
                            ]
                        ]
                    ]
                ]
            ]
        ]);
        ComparisonMetric::factory()->create([
            'job_id' => $job->job_id,
            'text_id' => $textAnalysis->text_id,
            'precision' => 0.8,
            'recall' => 0.7,
            'f1_score' => 0.75
        ]);

        $statistics = $this->service->calculateJobStatistics($job);

        $this->assertIsArray($statistics);
        $this->assertArrayHasKey('precision', $statistics);
        $this->assertArrayHasKey('recall', $statistics);
        $this->assertArrayHasKey('f1_score', $statistics);
        $this->assertEquals(0.8, $statistics['precision']);
        $this->assertEquals(0.7, $statistics['recall']);
        $this->assertEquals(0.75, $statistics['f1_score']);
    }

    public function test_handles_empty_job_statistics(): void
    {
        $job = AnalysisJob::factory()->create();

        $statistics = $this->service->calculateJobStatistics($job);

        $this->assertEquals(0, $statistics['precision']);
        $this->assertEquals(0, $statistics['recall']);
        $this->assertEquals(0, $statistics['f1_score']);
    }

    public function test_extracts_propaganda_techniques_from_annotations(): void
    {
        // Create text analysis with AI annotations
        $textAnalysis = TextAnalysis::factory()->create([
            'claude_annotations' => [
                'annotations' => [
                    [
                        'type' => 'labels',
                        'value' => [
                            'labels' => ['emotionalExpression', 'uncertainty']
                        ]
                    ]
                ]
            ]
        ]);

        $statistics = $this->service->getGlobalStatistics();

        $this->assertArrayHasKey('top_techniques', $statistics);
        $this->assertIsArray($statistics['top_techniques']);
        
        if (!empty($statistics['top_techniques'])) {
            $this->assertContains('emotionalExpression', array_keys($statistics['top_techniques']));
        }
    }

    public function test_provides_time_series_data(): void
    {
        $statistics = $this->service->getGlobalStatistics();

        $this->assertArrayHasKey('time_series_data', $statistics);
        $this->assertIsArray($statistics['time_series_data']);
        $this->assertCount(30, $statistics['time_series_data']); // 30 days
        
        // Check structure of first item
        if (!empty($statistics['time_series_data'])) {
            $firstItem = $statistics['time_series_data'][0];
            $this->assertArrayHasKey('date', $firstItem);
            $this->assertArrayHasKey('count', $firstItem);
            $this->assertArrayHasKey('label', $firstItem);
        }
    }

    public function test_calculates_average_execution_times_from_text_analysis(): void
    {
        // Create text analyses with execution times
        TextAnalysis::factory()->create([
            'claude_execution_time_ms' => 1500,
            'gpt_execution_time_ms' => 2500,
            'gemini_execution_time_ms' => 3000,
        ]);
        
        TextAnalysis::factory()->create([
            'claude_execution_time_ms' => 2000,
            'gpt_execution_time_ms' => 3000,
            'gemini_execution_time_ms' => 2500,
        ]);

        $statistics = $this->service->getGlobalStatistics();

        $this->assertArrayHasKey('avg_execution_times', $statistics);
        $executionTimes = $statistics['avg_execution_times'];
        
        // Should contain averages for all models
        $this->assertArrayHasKey('claude-opus-4', $executionTimes);
        $this->assertArrayHasKey('claude-sonnet-4', $executionTimes);
        $this->assertArrayHasKey('gpt-4.1', $executionTimes);
        $this->assertArrayHasKey('gpt-4o-latest', $executionTimes);
        $this->assertArrayHasKey('gemini-2.5-pro', $executionTimes);
        $this->assertArrayHasKey('gemini-2.5-flash', $executionTimes);
        
        // Check calculated averages (should be (1500+2000)/2 = 1750 for Claude)
        $this->assertEquals(1750, $executionTimes['claude-opus-4']);
        $this->assertEquals(1750, $executionTimes['claude-sonnet-4']);
        $this->assertEquals(2750, $executionTimes['gpt-4.1']);
        $this->assertEquals(2750, $executionTimes['gpt-4o-latest']);
        $this->assertEquals(2750, $executionTimes['gemini-2.5-pro']);
        $this->assertEquals(2750, $executionTimes['gemini-2.5-flash']);
    }

    public function test_execution_times_fallback_to_model_results_table(): void
    {
        // Create analysis job first to satisfy foreign key constraint
        $job = AnalysisJob::factory()->create();
        
        // Create ModelResult records with execution times
        \App\Models\ModelResult::factory()->create([
            'job_id' => $job->job_id,
            'text_id' => 'test-text-1',
            'model_key' => 'claude-opus-4',
            'execution_time_ms' => 1200,
            'status' => 'completed'
        ]);
        
        \App\Models\ModelResult::factory()->create([
            'job_id' => $job->job_id,
            'text_id' => 'test-text-2',
            'model_key' => 'gpt-4.1',
            'execution_time_ms' => 2400,
            'status' => 'completed'
        ]);

        $statistics = $this->service->getGlobalStatistics();
        $executionTimes = $statistics['avg_execution_times'];

        $this->assertArrayHasKey('claude-opus-4', $executionTimes);
        $this->assertArrayHasKey('gpt-4.1', $executionTimes);
        $this->assertEquals(1200, $executionTimes['claude-opus-4']);
        $this->assertEquals(2400, $executionTimes['gpt-4.1']);
    }

    public function test_execution_times_returns_empty_when_no_data(): void
    {
        $statistics = $this->service->getGlobalStatistics();
        
        $this->assertArrayHasKey('avg_execution_times', $statistics);
        $this->assertIsArray($statistics['avg_execution_times']);
        
        // Should be empty array when no execution time data exists
        $this->assertEmpty($statistics['avg_execution_times']);
    }
}