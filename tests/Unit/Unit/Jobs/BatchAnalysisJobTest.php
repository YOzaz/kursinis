<?php

namespace Tests\Unit\Jobs;

use Tests\TestCase;
use App\Jobs\BatchAnalysisJob;
use App\Jobs\AnalyzeTextJob;
use App\Models\AnalysisJob;
use App\Models\TextAnalysis;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;

class BatchAnalysisJobTest extends TestCase
{
    use RefreshDatabase;

    public function test_job_can_be_instantiated(): void
    {
        $jobId = 'test-job-id';
        $fileContent = [
            ['id' => 1, 'data' => ['content' => 'Test content'], 'annotations' => []]
        ];
        $models = ['claude', 'gemini'];
        
        $job = new BatchAnalysisJob($jobId, $fileContent, $models);
        
        $this->assertInstanceOf(BatchAnalysisJob::class, $job);
    }

    public function test_job_processes_file_content(): void
    {
        Queue::fake();
        
        $analysisJob = AnalysisJob::factory()->create([
            'job_id' => 'test-job-123',
            'status' => 'pending'
        ]);

        $fileContent = [
            [
                'id' => 1,
                'data' => ['content' => 'Test propaganda content'],
                'annotations' => [['start' => 0, 'end' => 4, 'text' => 'Test']]
            ],
            [
                'id' => 2,
                'data' => ['content' => 'Another test content'],
                'annotations' => []
            ]
        ];
        $models = ['claude', 'gemini'];

        $job = new BatchAnalysisJob('test-job-123', $fileContent, $models);
        $job->handle();

        // Verify text analysis records were created
        $this->assertDatabaseHas('text_analyses', [
            'job_id' => 'test-job-123',
            'text_id' => '1',
            'content' => 'Test propaganda content'
        ]);

        $this->assertDatabaseHas('text_analyses', [
            'job_id' => 'test-job-123',
            'text_id' => '2',
            'content' => 'Another test content'
        ]);

        // Verify individual analysis jobs were dispatched
        Queue::assertPushed(AnalyzeTextJob::class, 4); // 2 texts * 2 models
    }

    public function test_job_updates_analysis_job_status(): void
    {
        $analysisJob = AnalysisJob::factory()->create([
            'job_id' => 'status-test-job',
            'status' => 'pending'
        ]);

        $fileContent = [
            ['id' => 1, 'data' => ['content' => 'Test content'], 'annotations' => []]
        ];
        $models = ['claude'];

        $job = new BatchAnalysisJob('status-test-job', $fileContent, $models);
        $job->handle();

        // Refresh the model to get updated status
        $analysisJob->refresh();
        $this->assertEquals('processing', $analysisJob->status);
    }

    public function test_job_handles_missing_analysis_job(): void
    {
        $fileContent = [
            ['id' => 1, 'data' => ['content' => 'Test content'], 'annotations' => []]
        ];
        $models = ['claude'];

        $job = new BatchAnalysisJob('non-existent-job', $fileContent, $models);
        
        // Should not throw exception for missing job
        $job->handle();
        
        $this->assertTrue(true); // Job completed without exceptions
    }

    public function test_job_can_be_queued(): void
    {
        Queue::fake();

        $fileContent = [
            ['id' => 1, 'data' => ['content' => 'Test'], 'annotations' => []]
        ];
        $models = ['claude'];
        
        BatchAnalysisJob::dispatch('queue-test-job', $fileContent, $models);

        Queue::assertPushed(BatchAnalysisJob::class, function ($job) {
            return $job->jobId === 'queue-test-job';
        });
    }

    public function test_job_handles_empty_file_content(): void
    {
        $analysisJob = AnalysisJob::factory()->create([
            'job_id' => 'empty-content-job',
            'status' => 'pending'
        ]);

        $job = new BatchAnalysisJob('empty-content-job', [], ['claude']);
        $job->handle();

        // Should complete without errors even with no content
        $analysisJob->refresh();
        $this->assertEquals('processing', $analysisJob->status);
    }

    public function test_job_creates_text_analysis_with_annotations(): void
    {
        $analysisJob = AnalysisJob::factory()->create([
            'job_id' => 'annotation-test-job',
            'status' => 'pending'
        ]);

        $annotations = [
            ['start' => 0, 'end' => 10, 'text' => 'propaganda'],
            ['start' => 15, 'end' => 25, 'text' => 'technique']
        ];

        $fileContent = [
            [
                'id' => 1,
                'data' => ['content' => 'Test content with annotations'],
                'annotations' => $annotations
            ]
        ];

        $job = new BatchAnalysisJob('annotation-test-job', $fileContent, ['claude']);
        $job->handle();

        $textAnalysis = TextAnalysis::where('job_id', 'annotation-test-job')->first();
        $this->assertNotNull($textAnalysis);
        $this->assertEquals($annotations, $textAnalysis->expert_annotations);
    }

    public function test_job_processes_multiple_models(): void
    {
        Queue::fake();
        
        $analysisJob = AnalysisJob::factory()->create([
            'job_id' => 'multi-model-job',
            'status' => 'pending'
        ]);

        $fileContent = [
            ['id' => 1, 'data' => ['content' => 'Test content'], 'annotations' => []]
        ];
        $models = ['claude', 'gemini', 'openai'];

        $job = new BatchAnalysisJob('multi-model-job', $fileContent, $models);
        $job->handle();

        // Should dispatch one AnalyzeTextJob for each model
        Queue::assertPushed(AnalyzeTextJob::class, 3);
    }
}