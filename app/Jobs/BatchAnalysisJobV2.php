<?php

namespace App\Jobs;

use App\Models\AnalysisJob;
use App\Models\TextAnalysis;
use App\Models\ComparisonMetric;
use App\Services\BatchAnalysisService;
use App\Services\MetricsService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

/**
 * Optimized batch analysis job using batch processing.
 * 
 * This version processes multiple texts in single API requests
 * instead of making individual requests for each text.
 */
class BatchAnalysisJobV2 implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public string $jobId;
    public array $fileContent;
    public array $models;

    public int $tries = 3;
    public int $timeout = 3600; // 1 hour for large batches

    public function __construct(string $jobId, array $fileContent, array $models)
    {
        $this->jobId = $jobId;
        $this->fileContent = $fileContent;
        $this->models = $models;
        
        // Set the queue to 'batch' for this job
        $this->onQueue('batch');
    }

    public function handle(): void
    {
        $batchService = app(BatchAnalysisService::class);
        $metricsService = app(MetricsService::class);
        
        try {
            $job = AnalysisJob::where('job_id', $this->jobId)->first();
            
            if (!$job) {
                Log::error('Analysis job not found', ['job_id' => $this->jobId]);
                return;
            }

            $job->status = AnalysisJob::STATUS_PROCESSING;
            $job->save();

            Log::info('Starting optimized batch analysis', [
                'job_id' => $this->jobId,
                'texts_count' => count($this->fileContent),
                'models' => $this->models
            ]);

            // Create TextAnalysis records first
            $textAnalyses = [];
            foreach ($this->fileContent as $item) {
                $textAnalysis = TextAnalysis::create([
                    'job_id' => $this->jobId,
                    'text_id' => (string) $item['id'],
                    'content' => $item['data']['content'],
                    'expert_annotations' => $item['annotations'] ?? [],
                ]);
                
                $textAnalyses[$item['id']] = $textAnalysis;
            }

            $totalModels = count($this->models);
            $completedModels = 0;

            // Process each model with batch requests
            foreach ($this->models as $modelKey) {
                try {
                    Log::info("Processing model with batch analysis", [
                        'model' => $modelKey,
                        'texts_count' => count($this->fileContent)
                    ]);

                    // Prepare texts for batch processing
                    $textsForBatch = array_map(function($item) {
                        return [
                            'id' => $item['id'],
                            'content' => $item['data']['content'],
                            'annotations' => $item['annotations'] ?? []
                        ];
                    }, $this->fileContent);

                    // Process entire dataset in optimized batches
                    $results = $batchService->analyzeBatch(
                        $textsForBatch, 
                        $modelKey, 
                        $job->custom_prompt
                    );

                    // Save results to database
                    foreach ($results as $textId => $result) {
                        if (isset($textAnalyses[$textId])) {
                            $textAnalysis = $textAnalyses[$textId];
                            
                            // Update the text analysis with results
                            $this->saveModelResults($textAnalysis, $modelKey, $result);
                            
                            // Create comparison metrics if expert annotations exist
                            if (!empty($textAnalysis->expert_annotations)) {
                                $this->createComparisonMetrics(
                                    $textAnalysis, 
                                    $modelKey, 
                                    $result, 
                                    $metricsService
                                );
                            }
                        }
                    }

                    $completedModels++;
                    
                    // Update progress
                    $job->processed_texts = $completedModels * count($this->fileContent);
                    $job->save();

                    Log::info("Model batch processing completed", [
                        'model' => $modelKey,
                        'results_count' => count($results),
                        'progress' => "{$completedModels}/{$totalModels} models"
                    ]);

                } catch (\Exception $e) {
                    Log::error("Model batch processing failed", [
                        'model' => $modelKey,
                        'error' => $e->getMessage(),
                        'job_id' => $this->jobId
                    ]);
                    
                    // Mark text analyses as failed for this model
                    foreach ($textAnalyses as $textAnalysis) {
                        $this->markModelAsFailed($textAnalysis, $modelKey, $e->getMessage());
                    }
                }
            }

            // Mark job as completed
            $job->status = AnalysisJob::STATUS_COMPLETED;
            $job->processed_texts = count($this->fileContent) * count($this->models);
            $job->total_texts = count($this->fileContent) * count($this->models);
            $job->save();

            Log::info('Optimized batch analysis completed', [
                'job_id' => $this->jobId,
                'total_texts' => count($this->fileContent),
                'models_processed' => count($this->models)
            ]);

        } catch (\Exception $e) {
            Log::error('Batch analysis job failed', [
                'job_id' => $this->jobId,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            $job = AnalysisJob::where('job_id', $this->jobId)->first();
            if ($job) {
                $job->status = AnalysisJob::STATUS_FAILED;
                $job->error_message = $e->getMessage();
                $job->save();
            }

            throw $e;
        }
    }

    /**
     * Save model results to the appropriate field in TextAnalysis.
     */
    private function saveModelResults(TextAnalysis $textAnalysis, string $modelKey, array $result): void
    {
        $modelConfig = config("llm.models.{$modelKey}");
        $provider = $modelConfig['provider'] ?? 'unknown';
        
        // Remove error field if present (it's not part of the annotations)
        $cleanResult = $result;
        unset($cleanResult['error']);
        unset($cleanResult['text_id']);
        
        $modelName = $modelConfig['model'] ?? $modelKey;
        
        switch ($provider) {
            case 'anthropic':
                $textAnalysis->claude_annotations = $cleanResult;
                $textAnalysis->claude_model_name = $modelName;
                if (isset($result['error'])) {
                    $textAnalysis->claude_error = $result['error'];
                }
                break;
                
            case 'openai':
                $textAnalysis->gpt_annotations = $cleanResult;
                $textAnalysis->gpt_model_name = $modelName;
                if (isset($result['error'])) {
                    $textAnalysis->gpt_error = $result['error'];
                }
                break;
                
            case 'google':
                $textAnalysis->gemini_annotations = $cleanResult;
                $textAnalysis->gemini_model_name = $modelName;
                if (isset($result['error'])) {
                    $textAnalysis->gemini_error = $result['error'];
                }
                break;
        }
        
        $textAnalysis->save();
    }

    /**
     * Mark a model as failed for a text analysis.
     */
    private function markModelAsFailed(TextAnalysis $textAnalysis, string $modelKey, string $error): void
    {
        $modelConfig = config("llm.models.{$modelKey}");
        $provider = $modelConfig['provider'] ?? 'unknown';
        
        switch ($provider) {
            case 'anthropic':
                $textAnalysis->claude_error = $error;
                break;
            case 'openai':
                $textAnalysis->gpt_error = $error;
                break;
            case 'google':
                $textAnalysis->gemini_error = $error;
                break;
        }
        
        $textAnalysis->save();
    }

    /**
     * Create comparison metrics for expert vs AI annotations.
     */
    private function createComparisonMetrics(
        TextAnalysis $textAnalysis, 
        string $modelKey, 
        array $result,
        MetricsService $metricsService
    ): void {
        try {
            if (empty($textAnalysis->expert_annotations) || isset($result['error'])) {
                return;
            }

            $modelConfig = config("llm.models.{$modelKey}");
            $modelName = $modelConfig['model'] ?? $modelKey;

            // Calculate metrics
            $metrics = $metricsService->calculateComparisonMetrics(
                $textAnalysis->expert_annotations,
                $result,
                $textAnalysis->content
            );

            // Save to database
            ComparisonMetric::create([
                'job_id' => $this->jobId,
                'text_analysis_id' => $textAnalysis->id,
                'model_name' => $modelName,
                'precision' => $metrics['precision'],
                'recall' => $metrics['recall'],
                'f1_score' => $metrics['f1_score'],
                'exact_matches' => $metrics['exact_matches'],
                'partial_matches' => $metrics['partial_matches'],
                'false_positives' => $metrics['false_positives'],
                'false_negatives' => $metrics['false_negatives'],
                'total_expert_annotations' => $metrics['total_expert_annotations'],
                'total_ai_annotations' => $metrics['total_ai_annotations'],
                'overlap_threshold' => $metrics['overlap_threshold'],
                'detailed_results' => $metrics['detailed_results'] ?? null,
            ]);

        } catch (\Exception $e) {
            Log::error('Failed to create comparison metrics', [
                'text_analysis_id' => $textAnalysis->id,
                'model' => $modelKey,
                'error' => $e->getMessage()
            ]);
        }
    }

    public function failed(\Throwable $exception): void
    {
        Log::error('Batch analysis job failed permanently', [
            'job_id' => $this->jobId,
            'error' => $exception->getMessage(),
            'trace' => $exception->getTraceAsString()
        ]);

        $job = AnalysisJob::where('job_id', $this->jobId)->first();
        if ($job) {
            $job->status = AnalysisJob::STATUS_FAILED;
            $job->error_message = $exception->getMessage();
            $job->save();
        }
    }
}