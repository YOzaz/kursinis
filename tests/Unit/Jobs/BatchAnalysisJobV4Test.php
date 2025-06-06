<?php

namespace Tests\Unit\Jobs;

use Tests\TestCase;
use App\Jobs\BatchAnalysisJobV4;
use App\Jobs\IndividualTextAnalysisJob;
use App\Models\AnalysisJob;
use App\Models\TextAnalysis;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Str;

class BatchAnalysisJobV4Test extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        
        // Fake the queue to test job dispatching
        Queue::fake();
        
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
                ],
                'gemini-1.5-pro' => [
                    'provider' => 'google',
                    'model' => 'gemini-1.5-pro-latest',
                    'api_key' => 'test-key',
                    'max_tokens' => 4000,
                    'temperature' => 0.1,
                ]
            ]
        ]);
    }

    public function test_job_creation_and_properties()
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
                'annotations' => [['type' => 'propaganda', 'start' => 0, 'end' => 6]]
            ]
        ];
        $models = ['claude-3-sonnet', 'gpt-4o'];

        $job = new BatchAnalysisJobV4($jobId, $fileContent, $models);

        $this->assertEquals($jobId, $job->jobId);
        $this->assertEquals($fileContent, $job->fileContent);
        $this->assertEquals($models, $job->models);
        $this->assertEquals(3, $job->tries);
        $this->assertEquals(300, $job->timeout);
    }

    public function test_individual_text_jobs_dispatched_for_each_text_model_combination()
    {
        $jobId = 'test-job-' . Str::uuid();
        $fileContent = [
            [
                'id' => 'text-1',
                'data' => ['content' => 'First test text for analysis'],
                'annotations' => []
            ],
            [
                'id' => 'text-2',
                'data' => ['content' => 'Second test text for analysis'],
                'annotations' => [['type' => 'propaganda', 'start' => 0, 'end' => 6]]
            ]
        ];
        $models = ['claude-3-sonnet', 'gpt-4o'];

        // Create analysis job
        $analysisJob = AnalysisJob::create([
            'job_id' => $jobId,
            'status' => AnalysisJob::STATUS_PENDING,
            'requested_models' => $models,
            'custom_prompt' => 'Test custom prompt'
        ]);

        // Create and handle the batch job
        $batchJob = new BatchAnalysisJobV4($jobId, $fileContent, $models);
        $batchJob->handle();

        // Assert that individual text analysis jobs were dispatched
        // Should dispatch 2 texts × 2 models = 4 individual jobs
        Queue::assertPushed(IndividualTextAnalysisJob::class, 4);

        // Assert that each text-model combination got a job
        Queue::assertPushed(IndividualTextAnalysisJob::class, function ($job) use ($jobId) {
            return $job->jobId === $jobId && 
                   $job->textId === 'text-1' && 
                   $job->modelKey === 'claude-3-sonnet' &&
                   $job->content === 'First test text for analysis' &&
                   $job->customPrompt === 'Test custom prompt';
        });

        Queue::assertPushed(IndividualTextAnalysisJob::class, function ($job) use ($jobId) {
            return $job->jobId === $jobId && 
                   $job->textId === 'text-1' && 
                   $job->modelKey === 'gpt-4o';
        });

        Queue::assertPushed(IndividualTextAnalysisJob::class, function ($job) use ($jobId) {
            return $job->jobId === $jobId && 
                   $job->textId === 'text-2' && 
                   $job->modelKey === 'claude-3-sonnet' &&
                   count($job->expertAnnotations) === 1;
        });

        Queue::assertPushed(IndividualTextAnalysisJob::class, function ($job) use ($jobId) {
            return $job->jobId === $jobId && 
                   $job->textId === 'text-2' && 
                   $job->modelKey === 'gpt-4o';
        });

        // Assert that all jobs are dispatched to the 'individual' queue
        Queue::assertPushedOn('individual', IndividualTextAnalysisJob::class, 4);
    }

    public function test_text_analysis_records_created()
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
                'annotations' => [['type' => 'bias', 'start' => 7, 'end' => 11]]
            ]
        ];
        $models = ['claude-3-sonnet'];

        // Create analysis job
        $analysisJob = AnalysisJob::create([
            'job_id' => $jobId,
            'status' => AnalysisJob::STATUS_PENDING,
            'requested_models' => $models,
            'custom_prompt' => null
        ]);

        // Create and handle the batch job
        $batchJob = new BatchAnalysisJobV4($jobId, $fileContent, $models);
        $batchJob->handle();

        // Assert TextAnalysis records were created
        $textAnalyses = TextAnalysis::where('job_id', $jobId)->get();
        $this->assertCount(2, $textAnalyses);

        // Check first text analysis
        $text1 = $textAnalyses->where('text_id', 'text-1')->first();
        $this->assertNotNull($text1);
        $this->assertEquals('First test text', $text1->content);
        $this->assertEmpty($text1->expert_annotations);

        // Check second text analysis
        $text2 = $textAnalyses->where('text_id', 'text-2')->first();
        $this->assertNotNull($text2);
        $this->assertEquals('Second test text', $text2->content);
        $this->assertCount(1, $text2->expert_annotations);
        $this->assertEquals('bias', $text2->expert_annotations[0]['type']);
    }

    public function test_analysis_job_status_updated_to_processing()
    {
        $jobId = 'test-job-' . Str::uuid();
        $fileContent = [
            [
                'id' => 'text-1',
                'data' => ['content' => 'Test text'],
                'annotations' => []
            ]
        ];
        $models = ['claude-3-sonnet'];

        // Create analysis job
        $analysisJob = AnalysisJob::create([
            'job_id' => $jobId,
            'status' => AnalysisJob::STATUS_PENDING,
            'requested_models' => $models,
            'custom_prompt' => null
        ]);

        // Create and handle the batch job
        $batchJob = new BatchAnalysisJobV4($jobId, $fileContent, $models);
        $batchJob->handle();

        // Assert job status was updated
        $analysisJob->refresh();
        $this->assertEquals('processing', $analysisJob->status);
    }

    public function test_single_text_single_model_dispatches_one_job()
    {
        $jobId = 'test-job-' . Str::uuid();
        $fileContent = [
            [
                'id' => 'single-text',
                'data' => ['content' => 'Single text for testing'],
                'annotations' => []
            ]
        ];
        $models = ['claude-3-sonnet'];

        // Create analysis job
        $analysisJob = AnalysisJob::create([
            'job_id' => $jobId,
            'status' => AnalysisJob::STATUS_PENDING,
            'requested_models' => $models,
            'custom_prompt' => null
        ]);

        // Create and handle the batch job
        $batchJob = new BatchAnalysisJobV4($jobId, $fileContent, $models);
        $batchJob->handle();

        // Assert exactly one individual job was dispatched
        Queue::assertPushed(IndividualTextAnalysisJob::class, 1);

        Queue::assertPushed(IndividualTextAnalysisJob::class, function ($job) use ($jobId) {
            return $job->jobId === $jobId && 
                   $job->textId === 'single-text' && 
                   $job->modelKey === 'claude-3-sonnet' &&
                   $job->content === 'Single text for testing';
        });
    }

    public function test_multiple_models_for_single_text()
    {
        $jobId = 'test-job-' . Str::uuid();
        $fileContent = [
            [
                'id' => 'multi-model-text',
                'data' => ['content' => 'Text to be analyzed by multiple models'],
                'annotations' => []
            ]
        ];
        $models = ['claude-3-sonnet', 'gpt-4o', 'gemini-1.5-pro'];

        // Create analysis job
        $analysisJob = AnalysisJob::create([
            'job_id' => $jobId,
            'status' => AnalysisJob::STATUS_PENDING,
            'requested_models' => $models,
            'custom_prompt' => null
        ]);

        // Create and handle the batch job
        $batchJob = new BatchAnalysisJobV4($jobId, $fileContent, $models);
        $batchJob->handle();

        // Assert 3 individual jobs were dispatched (one for each model)
        Queue::assertPushed(IndividualTextAnalysisJob::class, 3);

        // Check each model gets a job
        foreach ($models as $modelKey) {
            Queue::assertPushed(IndividualTextAnalysisJob::class, function ($job) use ($jobId, $modelKey) {
                return $job->jobId === $jobId && 
                       $job->textId === 'multi-model-text' && 
                       $job->modelKey === $modelKey;
            });
        }
    }

    public function test_error_handling_when_analysis_job_not_found()
    {
        $jobId = 'non-existent-job-' . Str::uuid();
        $fileContent = [
            [
                'id' => 'text-1',
                'data' => ['content' => 'Test text'],
                'annotations' => []
            ]
        ];
        $models = ['claude-3-sonnet'];

        // Don't create analysis job - it should handle missing job gracefully
        $batchJob = new BatchAnalysisJobV4($jobId, $fileContent, $models);
        
        // Handle should complete without exception
        $batchJob->handle();

        // No individual jobs should be dispatched
        Queue::assertNothingPushed();
    }

    public function test_custom_prompt_passed_to_individual_jobs()
    {
        $jobId = 'test-job-' . Str::uuid();
        $customPrompt = 'Please analyze this text with special attention to emotional language and bias';
        $fileContent = [
            [
                'id' => 'prompt-text',
                'data' => ['content' => 'Text with custom prompt'],
                'annotations' => []
            ]
        ];
        $models = ['claude-3-sonnet'];

        // Create analysis job with custom prompt
        $analysisJob = AnalysisJob::create([
            'job_id' => $jobId,
            'status' => AnalysisJob::STATUS_PENDING,
            'requested_models' => $models,
            'custom_prompt' => $customPrompt
        ]);

        // Create and handle the batch job
        $batchJob = new BatchAnalysisJobV4($jobId, $fileContent, $models);
        $batchJob->handle();

        // Assert the custom prompt was passed to individual job
        Queue::assertPushed(IndividualTextAnalysisJob::class, function ($job) use ($customPrompt) {
            return $job->customPrompt === $customPrompt;
        });
    }

    public function test_large_dataset_creates_many_individual_jobs()
    {
        $jobId = 'test-job-' . Str::uuid();
        
        // Create 5 texts with 2 models = 10 individual jobs
        $fileContent = [];
        for ($i = 1; $i <= 5; $i++) {
            $fileContent[] = [
                'id' => "text-{$i}",
                'data' => ['content' => "Content for text {$i}"],
                'annotations' => []
            ];
        }
        $models = ['claude-3-sonnet', 'gpt-4o'];

        // Create analysis job
        $analysisJob = AnalysisJob::create([
            'job_id' => $jobId,
            'status' => AnalysisJob::STATUS_PENDING,
            'requested_models' => $models,
            'custom_prompt' => null
        ]);

        // Create and handle the batch job
        $batchJob = new BatchAnalysisJobV4($jobId, $fileContent, $models);
        $batchJob->handle();

        // Assert 10 individual jobs were dispatched (5 texts × 2 models)
        Queue::assertPushed(IndividualTextAnalysisJob::class, 10);

        // Assert all text analysis records were created
        $textAnalyses = TextAnalysis::where('job_id', $jobId)->get();
        $this->assertCount(5, $textAnalyses);
    }

    public function test_empty_file_content_handles_gracefully()
    {
        $jobId = 'test-job-' . Str::uuid();
        $fileContent = [];
        $models = ['claude-3-sonnet'];

        // Create analysis job
        $analysisJob = AnalysisJob::create([
            'job_id' => $jobId,
            'status' => AnalysisJob::STATUS_PENDING,
            'requested_models' => $models,
            'custom_prompt' => null
        ]);

        // Create and handle the batch job
        $batchJob = new BatchAnalysisJobV4($jobId, $fileContent, $models);
        $batchJob->handle();

        // No individual jobs should be dispatched
        Queue::assertNothingPushed();

        // No text analysis records should be created
        $textAnalyses = TextAnalysis::where('job_id', $jobId)->get();
        $this->assertCount(0, $textAnalyses);
    }
}