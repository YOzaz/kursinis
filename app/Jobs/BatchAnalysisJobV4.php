<?php

namespace App\Jobs;

use App\Models\AnalysisJob;
use App\Models\TextAnalysis;
use App\Models\ComparisonMetric;
use App\Services\MetricsService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;

/**
 * File attachment based batch analysis job with parallel processing.
 * 
 * Uses file attachments instead of chunking for maximum efficiency:
 * - Claude: Base64 encoded file attachment
 * - Gemini: File API upload with reference
 * - OpenAI: File attachment or structured chunks
 */
class BatchAnalysisJobV4 implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public string $jobId;
    public array $fileContent;
    public array $models;

    public int $tries = 3;
    public int $timeout = 1800; // 30 minutes for file-based processing

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
        $metricsService = app(MetricsService::class);
        
        try {
            $job = AnalysisJob::where('job_id', $this->jobId)->first();
            
            if (!$job) {
                Log::error('Analysis job not found', ['job_id' => $this->jobId]);
                return;
            }

            $job->status = AnalysisJob::STATUS_PROCESSING;
            $job->save();

            $this->logProgress('Starting file-based batch analysis', [
                'job_id' => $this->jobId,
                'texts_count' => count($this->fileContent),
                'models' => $this->models,
                'strategy' => 'file_attachment'
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

            // Create a temporary JSON file for the batch
            $jsonContent = json_encode($this->fileContent, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
            $tempFile = tempnam(sys_get_temp_dir(), 'batch_analysis_') . '.json';
            file_put_contents($tempFile, $jsonContent);

            $totalTexts = count($this->fileContent);
            $totalModels = count($this->models);

            // Dispatch parallel jobs for each model for true concurrent processing
            $this->logProgress('Starting parallel model processing', [
                'models' => $this->models,
                'total_models' => $totalModels,
                'parallel_strategy' => 'individual_model_jobs'
            ]);

            foreach ($this->models as $modelKey) {
                // Dispatch individual model processing job
                \App\Jobs\ModelAnalysisJob::dispatch(
                    $this->jobId,
                    $modelKey,
                    $tempFile,
                    $this->fileContent,
                    $job->custom_prompt
                )->onQueue('models');
            }

            // Update status to indicate models are being processed
            $job->status = 'processing';
            $job->save();

            $this->logProgress('Parallel model jobs dispatched', [
                'job_id' => $this->jobId,
                'models_dispatched' => count($this->models),
                'processing_type' => 'parallel_file_attachment'
            ]);

            // Keep temporary file for model jobs - they will clean up individually
            // Note: Individual ModelAnalysisJob instances will handle their own cleanup
            
            $this->logProgress('Batch orchestration completed - models processing in parallel', [
                'job_id' => $this->jobId,
                'total_texts' => $totalTexts,
                'models_dispatched' => count($this->models),
                'processing_type' => 'parallel_individual_jobs'
            ]);

        } catch (\Exception $e) {
            $this->logProgress('Batch analysis job failed', [
                'job_id' => $this->jobId,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ], 'error');

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
     * Enhanced logging with status information.
     */
    private function logProgress(string $message, array $context = [], string $level = 'info'): void
    {
        $context['timestamp'] = now()->toISOString();
        $context['job_type'] = 'BatchAnalysisJobV4';
        
        Log::{$level}($message, $context);
    }

    public function failed(\Throwable $exception): void
    {
        $this->logProgress('File-based batch analysis job failed permanently', [
            'job_id' => $this->jobId,
            'error' => $exception->getMessage(),
            'trace' => $exception->getTraceAsString()
        ], 'error');

        $job = AnalysisJob::where('job_id', $this->jobId)->first();
        if ($job) {
            $job->status = AnalysisJob::STATUS_FAILED;
            $job->error_message = $exception->getMessage();
            $job->save();
        }
    }
}