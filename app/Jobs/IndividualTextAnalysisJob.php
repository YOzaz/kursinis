<?php

namespace App\Jobs;

use App\Models\AnalysisJob;
use App\Models\TextAnalysis;
use App\Services\MetricsService;
use App\Services\ClaudeService;
use App\Services\OpenAIService;
use App\Services\GeminiService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

/**
 * Individual text analysis job - processes one text per job instance.
 * 
 * This replaces the chunking approach with individual text processing
 * for better reliability and simpler error handling.
 */
class IndividualTextAnalysisJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public string $jobId;
    public string $textId;
    public string $content;
    public array $expertAnnotations;
    public string $modelKey;
    public ?string $customPrompt;

    public int $tries = 3;
    public int $timeout = 600; // 10 minutes per individual text

    public function __construct(
        string $jobId,
        string $textId,
        string $content,
        array $expertAnnotations,
        string $modelKey,
        ?string $customPrompt = null
    ) {
        $this->jobId = $jobId;
        $this->textId = $textId;
        $this->content = $content;
        $this->expertAnnotations = $expertAnnotations;
        $this->modelKey = $modelKey;
        $this->customPrompt = $customPrompt;
        
        $this->onQueue('individual');
    }

    public function handle(): void
    {
        $metricsService = app(MetricsService::class);
        
        try {
            $job = AnalysisJob::where('job_id', $this->jobId)->first();
            
            if (!$job) {
                Log::error('Analysis job not found for individual text processing', [
                    'job_id' => $this->jobId,
                    'text_id' => $this->textId,
                    'model' => $this->modelKey
                ]);
                return;
            }

            $this->logProgress("Starting individual text analysis", [
                'text_id' => $this->textId,
                'model' => $this->modelKey,
                'content_length' => strlen($this->content),
                'status' => 'started'
            ]);

            // Find or create TextAnalysis record
            $textAnalysis = TextAnalysis::where('job_id', $this->jobId)
                ->where('text_id', $this->textId)
                ->first();

            if (!$textAnalysis) {
                $textAnalysis = TextAnalysis::create([
                    'job_id' => $this->jobId,
                    'text_id' => $this->textId,
                    'content' => $this->content,
                    'expert_annotations' => $this->expertAnnotations,
                ]);
            }

            // Process text with the specific model
            $result = $this->processTextWithModel($this->modelKey, $this->content, $this->customPrompt);

            // Save results
            $this->saveModelResults($textAnalysis, $this->modelKey, $result);
            
            // Create comparison metrics if expert annotations exist
            if (!empty($this->expertAnnotations) && !isset($result['error'])) {
                $this->createComparisonMetrics(
                    $textAnalysis, 
                    $this->modelKey, 
                    $result, 
                    $metricsService
                );
            }

            // Update job progress
            $this->updateJobProgress($job);

            $this->logProgress("Individual text analysis completed successfully", [
                'text_id' => $this->textId,
                'model' => $this->modelKey,
                'has_annotations' => !empty($result['annotations'] ?? []),
                'status' => 'completed'
            ]);

        } catch (\Exception $e) {
            $this->logProgress("Individual text analysis failed", [
                'text_id' => $this->textId,
                'model' => $this->modelKey,
                'error' => $e->getMessage(),
                'status' => 'failed'
            ], 'error');
            
            Log::error("❌ Individual text analysis failed", [
                'job_id' => $this->jobId,
                'text_id' => $this->textId,
                'model' => $this->modelKey,
                'error' => $e->getMessage(),
                'error_type' => get_class($e),
                'stack_trace' => $e->getTraceAsString()
            ]);
            
            // Mark text analysis as failed for this model
            $textAnalysis = TextAnalysis::where('job_id', $this->jobId)
                ->where('text_id', $this->textId)
                ->first();
                
            if ($textAnalysis) {
                $this->markModelAsFailed($textAnalysis, $this->modelKey, $e->getMessage());
            }
            
            // Update job progress even for failed texts
            $job = AnalysisJob::where('job_id', $this->jobId)->first();
            if ($job) {
                $this->updateJobProgress($job);
            }

            // Only rethrow if it's a critical error that should fail the job
            if ($e instanceof \App\Services\Exceptions\LLMException) {
                if ($e->shouldFailBatch()) {
                    throw $e;
                } else {
                    // Log graceful failure for quota/rate limit errors
                    $this->logProgress("Individual text analysis failed gracefully (quota/rate limit)", [
                        'text_id' => $this->textId,
                        'model' => $this->modelKey,
                        'error_type' => $e->getErrorType(),
                        'is_quota_related' => $e->isQuotaRelated(),
                        'is_retryable' => $e->isRetryable(),
                        'status' => 'graceful_failure'
                    ], 'warning');
                    return; // Exit gracefully without rethrowing
                }
            } else {
                // For non-LLM exceptions, still throw to maintain retry behavior
                throw $e;
            }
        }
    }

    /**
     * Process a single text with the specified model.
     */
    private function processTextWithModel(string $modelKey, string $content, ?string $customPrompt = null): array
    {
        $allModels = config('llm.models');
        $modelConfig = $allModels[$modelKey] ?? null;
        
        if (!$modelConfig) {
            throw new \Exception("Model {$modelKey} not found in configuration");
        }
        
        $provider = $modelConfig['provider'];
        $modelName = $modelConfig['model'];
        
        $this->logProgress("Processing text with model", [
            'text_id' => $this->textId,
            'model' => $modelKey,
            'provider' => $provider,
            'model_name' => $modelName,
            'content_length' => strlen($content),
            'status' => 'processing'
        ]);
        
        switch ($provider) {
            case 'anthropic':
                $service = app(ClaudeService::class);
                $service->setModel($modelKey);
                return $service->analyzeText($content, $customPrompt);
                
            case 'openai':
                $service = app(OpenAIService::class);
                $service->setModel($modelKey);
                return $service->analyzeText($content, $customPrompt);
                
            case 'google':
                $service = app(GeminiService::class);
                $service->setModel($modelKey);
                return $service->analyzeText($content, $customPrompt);
                
            default:
                throw new \Exception("Unsupported provider: {$provider}");
        }
    }

    /**
     * Save model results to the appropriate field in TextAnalysis.
     */
    private function saveModelResults(TextAnalysis $textAnalysis, string $modelKey, array $result): void
    {
        $this->logProgress("Saving model results", [
            'model' => $modelKey,
            'text_id' => $textAnalysis->text_id,
            'has_annotations' => !empty($result['annotations'] ?? []),
            'has_error' => !empty($result['error'] ?? ''),
            'status' => 'saving'
        ]);
        
        $allModels = config('llm.models');
        $modelConfig = $allModels[$modelKey] ?? null;
        $provider = $modelConfig['provider'] ?? 'unknown';
        
        $cleanResult = $result;
        unset($cleanResult['error']);
        
        $modelName = $modelConfig['model'] ?? $modelKey;
        $errorMessage = $result['error'] ?? null;
        
        try {
            // Store in new ModelResult table for progress tracking
            $modelResult = $textAnalysis->storeModelResult(
                $modelKey, 
                $cleanResult, 
                $modelName, 
                null, // execution time - could be added later
                $errorMessage
            );
            
            $this->logProgress("ModelResult created successfully", [
                'model' => $modelKey,
                'text_id' => $textAnalysis->text_id,
                'model_result_id' => $modelResult->id,
                'status' => 'model_result_saved'
            ]);
        } catch (\Exception $e) {
            $this->logProgress("Failed to create ModelResult", [
                'model' => $modelKey,
                'text_id' => $textAnalysis->text_id,
                'error' => $e->getMessage(),
                'status' => 'error'
            ], 'error');
            throw $e;
        }
        
        // Also store in legacy TextAnalysis columns for backward compatibility
        switch ($provider) {
            case 'anthropic':
                $textAnalysis->claude_annotations = $cleanResult;
                $textAnalysis->claude_model_name = $modelName;
                if ($errorMessage) {
                    $textAnalysis->claude_error = $errorMessage;
                }
                break;
                
            case 'openai':
                $textAnalysis->gpt_annotations = $cleanResult;
                $textAnalysis->gpt_model_name = $modelName;
                if ($errorMessage) {
                    $textAnalysis->gpt_error = $errorMessage;
                }
                break;
                
            case 'google':
                $textAnalysis->gemini_annotations = $cleanResult;
                $textAnalysis->gemini_model_name = $modelName;
                if ($errorMessage) {
                    $textAnalysis->gemini_error = $errorMessage;
                }
                break;
        }
        
        $textAnalysis->save();
    }

    /**
     * Mark a model as failed for the text analysis.
     */
    private function markModelAsFailed(TextAnalysis $textAnalysis, string $modelKey, string $error): void
    {
        // Use new structure to store the failure
        $textAnalysis->storeModelResult($modelKey, [], null, null, $error);
        
        // Also update legacy fields for backward compatibility
        $allModels = config('llm.models');
        $modelConfig = $allModels[$modelKey] ?? null;
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

            $allModels = config('llm.models');
            $modelConfig = $allModels[$modelKey] ?? null;
            $modelName = $modelConfig['model'] ?? $modelKey;

            // Set the model annotations for calculation
            $textAnalysis->setModelAnnotations($modelKey, $result, $modelName);
            $textAnalysis->save();

            // Calculate and save comparison metrics
            $metricsService->calculateMetricsForText(
                $textAnalysis,
                $modelKey,
                $this->jobId,
                $modelName
            );

        } catch (\Exception $e) {
            $this->logProgress('Failed to create comparison metrics', [
                'text_analysis_id' => $textAnalysis->id,
                'model' => $modelKey,
                'error' => $e->getMessage()
            ], 'error');
        }
    }

    /**
     * Update job progress when a text completes.
     */
    private function updateJobProgress(AnalysisJob $job): void
    {
        $totalTexts = count($job->requested_models ?? []) * TextAnalysis::where('job_id', $this->jobId)->count();
        
        // Count completed individual text-model combinations
        $completedCount = \App\Models\ModelResult::where('job_id', $this->jobId)
            ->whereIn('status', ['completed', 'failed'])
            ->count();
        
        // Update job progress based on individual text completion
        $job->processed_texts = $completedCount;
        $job->total_texts = $totalTexts;
        
        // Check if all text-model combinations are completed
        if ($completedCount >= $totalTexts) {
            $job->status = AnalysisJob::STATUS_COMPLETED;
        }
        
        $job->save();
        
        $this->logProgress("Job progress updated", [
            'completed_count' => $completedCount,
            'total_texts' => $totalTexts,
            'progress_percentage' => $totalTexts > 0 ? round(($completedCount / $totalTexts) * 100, 1) : 0,
            'job_status' => $job->status
        ]);
    }

    /**
     * Enhanced logging with status information.
     */
    private function logProgress(string $message, array $context = [], string $level = 'info'): void
    {
        $context['timestamp'] = now()->toISOString();
        $context['job_type'] = 'IndividualTextAnalysisJob';
        $context['job_id'] = $this->jobId;
        $context['text_id'] = $this->textId;
        $context['model'] = $this->modelKey;
        
        Log::{$level}($message, $context);
    }

    public function failed(\Throwable $exception): void
    {
        $this->logProgress('Individual text analysis job failed permanently', [
            'error' => $exception->getMessage(),
            'trace' => $exception->getTraceAsString()
        ], 'error');

        // Update job progress even for failed texts
        $job = AnalysisJob::where('job_id', $this->jobId)->first();
        if ($job) {
            $this->updateJobProgress($job);
        }
    }
}