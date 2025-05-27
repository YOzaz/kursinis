<?php

namespace Tests\Unit\Unit\Models;

use App\Models\AnalysisJob;
use App\Models\TextAnalysis;
use App\Models\ComparisonMetric;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AnalysisJobTest extends TestCase
{
    use RefreshDatabase;

    public function test_analysis_job_can_be_created(): void
    {
        $analysisJob = AnalysisJob::factory()->create([
            'job_id' => 'test-job-123',
            'status' => AnalysisJob::STATUS_PENDING,
            'total_texts' => 100,
            'processed_texts' => 0,
        ]);

        $this->assertDatabaseHas('analysis_jobs', [
            'job_id' => 'test-job-123',
            'status' => AnalysisJob::STATUS_PENDING,
            'total_texts' => 100,
        ]);
    }

    public function test_analysis_job_has_required_fields(): void
    {
        $analysisJob = AnalysisJob::factory()->create();

        $this->assertNotNull($analysisJob->job_id);
        $this->assertNotNull($analysisJob->status);
        $this->assertNotNull($analysisJob->total_texts);
        $this->assertNotNull($analysisJob->processed_texts);
    }

    public function test_analysis_job_has_text_analyses_relationship(): void
    {
        $analysisJob = AnalysisJob::factory()->create();
        
        $this->assertInstanceOf(\Illuminate\Database\Eloquent\Relations\HasMany::class, $analysisJob->textAnalyses());
    }

    public function test_analysis_job_has_comparison_metrics_relationship(): void
    {
        $analysisJob = AnalysisJob::factory()->create();
        
        $this->assertInstanceOf(\Illuminate\Database\Eloquent\Relations\HasMany::class, $analysisJob->comparisonMetrics());
    }

    // Experiment functionality has been removed

    public function test_analysis_job_status_constants(): void
    {
        $this->assertEquals('pending', AnalysisJob::STATUS_PENDING);
        $this->assertEquals('processing', AnalysisJob::STATUS_PROCESSING);
        $this->assertEquals('completed', AnalysisJob::STATUS_COMPLETED);
        $this->assertEquals('failed', AnalysisJob::STATUS_FAILED);
    }

    public function test_is_completed_method(): void
    {
        $completedJob = AnalysisJob::factory()->completed()->create();
        $pendingJob = AnalysisJob::factory()->pending()->create();

        $this->assertTrue($completedJob->isCompleted());
        $this->assertFalse($pendingJob->isCompleted());
    }

    public function test_is_failed_method(): void
    {
        $failedJob = AnalysisJob::factory()->failed()->create();
        $completedJob = AnalysisJob::factory()->completed()->create();

        $this->assertTrue($failedJob->isFailed());
        $this->assertFalse($completedJob->isFailed());
    }

    public function test_get_progress_percentage(): void
    {
        $analysisJob = AnalysisJob::factory()->create([
            'total_texts' => 100,
            'processed_texts' => 50,
        ]);

        $this->assertEquals(50.0, $analysisJob->getProgressPercentage());
    }

    public function test_get_progress_percentage_with_zero_texts(): void
    {
        $analysisJob = AnalysisJob::factory()->create([
            'total_texts' => 0,
            'processed_texts' => 0,
        ]);

        $this->assertEquals(0.0, $analysisJob->getProgressPercentage());
    }

    public function test_factory_states(): void
    {
        $pendingJob = AnalysisJob::factory()->pending()->create();
        $processingJob = AnalysisJob::factory()->processing()->create();
        $completedJob = AnalysisJob::factory()->completed()->create();
        $failedJob = AnalysisJob::factory()->failed()->create();

        $this->assertEquals(AnalysisJob::STATUS_PENDING, $pendingJob->status);
        $this->assertEquals(AnalysisJob::STATUS_PROCESSING, $processingJob->status);
        $this->assertEquals(AnalysisJob::STATUS_COMPLETED, $completedJob->status);
        $this->assertEquals(AnalysisJob::STATUS_FAILED, $failedJob->status);
    }
}