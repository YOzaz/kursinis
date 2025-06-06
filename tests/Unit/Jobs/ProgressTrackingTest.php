<?php

namespace Tests\Unit\Jobs;

use Tests\TestCase;
use App\Jobs\BatchAnalysisJobV4;
use App\Jobs\IndividualTextAnalysisJob;
use App\Models\AnalysisJob;
use App\Models\TextAnalysis;
use App\Models\ModelResult;
use App\Services\ClaudeService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Str;
use Mockery;

class ProgressTrackingTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        
        // Set up test configuration
        config([
            'llm.models' => [
                'claude-3-sonnet' => [
                    'provider' => 'anthropic',
                    'model' => 'claude-3-sonnet-20240229',
                    'api_key' => 'test-key',
                    'base_url' => 'https://api.anthropic.com/v1/',
                    'max_tokens' => 4000,
                    'temperature' => 0.1,
                ],
                'gpt-4o' => [
                    'provider' => 'openai',
                    'model' => 'gpt-4o',
                    'api_key' => 'test-key',
                    'base_url' => 'https://api.openai.com/v1',
                    'max_tokens' => 4000,
                    'temperature' => 0.1,
                ]
            ]
        ]);
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    public function test_total_texts_calculation_with_individual_processing()
    {
        $jobId = 'test-job-' . Str::uuid();
        $fileContent = [
            [
                'id' => 'text-1',
                'data' => ['content' => 'First test text'],
                'annotations' => []
            ],
            [
                'id' => 'text-2',
                'data' => ['content' => 'Second test text'],
                'annotations' => []
            ],
            [
                'id' => 'text-3',
                'data' => ['content' => 'Third test text'],
                'annotations' => []
            ]
        ];
        $models = ['claude-3-sonnet', 'gpt-4o'];

        // Create analysis job with correct total_texts calculation
        $analysisJob = AnalysisJob::create([
            'job_id' => $jobId,
            'status' => AnalysisJob::STATUS_PENDING,
            'total_texts' => count($fileContent) * count($models), // 3 texts × 2 models = 6 total processes
            'processed_texts' => 0,
            'requested_models' => $models,
            'custom_prompt' => null
        ]);

        // Verify correct total_texts calculation
        $this->assertEquals(6, $analysisJob->total_texts);
        $this->assertEquals(0, $analysisJob->processed_texts);
        $this->assertEquals(0.0, $analysisJob->getProgressPercentage());
    }

    public function test_progress_tracking_through_individual_jobs()
    {
        $jobId = 'test-job-' . Str::uuid();
        $textId = 'text-1';
        $content = 'Test text for progress tracking';
        $expertAnnotations = [];
        $modelKey = 'claude-3-sonnet';

        // Create analysis job
        $analysisJob = AnalysisJob::create([
            'job_id' => $jobId,
            'status' => AnalysisJob::STATUS_PROCESSING,
            'total_texts' => 4, // 2 texts × 2 models = 4 total processes
            'processed_texts' => 0,
            'requested_models' => ['claude-3-sonnet', 'gpt-4o'],
            'custom_prompt' => null
        ]);

        // Create text analyses
        TextAnalysis::create([
            'job_id' => $jobId,
            'text_id' => 'text-1',
            'content' => 'First text content',
            'expert_annotations' => []
        ]);

        TextAnalysis::create([
            'job_id' => $jobId,
            'text_id' => 'text-2',
            'content' => 'Second text content',
            'expert_annotations' => []
        ]);

        // Mock Claude service
        $mockClaudeService = Mockery::mock(ClaudeService::class);
        $mockClaudeService->shouldReceive('setModel')
            ->with($modelKey)
            ->once()
            ->andReturn(true);
        
        $mockClaudeService->shouldReceive('analyzeText')
            ->with($content, null)
            ->once()
            ->andReturn([
                'primaryChoice' => ['choices' => ['yes']],
                'annotations' => [],
                'desinformationTechnique' => ['choices' => []]
            ]);

        $this->app->instance(ClaudeService::class, $mockClaudeService);

        // Create and run individual text analysis job
        $job = new IndividualTextAnalysisJob(
            $jobId,
            $textId,
            $content,
            $expertAnnotations,
            $modelKey
        );

        $job->handle();

        // Check that progress was updated correctly
        $analysisJob->refresh();
        $this->assertEquals(1, $analysisJob->processed_texts); // 1 out of 4 processes completed
        $this->assertEquals(25.0, $analysisJob->getProgressPercentage()); // 25% progress

        // Verify ModelResult was created
        $modelResult = ModelResult::where('job_id', $jobId)
            ->where('text_id', $textId)
            ->where('model_key', $modelKey)
            ->first();

        $this->assertNotNull($modelResult);
        $this->assertEquals('completed', $modelResult->status);
    }

    public function test_progress_tracking_with_multiple_completions()
    {
        $jobId = 'test-job-' . Str::uuid();

        // Create analysis job for 2 texts × 2 models = 4 total processes
        $analysisJob = AnalysisJob::create([
            'job_id' => $jobId,
            'status' => AnalysisJob::STATUS_PROCESSING,
            'total_texts' => 4,
            'processed_texts' => 0,
            'requested_models' => ['claude-3-sonnet', 'gpt-4o'],
            'custom_prompt' => null
        ]);

        // Create text analyses
        $textAnalysis1 = TextAnalysis::create([
            'job_id' => $jobId,
            'text_id' => 'text-1',
            'content' => 'First text content',
            'expert_annotations' => []
        ]);

        $textAnalysis2 = TextAnalysis::create([
            'job_id' => $jobId,
            'text_id' => 'text-2',
            'content' => 'Second text content',
            'expert_annotations' => []
        ]);

        // Simulate completion of 3 out of 4 individual text-model combinations
        ModelResult::create([
            'job_id' => $jobId,
            'text_id' => 'text-1',
            'model_key' => 'claude-3-sonnet',
            'result' => ['primaryChoice' => ['choices' => ['yes']]],
            'status' => 'completed',
            'model_name' => 'claude-3-sonnet-20240229'
        ]);

        ModelResult::create([
            'job_id' => $jobId,
            'text_id' => 'text-1',
            'model_key' => 'gpt-4o',
            'result' => ['primaryChoice' => ['choices' => ['no']]],
            'status' => 'completed',
            'model_name' => 'gpt-4o'
        ]);

        ModelResult::create([
            'job_id' => $jobId,
            'text_id' => 'text-2',
            'model_key' => 'claude-3-sonnet',
            'result' => [],
            'status' => 'failed',
            'error' => 'API error',
            'model_name' => 'claude-3-sonnet-20240229'
        ]);

        // Update job progress manually (normally done by IndividualTextAnalysisJob)
        $completedCount = ModelResult::where('job_id', $jobId)
            ->whereIn('status', ['completed', 'failed'])
            ->count();

        $analysisJob->processed_texts = $completedCount;
        $analysisJob->save();

        // Check progress calculation
        $this->assertEquals(3, $analysisJob->processed_texts); // 3 out of 4 processes completed
        $this->assertEquals(75.0, $analysisJob->getProgressPercentage()); // 75% progress
        $this->assertEquals(AnalysisJob::STATUS_PROCESSING, $analysisJob->status); // Still processing

        // Complete the last one
        ModelResult::create([
            'job_id' => $jobId,
            'text_id' => 'text-2',
            'model_key' => 'gpt-4o',
            'result' => ['primaryChoice' => ['choices' => ['yes']]],
            'status' => 'completed',
            'model_name' => 'gpt-4o'
        ]);

        // Update progress again
        $completedCount = ModelResult::where('job_id', $jobId)
            ->whereIn('status', ['completed', 'failed'])
            ->count();

        $analysisJob->processed_texts = $completedCount;
        $analysisJob->status = AnalysisJob::STATUS_COMPLETED;
        $analysisJob->save();

        // Check final progress
        $this->assertEquals(4, $analysisJob->processed_texts); // 4 out of 4 processes completed
        $this->assertEquals(100.0, $analysisJob->getProgressPercentage()); // 100% progress
        $this->assertEquals(AnalysisJob::STATUS_COMPLETED, $analysisJob->status); // Completed
    }

    public function test_progress_with_empty_job()
    {
        $jobId = 'test-job-' . Str::uuid();

        // Create analysis job with no texts
        $analysisJob = AnalysisJob::create([
            'job_id' => $jobId,
            'status' => AnalysisJob::STATUS_PENDING,
            'total_texts' => 0,
            'processed_texts' => 0,
            'requested_models' => [],
            'custom_prompt' => null
        ]);

        // Check that progress calculation handles zero division correctly
        $this->assertEquals(0.0, $analysisJob->getProgressPercentage());
    }
}