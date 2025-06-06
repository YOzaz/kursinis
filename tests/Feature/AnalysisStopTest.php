<?php

namespace Tests\Feature;

use Tests\TestCase;
use App\Models\AnalysisJob;
use App\Models\TextAnalysis;
use App\Models\ModelResult;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;

class AnalysisStopTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        // Simulate authenticated session
        session(['authenticated' => true, 'username' => 'testuser']);
    }

    /** @test */
    public function it_can_stop_processing_analysis()
    {
        // Create a processing analysis job
        $jobId = Str::uuid();
        $job = AnalysisJob::create([
            'job_id' => $jobId,
            'status' => AnalysisJob::STATUS_PROCESSING,
            'name' => 'Test Analysis',
            'total_texts' => 5,
            'processed_texts' => 2,
            'requested_models' => ['claude-sonnet-4', 'gpt-4o']
        ]);

        // Create some text analyses and model results
        $textAnalysis = TextAnalysis::factory()->create(['job_id' => $jobId]);
        
        ModelResult::factory()->create([
            'job_id' => $jobId,
            'text_id' => $textAnalysis->text_id,
            'model_key' => 'claude-sonnet-4',
            'status' => ModelResult::STATUS_COMPLETED
        ]);

        ModelResult::factory()->create([
            'job_id' => $jobId,
            'text_id' => $textAnalysis->text_id,
            'model_key' => 'gpt-4o',
            'status' => ModelResult::STATUS_PENDING
        ]);

        // Stop the analysis
        $response = $this->post(route('analysis.stop'), [
            'job_id' => $jobId
        ]);

        // Assert response
        $response->assertRedirect(route('analyses.show', $jobId));
        $response->assertSessionHas('success', 'Analizė sėkmingai sustabdyta.');

        // Assert job status changed
        $job->refresh();
        $this->assertEquals(AnalysisJob::STATUS_CANCELLED, $job->status);

        // Assert pending model results were cancelled
        $pendingResult = ModelResult::where('job_id', $jobId)
            ->where('status', ModelResult::STATUS_PENDING)
            ->first();
        
        if ($pendingResult) {
            $this->assertEquals('cancelled', $pendingResult->status);
        }
    }

    /** @test */
    public function it_can_stop_pending_analysis()
    {
        // Create a pending analysis job
        $jobId = Str::uuid();
        $job = AnalysisJob::create([
            'job_id' => $jobId,
            'status' => AnalysisJob::STATUS_PENDING,
            'name' => 'Pending Test Analysis',
            'total_texts' => 3,
            'processed_texts' => 0,
            'requested_models' => ['gemini-2.5-pro']
        ]);

        // Stop the analysis
        $response = $this->post(route('analysis.stop'), [
            'job_id' => $jobId
        ]);

        $response->assertRedirect(route('analyses.show', $jobId));
        $response->assertSessionHas('success', 'Analizė sėkmingai sustabdyta.');

        // Assert job status changed
        $job->refresh();
        $this->assertEquals(AnalysisJob::STATUS_CANCELLED, $job->status);
    }

    /** @test */
    public function it_cannot_stop_completed_analysis()
    {
        // Create a completed analysis job
        $jobId = Str::uuid();
        $job = AnalysisJob::create([
            'job_id' => $jobId,
            'status' => AnalysisJob::STATUS_COMPLETED,
            'name' => 'Completed Test Analysis',
            'total_texts' => 2,
            'processed_texts' => 2,
            'requested_models' => ['claude-opus-4']
        ]);

        // Try to stop the analysis
        $response = $this->post(route('analysis.stop'), [
            'job_id' => $jobId
        ]);

        $response->assertRedirect();
        $response->assertSessionHas('error', 'Galima sustabdyti tik vykdomą analizę.');

        // Assert job status didn't change
        $job->refresh();
        $this->assertEquals(AnalysisJob::STATUS_COMPLETED, $job->status);
    }

    /** @test */
    public function it_cannot_stop_failed_analysis()
    {
        // Create a failed analysis job
        $jobId = Str::uuid();
        $job = AnalysisJob::create([
            'job_id' => $jobId,
            'status' => AnalysisJob::STATUS_FAILED,
            'name' => 'Failed Test Analysis',
            'total_texts' => 1,
            'processed_texts' => 0,
            'requested_models' => ['claude-sonnet-4']
        ]);

        // Try to stop the analysis
        $response = $this->post(route('analysis.stop'), [
            'job_id' => $jobId
        ]);

        $response->assertRedirect();
        $response->assertSessionHas('error', 'Galima sustabdyti tik vykdomą analizę.');

        // Assert job status didn't change
        $job->refresh();
        $this->assertEquals(AnalysisJob::STATUS_FAILED, $job->status);
    }

    /** @test */
    public function it_requires_valid_job_id()
    {
        // Try to stop with non-existent job ID
        $response = $this->post(route('analysis.stop'), [
            'job_id' => 'non-existent-id'
        ]);

        $response->assertSessionHasErrors(['job_id']);
    }

    /** @test */
    public function it_requires_job_id_parameter()
    {
        // Try to stop without job_id
        $response = $this->post(route('analysis.stop'), []);

        $response->assertSessionHasErrors(['job_id']);
    }

    /** @test */
    public function analysis_job_has_cancelled_status_constant()
    {
        $this->assertEquals('cancelled', AnalysisJob::STATUS_CANCELLED);
    }

    /** @test */
    public function analysis_job_can_check_if_cancelled()
    {
        $job = AnalysisJob::factory()->create([
            'status' => AnalysisJob::STATUS_CANCELLED
        ]);

        $this->assertTrue($job->isCancelled());

        $job->status = AnalysisJob::STATUS_PROCESSING;
        $this->assertFalse($job->isCancelled());
    }

    /** @test */
    public function stop_analysis_logs_activity()
    {
        $jobId = Str::uuid();
        $job = AnalysisJob::create([
            'job_id' => $jobId,
            'status' => AnalysisJob::STATUS_PROCESSING,
            'name' => 'Test Analysis for Logging',
            'total_texts' => 1,
            'processed_texts' => 0,
            'requested_models' => ['claude-sonnet-4']
        ]);

        // Mock log to verify it's called
        $this->expectsEvents(\Illuminate\Log\Events\MessageLogged::class);

        $response = $this->post(route('analysis.stop'), [
            'job_id' => $jobId
        ]);

        $response->assertRedirect(route('analyses.show', $jobId));
    }
}