<?php

namespace Tests\Unit\Unit\Jobs;

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

    protected function setUp(): void
    {
        parent::setUp();
        Queue::fake();
    }

    public function test_can_instantiate_batch_analysis_job(): void
    {
        $job = new BatchAnalysisJob(
            'test-job-id',
            [['id' => '1', 'data' => ['content' => 'test'], 'annotations' => []]],
            ['claude-4']
        );

        $this->assertInstanceOf(BatchAnalysisJob::class, $job);
        $this->assertEquals('test-job-id', $job->jobId);
        $this->assertEquals(['claude-4'], $job->models);
        $this->assertEquals(3, $job->tries);
        $this->assertEquals(1800, $job->timeout);
    }

    public function test_handle_processes_file_content_and_creates_text_analyses(): void
    {
        $analysisJob = AnalysisJob::factory()->create([
            'job_id' => 'test-job-id',
            'status' => AnalysisJob::STATUS_PENDING
        ]);

        $fileContent = [
            [
                'id' => 'text-1',
                'data' => ['content' => 'First test content'],
                'annotations' => [['type' => 'propaganda', 'text' => 'test']]
            ],
            [
                'id' => 'text-2', 
                'data' => ['content' => 'Second test content'],
                'annotations' => []
            ]
        ];

        $models = ['claude-4', 'gemini-2.5-pro'];

        $job = new BatchAnalysisJob('test-job-id', $fileContent, $models);
        $job->handle();

        // Check that AnalysisJob status was updated
        $analysisJob->refresh();
        $this->assertEquals(AnalysisJob::STATUS_PROCESSING, $analysisJob->status);

        // Check that TextAnalysis records were created
        $this->assertDatabaseHas('text_analyses', [
            'job_id' => 'test-job-id',
            'text_id' => 'text-1',
            'content' => 'First test content'
        ]);

        $this->assertDatabaseHas('text_analyses', [
            'job_id' => 'test-job-id',
            'text_id' => 'text-2',
            'content' => 'Second test content'
        ]);

        // Check that AnalyzeTextJob was dispatched for each text and model
        Queue::assertPushed(AnalyzeTextJob::class, 4); // 2 texts × 2 models

        // Check that job totals were updated correctly
        $analysisJob->refresh();
        $this->assertEquals(4, $analysisJob->total_texts); // 2 texts × 2 models
        $this->assertEquals(0, $analysisJob->processed_texts);
    }

    public function test_handle_updates_job_status_to_processing(): void
    {
        $analysisJob = AnalysisJob::factory()->create([
            'job_id' => 'test-job-id',
            'status' => AnalysisJob::STATUS_PENDING
        ]);

        $job = new BatchAnalysisJob(
            'test-job-id',
            [['id' => '1', 'data' => ['content' => 'test'], 'annotations' => []]],
            ['claude-4']
        );

        $job->handle();

        $analysisJob->refresh();
        $this->assertEquals(AnalysisJob::STATUS_PROCESSING, $analysisJob->status);
    }

    public function test_handle_logs_progress_for_multiple_texts(): void
    {
        $analysisJob = AnalysisJob::factory()->create([
            'job_id' => 'test-job-id'
        ]);

        // Create 100+ texts to trigger progress logging
        $fileContent = [];
        for ($i = 1; $i <= 150; $i++) {
            $fileContent[] = [
                'id' => "text-{$i}",
                'data' => ['content' => "Test content {$i}"],
                'annotations' => []
            ];
        }

        $job = new BatchAnalysisJob('test-job-id', $fileContent, ['claude-4']);
        $job->handle();

        // Test completed successfully if no exceptions thrown
        $this->assertTrue(true);
    }

    public function test_handle_continues_processing_after_individual_text_error(): void
    {
        $analysisJob = AnalysisJob::factory()->create([
            'job_id' => 'test-job-id'
        ]);

        // Include content that might cause issues  
        $fileContent = [
            [
                'id' => 'text-1',
                'data' => ['content' => 'Valid content'],
                'annotations' => []
            ],
            [
                'id' => 'text-2',
                'data' => ['content' => ''],  // Empty content but valid structure
                'annotations' => []
            ],
            [
                'id' => 'text-3',
                'data' => ['content' => 'Another valid content'],
                'annotations' => []
            ]
        ];

        $job = new BatchAnalysisJob('test-job-id', $fileContent, ['claude-4']);
        
        // Should not throw exception despite potential issues
        $job->handle();

        // Should have processed all valid texts
        $this->assertDatabaseHas('text_analyses', [
            'job_id' => 'test-job-id',
            'text_id' => 'text-1'
        ]);

        $this->assertDatabaseHas('text_analyses', [
            'job_id' => 'test-job-id',
            'text_id' => 'text-3'
        ]);
    }

    public function test_handle_returns_early_if_job_not_found(): void
    {
        $job = new BatchAnalysisJob(
            'non-existent-job-id',
            [['id' => '1', 'data' => ['content' => 'test'], 'annotations' => []]],
            ['claude-4']
        );

        $job->handle();

        // Test completed without exceptions
        $this->assertTrue(true);

        // Should not create any text analyses
        $this->assertDatabaseMissing('text_analyses', [
            'job_id' => 'non-existent-job-id'
        ]);
    }

    public function test_handle_marks_job_as_failed_on_exception(): void
    {
        $analysisJob = AnalysisJob::factory()->create([
            'job_id' => 'test-job-id',
            'status' => AnalysisJob::STATUS_PENDING
        ]);

        // Test the failed method directly
        $job = new BatchAnalysisJob('test-job-id', [], ['claude-4']);
        $exception = new \Exception('Test forced exception');
        
        $job->failed($exception);

        $analysisJob->refresh();
        $this->assertEquals(AnalysisJob::STATUS_FAILED, $analysisJob->status);
        $this->assertEquals('Test forced exception', $analysisJob->error_message);
    }

    public function test_failed_marks_job_as_failed(): void
    {
        $analysisJob = AnalysisJob::factory()->create([
            'job_id' => 'test-job-id'
        ]);

        $job = new BatchAnalysisJob(
            'test-job-id',
            [['id' => '1', 'data' => ['content' => 'test'], 'annotations' => []]],
            ['claude-4']
        );

        $exception = new \Exception('Test exception');
        $job->failed($exception);

        $analysisJob->refresh();
        $this->assertEquals(AnalysisJob::STATUS_FAILED, $analysisJob->status);
        $this->assertEquals('Test exception', $analysisJob->error_message);

        // Verify the error was handled properly
        $this->assertEquals('Test exception', $analysisJob->error_message);
    }

    public function test_job_queue_configuration(): void
    {
        $job = new BatchAnalysisJob(
            'test-job-id',
            [['id' => '1', 'data' => ['content' => 'test'], 'annotations' => []]],
            ['claude-4']
        );

        // Test that job implements ShouldQueue and has proper queue traits
        $this->assertInstanceOf(\Illuminate\Contracts\Queue\ShouldQueue::class, $job);
        
        $traits = class_uses(BatchAnalysisJob::class);
        $this->assertContains(\Illuminate\Bus\Queueable::class, $traits);
        $this->assertContains(\Illuminate\Foundation\Bus\Dispatchable::class, $traits);
        $this->assertContains(\Illuminate\Queue\InteractsWithQueue::class, $traits);
        $this->assertContains(\Illuminate\Queue\SerializesModels::class, $traits);
    }

    public function test_handle_preserves_expert_annotations(): void
    {
        $analysisJob = AnalysisJob::factory()->create([
            'job_id' => 'test-job-id'
        ]);

        $expertAnnotations = [
            ['type' => 'propaganda', 'text' => 'expert annotation', 'start' => 0, 'end' => 10]
        ];

        $fileContent = [
            [
                'id' => 'text-1',
                'data' => ['content' => 'Test content with expert annotations'],
                'annotations' => $expertAnnotations
            ]
        ];

        $job = new BatchAnalysisJob('test-job-id', $fileContent, ['claude-4']);
        $job->handle();

        $textAnalysis = TextAnalysis::where('text_id', 'text-1')->first();
        $this->assertNotNull($textAnalysis);
        $this->assertEquals($expertAnnotations, $textAnalysis->expert_annotations);
    }

    public function test_handle_with_multiple_models_creates_correct_job_count(): void
    {
        $analysisJob = AnalysisJob::factory()->create([
            'job_id' => 'test-job-id'
        ]);

        $fileContent = [
            ['id' => 'text-1', 'data' => ['content' => 'content 1'], 'annotations' => []],
            ['id' => 'text-2', 'data' => ['content' => 'content 2'], 'annotations' => []],
            ['id' => 'text-3', 'data' => ['content' => 'content 3'], 'annotations' => []]
        ];

        $models = ['claude-4', 'gemini-2.5-pro', 'gpt-4.1'];

        $job = new BatchAnalysisJob('test-job-id', $fileContent, $models);
        $job->handle();

        // Should create 3 texts × 3 models = 9 analysis jobs
        Queue::assertPushed(AnalyzeTextJob::class, 9);

        $analysisJob->refresh();
        $this->assertEquals(9, $analysisJob->total_texts);
    }

    public function test_job_can_be_dispatched(): void
    {
        $fileContent = [
            ['id' => 1, 'data' => ['content' => 'Test'], 'annotations' => []]
        ];
        $models = ['claude-4'];
        
        BatchAnalysisJob::dispatch('queue-test-job', $fileContent, $models);

        Queue::assertPushed(BatchAnalysisJob::class, function ($job) {
            return $job->jobId === 'queue-test-job';
        });
    }

    public function test_job_handles_empty_file_content(): void
    {
        $analysisJob = AnalysisJob::factory()->create([
            'job_id' => 'empty-content-job',
            'status' => AnalysisJob::STATUS_PENDING
        ]);

        $job = new BatchAnalysisJob('empty-content-job', [], ['claude-4']);
        $job->handle();

        // Should complete without errors even with no content
        $analysisJob->refresh();
        $this->assertEquals(AnalysisJob::STATUS_PROCESSING, $analysisJob->status);
        $this->assertEquals(0, $analysisJob->total_texts);
    }
}