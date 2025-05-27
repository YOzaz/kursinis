<?php

namespace Tests\Unit\Unit\Services;

use App\Models\AnalysisJob;
use App\Models\Experiment;
use App\Models\ExperimentResult;
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

    public function test_returns_empty_statistics_for_experiment_without_results(): void
    {
        $experiment = Experiment::factory()->create();

        $statistics = $this->service->getExperimentStatistics($experiment);

        $this->assertIsArray($statistics);
        $this->assertArrayHasKey('models', $statistics);
        $this->assertArrayHasKey('metrics', $statistics);
        $this->assertArrayHasKey('comparison', $statistics);
        $this->assertArrayHasKey('charts', $statistics);
        $this->assertEmpty($statistics['models']);
    }

    public function test_calculates_model_statistics(): void
    {
        $experiment = Experiment::factory()->create();
        $analysisJob = AnalysisJob::factory()->forExperiment($experiment)->create();
        
        // Create results with known metrics
        ExperimentResult::factory()->create([
            'experiment_id' => $experiment->id,
            'analysis_job_id' => $analysisJob->job_id,
            'llm_model' => 'claude-4',
            'metrics' => [
                'precision' => 0.8,
                'recall' => 0.7,
                'f1_score' => 0.75,
                'cohens_kappa' => 0.6,
            ],
            'execution_time' => 2.5,
        ]);
        
        ExperimentResult::factory()->forExperiment($experiment)->create([
            'llm_model' => 'claude-4',
            'metrics' => [
                'precision' => 0.9,
                'recall' => 0.8,
                'f1_score' => 0.85,
                'cohens_kappa' => 0.7,
            ],
            'execution_time' => 3.0,
        ]);

        $statistics = $this->service->getExperimentStatistics($experiment);

        $this->assertArrayHasKey('claude-4', $statistics['models']);
        $modelStats = $statistics['models']['claude-4'];
        
        $this->assertEquals(2, $modelStats['total_analyses']);
        $this->assertEquals(2.75, $modelStats['avg_execution_time']); // (2.5 + 3.0) / 2
        $this->assertEquals(0.85, $modelStats['avg_precision']); // (0.8 + 0.9) / 2
        $this->assertEquals(0.75, $modelStats['avg_recall']); // (0.7 + 0.8) / 2
        $this->assertEquals(0.8, $modelStats['avg_f1']); // (0.75 + 0.85) / 2
        $this->assertEquals(0.65, $modelStats['avg_kappa']); // (0.6 + 0.7) / 2
    }

    public function test_calculates_metrics_comparison_with_standard_deviation(): void
    {
        $experiment = Experiment::factory()->create();
        
        ExperimentResult::factory()->forExperiment($experiment)->create([
            'llm_model' => 'claude-4',
            'metrics' => [
                'precision' => 0.8,
                'recall' => 0.7,
                'f1_score' => 0.75,
                'cohens_kappa' => 0.6,
            ],
        ]);
        
        ExperimentResult::factory()->forExperiment($experiment)->create([
            'llm_model' => 'claude-4',
            'metrics' => [
                'precision' => 0.6,
                'recall' => 0.5,
                'f1_score' => 0.55,
                'cohens_kappa' => 0.4,
            ],
        ]);

        $statistics = $this->service->getExperimentStatistics($experiment);

        $this->assertArrayHasKey('claude-4', $statistics['comparison']);
        $comparison = $statistics['comparison']['claude-4'];
        
        $this->assertArrayHasKey('precision', $comparison);
        $this->assertArrayHasKey('avg', $comparison['precision']);
        $this->assertArrayHasKey('std', $comparison['precision']);
        $this->assertArrayHasKey('values', $comparison['precision']);
        
        $this->assertEquals(0.7, $comparison['precision']['avg']); // (0.8 + 0.6) / 2
        $this->assertGreaterThan(0, $comparison['precision']['std']); // Should have variation
    }

    public function test_generates_charts_data(): void
    {
        $experiment = Experiment::factory()->create();
        
        ExperimentResult::factory()->forExperiment($experiment)->claude()->create([
            'metrics' => [
                'precision' => 0.8,
                'recall' => 0.7,
                'f1_score' => 0.75,
                'cohens_kappa' => 0.6,
            ],
            'execution_time' => 2.0,
        ]);
        
        ExperimentResult::factory()->forExperiment($experiment)->gemini()->create([
            'metrics' => [
                'precision' => 0.75,
                'recall' => 0.65,
                'f1_score' => 0.7,
                'cohens_kappa' => 0.55,
            ],
            'execution_time' => 1.5,
        ]);

        $statistics = $this->service->getExperimentStatistics($experiment);

        $this->assertArrayHasKey('charts', $statistics);
        $charts = $statistics['charts'];
        
        $this->assertArrayHasKey('metrics_comparison', $charts);
        $this->assertArrayHasKey('execution_time', $charts);
        $this->assertArrayHasKey('score_distribution', $charts);
        $this->assertArrayHasKey('model_accuracy', $charts);
        
        // Check metrics comparison chart data
        $metricsChart = $charts['metrics_comparison'];
        $this->assertCount(2, $metricsChart); // Two models
        $this->assertArrayHasKey('model', $metricsChart[0]);
        $this->assertArrayHasKey('precision', $metricsChart[0]);
        $this->assertArrayHasKey('recall', $metricsChart[0]);
        $this->assertArrayHasKey('f1_score', $metricsChart[0]);
    }

    public function test_calculates_global_statistics(): void
    {
        // Create multiple experiments with results
        $experiment1 = Experiment::factory()->create();
        $experiment2 = Experiment::factory()->create();
        
        ExperimentResult::factory()->forExperiment($experiment1)->claude()->count(2)->create();
        ExperimentResult::factory()->forExperiment($experiment2)->gemini()->count(3)->create();

        $globalStats = $this->service->getGlobalStatistics();

        $this->assertArrayHasKey('total_experiments', $globalStats);
        $this->assertArrayHasKey('total_analyses', $globalStats);
        $this->assertArrayHasKey('model_performance', $globalStats);
        $this->assertArrayHasKey('recent_activity', $globalStats);
        
        $this->assertEquals(2, $globalStats['total_experiments']);
        $this->assertEquals(5, $globalStats['total_analyses']); // 2 + 3
    }

    public function test_handles_missing_metrics_gracefully(): void
    {
        $experiment = Experiment::factory()->create();
        
        ExperimentResult::factory()->forExperiment($experiment)->create([
            'llm_model' => 'claude-4',
            'metrics' => [], // Empty metrics
            'execution_time' => 2.0,
        ]);

        $statistics = $this->service->getExperimentStatistics($experiment);

        $this->assertArrayHasKey('claude-4', $statistics['models']);
        $modelStats = $statistics['models']['claude-4'];
        
        $this->assertEquals(1, $modelStats['total_analyses']);
        $this->assertEquals(2.0, $modelStats['avg_execution_time']);
        $this->assertEquals(0, $modelStats['avg_precision']); // Should default to 0
        $this->assertEquals(0, $modelStats['avg_recall']);
        $this->assertEquals(0, $modelStats['avg_f1']);
        $this->assertEquals(0, $modelStats['avg_kappa']);
    }

    public function test_calculates_reliability_score(): void
    {
        $experiment = Experiment::factory()->create();
        
        // Create consistent results (high reliability)
        ExperimentResult::factory()->forExperiment($experiment)->count(3)->create([
            'llm_model' => 'claude-4',
            'metrics' => [
                'f1_score' => 0.85, // Consistent F1 scores
            ],
        ]);
        
        // Create inconsistent results (low reliability)
        ExperimentResult::factory()->forExperiment($experiment)->create([
            'llm_model' => 'gemini-2.5-pro',
            'metrics' => ['f1_score' => 0.9],
        ]);
        ExperimentResult::factory()->forExperiment($experiment)->create([
            'llm_model' => 'gemini-2.5-pro',
            'metrics' => ['f1_score' => 0.3],
        ]);

        $globalStats = $this->service->getGlobalStatistics();
        $performance = $globalStats['model_performance'];

        $this->assertArrayHasKey('claude-4', $performance);
        $this->assertArrayHasKey('gemini-2.5-pro', $performance);
        $this->assertArrayHasKey('reliability_score', $performance['claude-4']);
        $this->assertArrayHasKey('reliability_score', $performance['gemini-2.5-pro']);
        
        // Claude should have higher reliability due to consistent scores
        $this->assertGreaterThan(
            $performance['gemini-2.5-pro']['reliability_score'],
            $performance['claude-4']['reliability_score']
        );
    }
}
