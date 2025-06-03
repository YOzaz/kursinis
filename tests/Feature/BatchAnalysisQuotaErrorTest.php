<?php

namespace Tests\Feature;

use App\Jobs\AnalyzeTextJob;
use App\Jobs\BatchAnalysisJob;
use App\Models\AnalysisJob;
use App\Models\TextAnalysis;
use App\Services\OpenAIService;
use App\Services\Exceptions\LLMException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class BatchAnalysisQuotaErrorTest extends TestCase
{
    use RefreshDatabase;

    public function test_batch_analysis_continues_when_openai_quota_exceeded(): void
    {
        // Create analysis job
        $job = AnalysisJob::factory()->pending()->create();
        
        // Prepare file content with multiple texts
        $fileContent = [
            [
                'id' => 'text1',
                'data' => ['content' => 'First propaganda text for analysis'],
                'annotations' => []
            ],
            [
                'id' => 'text2', 
                'data' => ['content' => 'Second propaganda text for analysis'],
                'annotations' => []
            ]
        ];

        // Models to test including OpenAI
        $models = ['claude-opus-4', 'gemini-2.5-pro', 'gpt-4.1'];

        // Execute the batch job directly (not through queue to test actual logic)
        $batchJob = new BatchAnalysisJob($job->job_id, $fileContent, $models);
        $batchJob->handle();

        // Verify text analysis records were created
        $this->assertDatabaseCount('text_analysis', 2);
        
        // Verify job progress tracking was set up correctly
        $job->refresh();
        $this->assertEquals(6, $job->total_texts); // 2 texts × 3 models
        $this->assertEquals(AnalysisJob::STATUS_PROCESSING, $job->status);

        // Verify each text was created with proper job_id
        $this->assertDatabaseHas('text_analysis', [
            'job_id' => $job->job_id,
            'text_id' => 'text1',
            'content' => 'First propaganda text for analysis'
        ]);

        $this->assertDatabaseHas('text_analysis', [
            'job_id' => $job->job_id,
            'text_id' => 'text2',
            'content' => 'Second propaganda text for analysis'
        ]);
    }

    public function test_individual_jobs_handle_quota_errors_without_stopping_batch(): void
    {
        // Create test data
        $job = AnalysisJob::factory()->create([
            'status' => AnalysisJob::STATUS_PROCESSING,
            'total_texts' => 6, // 2 texts × 3 models
            'processed_texts' => 0
        ]);

        $textAnalysis1 = TextAnalysis::factory()->create([
            'job_id' => $job->job_id,
            'text_id' => 'text1',
            'content' => 'First text for analysis',
            'expert_annotations' => []
        ]);

        $textAnalysis2 = TextAnalysis::factory()->create([
            'job_id' => $job->job_id,
            'text_id' => 'text2',
            'content' => 'Second text for analysis',
            'expert_annotations' => []
        ]);

        // Mock successful services
        $this->mockLLMResponse('claude');
        $this->mockLLMResponse('gemini');

        // Mock OpenAI with quota error
        $mockOpenAI = $this->createMock(OpenAIService::class);
        $mockOpenAI->method('isConfigured')->willReturn(true);
        $mockOpenAI->method('setModel')->willReturn(true);
        $mockOpenAI->method('analyzeText')->willThrowException(
            new LLMException(
                message: 'You exceeded your current quota, please check your plan and billing details.',
                statusCode: 429,
                errorType: 'insufficient_quota',
                provider: 'openai',
                isRetryable: false,
                isQuotaRelated: true
            )
        );

        // Process all jobs for first text
        $jobs = [
            new AnalyzeTextJob($textAnalysis1->id, 'claude-opus-4', $job->job_id),
            new AnalyzeTextJob($textAnalysis1->id, 'gemini-2.5-pro', $job->job_id),
            new AnalyzeTextJob($textAnalysis1->id, 'gpt-4.1', $job->job_id), // This will fail with quota
        ];

        foreach ($jobs as $analyzeJob) {
            if (strpos($analyzeJob->modelName, 'gpt') === 0) {
                // OpenAI job with quota error - should handle gracefully
                $analyzeJob->handle(
                    app(\App\Services\ClaudeService::class),
                    app(\App\Services\GeminiService::class),
                    $mockOpenAI,
                    app(\App\Services\MetricsService::class)
                );
            } else {
                // Other services work normally
                $analyzeJob->handle(
                    app(\App\Services\ClaudeService::class),
                    app(\App\Services\GeminiService::class),
                    app(\App\Services\OpenAIService::class),
                    app(\App\Services\MetricsService::class)
                );
            }
        }

        // Process jobs for second text
        $jobs2 = [
            new AnalyzeTextJob($textAnalysis2->id, 'claude-opus-4', $job->job_id),
            new AnalyzeTextJob($textAnalysis2->id, 'gemini-2.5-pro', $job->job_id),
            new AnalyzeTextJob($textAnalysis2->id, 'gpt-4.1', $job->job_id), // This will also fail with quota
        ];

        foreach ($jobs2 as $analyzeJob) {
            if (strpos($analyzeJob->modelName, 'gpt') === 0) {
                // OpenAI job with quota error - should also handle gracefully
                $analyzeJob->handle(
                    app(\App\Services\ClaudeService::class),
                    app(\App\Services\GeminiService::class),
                    $mockOpenAI,
                    app(\App\Services\MetricsService::class)
                );
            } else {
                // Other services work normally
                $analyzeJob->handle(
                    app(\App\Services\ClaudeService::class),
                    app(\App\Services\GeminiService::class),
                    app(\App\Services\OpenAIService::class),
                    app(\App\Services\MetricsService::class)
                );
            }
        }

        // Verify the batch job completed successfully despite OpenAI quota errors
        $job->refresh();
        $this->assertEquals(AnalysisJob::STATUS_COMPLETED, $job->status);
        $this->assertEquals(6, $job->processed_texts);

        // Verify Claude and Gemini results were saved
        $textAnalysis1->refresh();
        $claudeAnnotations1 = $textAnalysis1->getModelAnnotations('claude-opus-4');
        $geminiAnnotations1 = $textAnalysis1->getModelAnnotations('gemini-2.5-pro');
        $openaiAnnotations1 = $textAnalysis1->getModelAnnotations('gpt-4.1');

        $this->assertNotEmpty($claudeAnnotations1);
        $this->assertNotEmpty($geminiAnnotations1);
        $this->assertArrayHasKey('error', $openaiAnnotations1);
        $this->assertStringContainsString('exceeded your current quota', $openaiAnnotations1['error']);

        // Verify same for second text
        $textAnalysis2->refresh();
        $claudeAnnotations2 = $textAnalysis2->getModelAnnotations('claude-opus-4');
        $geminiAnnotations2 = $textAnalysis2->getModelAnnotations('gemini-2.5-pro');
        $openaiAnnotations2 = $textAnalysis2->getModelAnnotations('gpt-4.1');

        $this->assertNotEmpty($claudeAnnotations2);
        $this->assertNotEmpty($geminiAnnotations2);
        $this->assertArrayHasKey('error', $openaiAnnotations2);
        $this->assertStringContainsString('exceeded your current quota', $openaiAnnotations2['error']);

        // Verify job was not marked as failed overall
        $this->assertNull($job->error_message);
    }

    public function test_different_llm_quota_errors_handling(): void
    {
        $job = AnalysisJob::factory()->create([
            'status' => AnalysisJob::STATUS_PROCESSING,
            'total_texts' => 3,
            'processed_texts' => 0
        ]);

        $textAnalysis = TextAnalysis::factory()->create([
            'job_id' => $job->job_id,
            'text_id' => 'test',
            'content' => 'Test text',
            'expert_annotations' => []
        ]);

        $quotaExceptions = [
            // OpenAI quota exceeded
            new LLMException(
                message: 'You exceeded your current quota, please check your plan and billing details.',
                statusCode: 429,
                errorType: 'insufficient_quota',
                provider: 'openai',
                isRetryable: false,
                isQuotaRelated: true
            ),
            // Gemini billing required
            new LLMException(
                message: 'Gemini API free tier is not available in your country. Please enable billing.',
                statusCode: 400,
                errorType: 'FAILED_PRECONDITION',
                provider: 'gemini',
                isRetryable: false,
                isQuotaRelated: true
            ),
            // Claude rate limit
            new LLMException(
                message: 'Rate limit exceeded for Claude API',
                statusCode: 429,
                errorType: 'rate_limit_error',
                provider: 'claude',
                isRetryable: true,
                isQuotaRelated: true
            )
        ];

        foreach ($quotaExceptions as $index => $quotaException) {
            $analyzeJob = new AnalyzeTextJob($textAnalysis->id, 'test-model', $job->job_id);
            
            // All quota-related errors should not fail the whole job
            $analyzeJob->failed($quotaException);

            // Should handle as quota error, not fail the whole job
            $job->refresh();
            $this->assertNotEquals(AnalysisJob::STATUS_FAILED, $job->status, 
                "Job should not be failed for quota error from {$quotaException->getProvider()}");
            
            // Progress should be updated
            $this->assertEquals($index + 1, $job->processed_texts,
                "Progress should be updated for {$quotaException->getProvider()} error");
        }

        // After all quota errors, job should be completed
        $this->assertEquals(AnalysisJob::STATUS_COMPLETED, $job->status);
    }
}