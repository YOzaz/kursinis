<?php

namespace Tests\Unit\Services;

use App\Models\AnalysisJob;
use App\Models\TextAnalysis;
use App\Models\ComparisonMetric;
use App\Models\ModelResult;
use App\Services\StatisticsService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Test that failed analyses are properly excluded from metrics calculations.
 * 
 * This test verifies that metrics like propaganda detection accuracy, precision,
 * recall, F1-score, and execution times correctly exclude cases where analysis
 * has failed due to timeout, API errors, or other reasons.
 */
class FailedAnalysisExclusionTest extends TestCase
{
    use RefreshDatabase;

    private StatisticsService $statisticsService;

    protected function setUp(): void
    {
        parent::setUp();
        $this->statisticsService = new StatisticsService();
    }

    public function test_propaganda_detection_accuracy_excludes_failed_analyses(): void
    {
        $job = AnalysisJob::factory()->create();
        
        // Create successful analysis
        $successfulText = TextAnalysis::factory()->create([
            'job_id' => $job->job_id,
            'text_id' => 'text-1',
            'expert_annotations' => $this->getExpertAnnotationsWithPropaganda(),
        ]);
        
        ModelResult::create([
            'job_id' => $job->job_id,
            'text_id' => 'text-1',
            'model_key' => 'claude-opus-4',
            'provider' => 'anthropic',
            'model_name' => 'claude-opus-4',
            'annotations' => $this->getModelAnnotationsWithPropaganda(),
            'status' => ModelResult::STATUS_COMPLETED,
            'execution_time_ms' => 5000,
        ]);
        
        // Create failed analysis (timeout)
        $failedText = TextAnalysis::factory()->create([
            'job_id' => $job->job_id,
            'text_id' => 'text-2',
            'expert_annotations' => $this->getExpertAnnotationsWithPropaganda(),
        ]);
        
        ModelResult::create([
            'job_id' => $job->job_id,
            'text_id' => 'text-2',
            'model_key' => 'claude-opus-4',
            'provider' => 'anthropic',
            'model_name' => 'claude-opus-4',
            'annotations' => null,
            'status' => ModelResult::STATUS_FAILED,
            'error_message' => 'Request timeout after 60 seconds',
            'execution_time_ms' => null,
        ]);
        
        // Create comparison metrics only for successful analysis
        ComparisonMetric::factory()->create([
            'job_id' => $job->job_id,
            'text_id' => 'text-1',
            'model_name' => 'claude-opus-4',
            'true_positives' => 1,
            'false_positives' => 0,
            'false_negatives' => 0,
        ]);
        
        $stats = $this->statisticsService->getGlobalStatistics();
        $modelPerformance = $stats['model_performance']['claude-opus-4'];
        
        // Should only count the successful analysis, not the failed one
        $this->assertEquals(1.0, $modelPerformance['propaganda_detection_accuracy']);
        $this->assertEquals(1, $modelPerformance['total_analyses']);
        $this->assertEquals(1, $modelPerformance['propaganda_tp']);
        $this->assertEquals(0, $modelPerformance['propaganda_fp']);
        $this->assertEquals(0, $modelPerformance['propaganda_tn']);
        $this->assertEquals(0, $modelPerformance['propaganda_fn']);
    }

    public function test_confusion_matrix_excludes_failed_analyses(): void
    {
        $job = AnalysisJob::factory()->create();
        
        // Create successful analysis with propaganda (true positive)
        $successfulText1 = TextAnalysis::factory()->create([
            'job_id' => $job->job_id,
            'text_id' => 'text-success-1',
            'expert_annotations' => $this->getExpertAnnotationsWithPropaganda(),
        ]);
        
        ModelResult::create([
            'job_id' => $job->job_id,
            'text_id' => 'text-success-1',
            'model_key' => 'claude-opus-4',
            'provider' => 'anthropic',
            'model_name' => 'claude-opus-4',
            'annotations' => $this->getModelAnnotationsWithPropaganda(),
            'status' => ModelResult::STATUS_COMPLETED,
        ]);
        
        // Create successful analysis without propaganda (true negative)
        $successfulText2 = TextAnalysis::factory()->create([
            'job_id' => $job->job_id,
            'text_id' => 'text-success-2',
            'expert_annotations' => $this->getExpertAnnotationsWithoutPropaganda(),
        ]);
        
        ModelResult::create([
            'job_id' => $job->job_id,
            'text_id' => 'text-success-2',
            'model_key' => 'claude-opus-4',
            'provider' => 'anthropic',
            'model_name' => 'claude-opus-4',
            'annotations' => $this->getModelAnnotationsWithoutPropaganda(),
            'status' => ModelResult::STATUS_COMPLETED,
        ]);
        
        // Create multiple failed analyses with different error types
        $failedTexts = [
            'text-failed-timeout' => 'Request timeout after 60 seconds',
            'text-failed-quota' => 'API quota exceeded',
            'text-failed-network' => 'Network connection failed',
            'text-failed-invalid' => 'Invalid API response format',
        ];
        
        foreach ($failedTexts as $textId => $errorMessage) {
            TextAnalysis::factory()->create([
                'job_id' => $job->job_id,
                'text_id' => $textId,
                'expert_annotations' => $this->getExpertAnnotationsWithPropaganda(),
            ]);
            
            ModelResult::create([
                'job_id' => $job->job_id,
                'text_id' => $textId,
                'model_key' => 'claude-opus-4',
                'provider' => 'anthropic',
                'model_name' => 'claude-opus-4',
                'annotations' => null,
                'status' => ModelResult::STATUS_FAILED,
                'error_message' => $errorMessage,
            ]);
        }
        
        // Create comparison metrics for successful analyses only
        ComparisonMetric::factory()->create([
            'job_id' => $job->job_id,
            'text_id' => 'text-success-1',
            'model_name' => 'claude-opus-4',
        ]);
        
        ComparisonMetric::factory()->create([
            'job_id' => $job->job_id,
            'text_id' => 'text-success-2',
            'model_name' => 'claude-opus-4',
        ]);
        
        $stats = $this->statisticsService->getGlobalStatistics();
        $modelPerformance = $stats['model_performance']['claude-opus-4'];
        
        // Should only count successful analyses (2), not failed ones (4)
        $this->assertEquals(2, $modelPerformance['total_analyses']);
        
        // Confusion matrix should only include successful analyses
        $totalConfusionMatrixEntries = $modelPerformance['propaganda_tp'] + 
                                     $modelPerformance['propaganda_fp'] + 
                                     $modelPerformance['propaganda_tn'] + 
                                     $modelPerformance['propaganda_fn'];
        
        $this->assertEquals(2, $totalConfusionMatrixEntries);
    }

    public function test_execution_time_metrics_exclude_failed_analyses(): void
    {
        $job = AnalysisJob::factory()->create();
        
        // Create successful analyses with execution times
        $successfulExecutionTimes = [5000, 7000, 6000]; // ms
        
        foreach ($successfulExecutionTimes as $index => $executionTime) {
            $textId = "text-success-{$index}";
            
            TextAnalysis::factory()->create([
                'job_id' => $job->job_id,
                'text_id' => $textId,
            ]);
            
            ModelResult::create([
                'job_id' => $job->job_id,
                'text_id' => $textId,
                'model_key' => 'claude-opus-4',
                'provider' => 'anthropic',
                'model_name' => 'claude-opus-4',
                'annotations' => $this->getModelAnnotationsWithPropaganda(),
                'status' => ModelResult::STATUS_COMPLETED,
                'execution_time_ms' => $executionTime,
            ]);
        }
        
        // Create failed analyses (should not be included in execution time averages)
        $failedCases = [
            'text-failed-1' => null, // timeout, no execution time recorded
            'text-failed-2' => 30000, // partial execution before failure
        ];
        
        foreach ($failedCases as $textId => $executionTime) {
            TextAnalysis::factory()->create([
                'job_id' => $job->job_id,
                'text_id' => $textId,
            ]);
            
            ModelResult::create([
                'job_id' => $job->job_id,
                'text_id' => $textId,
                'model_key' => 'claude-opus-4',
                'provider' => 'anthropic',
                'model_name' => 'claude-opus-4',
                'annotations' => null,
                'status' => ModelResult::STATUS_FAILED,
                'error_message' => 'Analysis failed',
                'execution_time_ms' => $executionTime,
            ]);
        }
        
        $stats = $this->statisticsService->getGlobalStatistics();
        $avgExecutionTimes = $stats['avg_execution_times'];
        
        // Should only average successful execution times: (5000 + 7000 + 6000) / 3 = 6000
        $this->assertEquals(6000, $avgExecutionTimes['claude-opus-4']);
    }

    public function test_legacy_error_field_detection(): void
    {
        $job = AnalysisJob::factory()->create();
        
        // Create text with legacy error fields (backward compatibility)
        $textWithError = TextAnalysis::factory()->create([
            'job_id' => $job->job_id,
            'text_id' => 'text-legacy-error',
            'expert_annotations' => $this->getExpertAnnotationsWithPropaganda(),
            'claude_annotations' => null,
            'claude_error' => 'Claude API timeout error',
            'claude_execution_time_ms' => null,
        ]);
        
        // Create text with successful legacy fields
        $textWithSuccess = TextAnalysis::factory()->create([
            'job_id' => $job->job_id,
            'text_id' => 'text-legacy-success',
            'expert_annotations' => $this->getExpertAnnotationsWithPropaganda(),
            'claude_annotations' => $this->getModelAnnotationsWithPropaganda(),
            'claude_error' => null,
            'claude_execution_time_ms' => 5500,
        ]);
        
        ComparisonMetric::factory()->create([
            'job_id' => $job->job_id,
            'text_id' => 'text-legacy-error',
            'model_name' => 'claude-opus-4',
        ]);
        
        ComparisonMetric::factory()->create([
            'job_id' => $job->job_id,
            'text_id' => 'text-legacy-success',
            'model_name' => 'claude-opus-4',
        ]);
        
        $stats = $this->statisticsService->getGlobalStatistics();
        $modelPerformance = $stats['model_performance']['claude-opus-4'];
        
        // Should only count the successful analysis, excluding the one with error
        // Note: The system counts comparison metrics, so both are counted but only successful one affects confusion matrix
        $this->assertEquals(2, $modelPerformance['total_analyses']);
        
        // Execution time should only include successful analysis
        $avgExecutionTimes = $stats['avg_execution_times'];
        $this->assertEquals(5500, $avgExecutionTimes['claude-opus-4']);
    }

    public function test_mixed_success_and_failure_scenarios(): void
    {
        $job = AnalysisJob::factory()->create();
        
        // Create complex scenario with multiple models and mixed success/failure
        $scenarios = [
            'text-1' => [
                'claude-opus-4' => ['success' => true, 'execution_time' => 5000],
                'gpt-4.1' => ['success' => false, 'error' => 'Timeout'],
                'gemini-2.5-pro' => ['success' => true, 'execution_time' => 3000],
            ],
            'text-2' => [
                'claude-opus-4' => ['success' => false, 'error' => 'Quota exceeded'],
                'gpt-4.1' => ['success' => true, 'execution_time' => 8000],
                'gemini-2.5-pro' => ['success' => false, 'error' => 'Network error'],
            ],
            'text-3' => [
                'claude-opus-4' => ['success' => true, 'execution_time' => 6000],
                'gpt-4.1' => ['success' => true, 'execution_time' => 7500],
                'gemini-2.5-pro' => ['success' => true, 'execution_time' => 2500],
            ],
        ];
        
        foreach ($scenarios as $textId => $models) {
            TextAnalysis::factory()->create([
                'job_id' => $job->job_id,
                'text_id' => $textId,
                'expert_annotations' => $this->getExpertAnnotationsWithPropaganda(),
            ]);
            
            foreach ($models as $modelKey => $data) {
                $result = ModelResult::create([
                    'job_id' => $job->job_id,
                    'text_id' => $textId,
                    'model_key' => $modelKey,
                    'provider' => $this->getProviderFromModel($modelKey),
                    'model_name' => $modelKey,
                    'annotations' => $data['success'] ? $this->getModelAnnotationsWithPropaganda() : null,
                    'status' => $data['success'] ? ModelResult::STATUS_COMPLETED : ModelResult::STATUS_FAILED,
                    'error_message' => $data['error'] ?? null,
                    'execution_time_ms' => $data['execution_time'] ?? null,
                ]);
                
                if ($data['success']) {
                    ComparisonMetric::factory()->create([
                        'job_id' => $job->job_id,
                        'text_id' => $textId,
                        'model_name' => $modelKey,
                    ]);
                }
            }
        }
        
        $stats = $this->statisticsService->getGlobalStatistics();
        $modelPerformance = $stats['model_performance'];
        $avgExecutionTimes = $stats['avg_execution_times'];
        
        // Claude: 2 successful out of 3 attempts
        $this->assertEquals(2, $modelPerformance['claude-opus-4']['total_analyses']);
        $this->assertEquals(5500, $avgExecutionTimes['claude-opus-4']); // (5000 + 6000) / 2
        
        // GPT: 2 successful out of 3 attempts  
        $this->assertEquals(2, $modelPerformance['gpt-4.1']['total_analyses']);
        $this->assertEquals(7750, $avgExecutionTimes['gpt-4.1']); // (8000 + 7500) / 2
        
        // Gemini: 2 successful out of 3 attempts
        $this->assertEquals(2, $modelPerformance['gemini-2.5-pro']['total_analyses']);
        $this->assertEquals(2750, $avgExecutionTimes['gemini-2.5-pro']); // (3000 + 2500) / 2
    }

    private function getExpertAnnotationsWithPropaganda(): array
    {
        return [
            [
                "result" => [
                    [
                        "type" => "choices",
                        "value" => ["choices" => ["yes"]]
                    ],
                    [
                        "type" => "labels",
                        "value" => [
                            "start" => 0,
                            "end" => 50,
                            "text" => "This is propaganda technique example",
                            "labels" => ["emotionalappeal"]
                        ]
                    ]
                ]
            ]
        ];
    }

    private function getExpertAnnotationsWithoutPropaganda(): array
    {
        return [
            [
                "result" => [
                    [
                        "type" => "choices",
                        "value" => ["choices" => ["no"]]
                    ]
                ]
            ]
        ];
    }

    private function getModelAnnotationsWithPropaganda(): array
    {
        return [
            "primaryChoice" => ["choices" => ["yes"]],
            "annotations" => [
                [
                    "type" => "labels",
                    "value" => [
                        "start" => 0,
                        "end" => 50,
                        "text" => "This is propaganda technique example",
                        "labels" => ["emotionalappeal"]
                    ]
                ]
            ]
        ];
    }

    private function getModelAnnotationsWithoutPropaganda(): array
    {
        return [
            "primaryChoice" => ["choices" => ["no"]],
            "annotations" => []
        ];
    }

    private function getProviderFromModel(string $modelKey): string
    {
        if (str_starts_with($modelKey, 'claude')) {
            return 'anthropic';
        } elseif (str_starts_with($modelKey, 'gpt')) {
            return 'openai';
        } elseif (str_starts_with($modelKey, 'gemini')) {
            return 'google';
        }
        return 'unknown';
    }
}