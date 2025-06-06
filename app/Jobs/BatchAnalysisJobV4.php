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
 * Individual text processing batch analysis job.
 * 
 * Processes each text individually instead of using chunking:
 * - Dispatches IndividualTextAnalysisJob for each text-model combination
 * - Better error isolation and handling
 * - Simpler debugging and monitoring
 */
class BatchAnalysisJobV4 implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public string $jobId;
    public array $fileContent;
    public array $models;

    public int $tries = 3;
    public int $timeout = 300; // 5 minutes for job coordination

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

            $this->logProgress('Starting individual text processing batch analysis', [
                'job_id' => $this->jobId,
                'texts_count' => count($this->fileContent),
                'models' => $this->models,
                'strategy' => 'individual_text_processing'
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

            $totalTexts = count($this->fileContent);
            $totalModels = count($this->models);
            $totalJobs = $totalTexts * $totalModels;

            // Dispatch individual text analysis jobs for each text-model combination
            $this->logProgress('Dispatching individual text analysis jobs', [
                'total_texts' => $totalTexts,
                'total_models' => $totalModels,
                'total_jobs' => $totalJobs,
                'strategy' => 'individual_text_model_jobs'
            ]);

            foreach ($this->fileContent as $item) {
                foreach ($this->models as $modelKey) {
                    // Dispatch individual text analysis job
                    \App\Jobs\IndividualTextAnalysisJob::dispatch(
                        $this->jobId,
                        (string) $item['id'],
                        $item['data']['content'],
                        $item['annotations'] ?? [],
                        $modelKey,
                        $job->custom_prompt
                    )->onQueue('individual');
                }
            }

            // Update status to indicate models are being processed
            $job->status = 'processing';
            $job->save();

            $this->logProgress('Individual text analysis jobs dispatched', [
                'job_id' => $this->jobId,
                'total_jobs_dispatched' => $totalJobs,
                'processing_type' => 'individual_text_processing'
            ]);
            
            $this->logProgress('Batch orchestration completed - texts processing individually', [
                'job_id' => $this->jobId,
                'total_texts' => $totalTexts,
                'total_models' => $totalModels,
                'total_jobs_dispatched' => $totalJobs,
                'processing_type' => 'individual_text_model_combinations'
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
        $this->logProgress('Individual text processing batch analysis job failed permanently', [
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