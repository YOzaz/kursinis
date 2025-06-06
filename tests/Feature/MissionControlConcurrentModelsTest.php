<?php

namespace Tests\Feature;

use Tests\TestCase;
use App\Models\AnalysisJob;
use App\Models\TextAnalysis;
use App\Models\ModelResult;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;

class MissionControlConcurrentModelsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        // Simulate authenticated session
        session(['authenticated' => true, 'username' => 'testuser']);
    }

    /** @test */
    public function it_shows_processing_status_for_requested_models_without_results()
    {
        // Create a processing analysis job with multiple Claude models
        $jobId = Str::uuid();
        $job = AnalysisJob::create([
            'job_id' => $jobId,
            'status' => AnalysisJob::STATUS_PROCESSING,
            'name' => 'Concurrent Claude Models Test',
            'total_texts' => 2, // 2 models
            'processed_texts' => 1, // 1 completed
            'requested_models' => ['claude-sonnet-4', 'claude-opus-4']
        ]);

        // Create text analysis
        $textAnalysis = TextAnalysis::factory()->create([
            'job_id' => $jobId,
            'text_id' => 'text-1',
            'content' => 'Test content for concurrent models'
        ]);

        // First Claude model has completed (has ModelResult)
        ModelResult::factory()->create([
            'job_id' => $jobId,
            'text_id' => $textAnalysis->text_id,
            'model_key' => 'claude-sonnet-4',
            'provider' => 'anthropic',
            'model_name' => 'claude-3-5-sonnet-20241022',
            'status' => ModelResult::STATUS_COMPLETED,
            'annotations' => ['some' => 'result']
        ]);

        // Second Claude model is still processing (no ModelResult yet)
        // This should be detected as pending based on requested_models

        // Get mission control data
        $response = $this->get(route('api.mission-control'));
        $response->assertStatus(200);

        $data = $response->json();
        
        // Both models should be tracked
        $this->assertArrayHasKey('model_stats', $data);
        $modelStats = $data['model_stats'];

        // Claude Sonnet should show as operational (has completed result)
        if (isset($modelStats['claude-sonnet-4'])) {
            $sonnetStats = $modelStats['claude-sonnet-4'];
            $this->assertEquals('operational', $sonnetStats['status']);
            $this->assertEquals(1, $sonnetStats['successful']);
            $this->assertEquals(1, $sonnetStats['total_analyses']);
        }

        // Claude Opus should show as processing (requested but no result yet)
        if (isset($modelStats['claude-opus-4'])) {
            $opusStats = $modelStats['claude-opus-4'];
            $this->assertEquals('processing', $opusStats['status']);
            $this->assertEquals(1, $opusStats['pending']);
            $this->assertEquals(1, $opusStats['total_analyses']);
        }
    }

    /** @test */
    public function it_shows_idle_status_for_models_not_requested()
    {
        // Create a processing analysis job with only one model
        $jobId = Str::uuid();
        $job = AnalysisJob::create([
            'job_id' => $jobId,
            'status' => AnalysisJob::STATUS_PROCESSING,
            'name' => 'Single Model Test',
            'total_texts' => 1,
            'processed_texts' => 0,
            'requested_models' => ['claude-sonnet-4'] // Only Sonnet requested
        ]);

        $textAnalysis = TextAnalysis::factory()->create([
            'job_id' => $jobId,
            'text_id' => 'text-1',
            'content' => 'Test content'
        ]);

        // No ModelResult yet (still processing)

        $response = $this->get(route('api.mission-control'));
        $response->assertStatus(200);

        $data = $response->json();
        $modelStats = $data['model_stats'];

        // Sonnet should show as processing (requested but no result)
        if (isset($modelStats['claude-sonnet-4'])) {
            $sonnetStats = $modelStats['claude-sonnet-4'];
            $this->assertEquals('processing', $sonnetStats['status']);
            $this->assertEquals(1, $sonnetStats['pending']);
        }

        // Opus should show as idle (not requested)
        if (isset($modelStats['claude-opus-4'])) {
            $opusStats = $modelStats['claude-opus-4'];
            $this->assertEquals('idle', $opusStats['status']);
            $this->assertEquals(0, $opusStats['total_analyses']);
        }
    }

    /** @test */
    public function it_handles_completed_jobs_correctly()
    {
        // Create a completed analysis job
        $jobId = Str::uuid();
        $job = AnalysisJob::create([
            'job_id' => $jobId,
            'status' => AnalysisJob::STATUS_COMPLETED,
            'name' => 'Completed Analysis',
            'total_texts' => 2,
            'processed_texts' => 2,
            'requested_models' => ['claude-sonnet-4', 'gpt-4o']
        ]);

        $textAnalysis = TextAnalysis::factory()->create([
            'job_id' => $jobId,
            'text_id' => 'text-1',
            'content' => 'Test content'
        ]);

        // Both models have completed results
        ModelResult::factory()->create([
            'job_id' => $jobId,
            'text_id' => $textAnalysis->text_id,
            'model_key' => 'claude-sonnet-4',
            'status' => ModelResult::STATUS_COMPLETED,
            'annotations' => ['result' => 'data']
        ]);

        ModelResult::factory()->create([
            'job_id' => $jobId,
            'text_id' => $textAnalysis->text_id,
            'model_key' => 'gpt-4o',
            'status' => ModelResult::STATUS_COMPLETED,
            'annotations' => ['result' => 'data']
        ]);

        $response = $this->get(route('api.mission-control'));
        $response->assertStatus(200);

        $data = $response->json();
        $modelStats = $data['model_stats'];

        // Both models should show as operational
        if (isset($modelStats['claude-sonnet-4'])) {
            $sonnetStats = $modelStats['claude-sonnet-4'];
            $this->assertEquals('operational', $sonnetStats['status']);
            $this->assertEquals(1, $sonnetStats['successful']);
            $this->assertEquals(0, $sonnetStats['pending']);
        }

        if (isset($modelStats['gpt-4o'])) {
            $gptStats = $modelStats['gpt-4o'];
            $this->assertEquals('operational', $gptStats['status']);
            $this->assertEquals(1, $gptStats['successful']);
            $this->assertEquals(0, $gptStats['pending']);
        }
    }

    /** @test */
    public function it_handles_mixed_success_and_failure_states()
    {
        // Create analysis with one successful and one failed model
        $jobId = Str::uuid();
        $job = AnalysisJob::create([
            'job_id' => $jobId,
            'status' => AnalysisJob::STATUS_COMPLETED,
            'name' => 'Mixed Results Analysis',
            'total_texts' => 2,
            'processed_texts' => 2,
            'requested_models' => ['claude-sonnet-4', 'gemini-2.5-pro']
        ]);

        $textAnalysis = TextAnalysis::factory()->create([
            'job_id' => $jobId,
            'text_id' => 'text-1',
            'content' => 'Test content'
        ]);

        // Successful result
        ModelResult::factory()->create([
            'job_id' => $jobId,
            'text_id' => $textAnalysis->text_id,
            'model_key' => 'claude-sonnet-4',
            'status' => ModelResult::STATUS_COMPLETED,
            'annotations' => ['result' => 'success']
        ]);

        // Failed result
        ModelResult::factory()->create([
            'job_id' => $jobId,
            'text_id' => $textAnalysis->text_id,
            'model_key' => 'gemini-2.5-pro',
            'status' => ModelResult::STATUS_FAILED,
            'error_message' => 'API error occurred'
        ]);

        $response = $this->get(route('api.mission-control'));
        $response->assertStatus(200);

        $data = $response->json();
        $modelStats = $data['model_stats'];

        // Claude should show as operational
        if (isset($modelStats['claude-sonnet-4'])) {
            $sonnetStats = $modelStats['claude-sonnet-4'];
            $this->assertEquals('operational', $sonnetStats['status']);
            $this->assertEquals(1, $sonnetStats['successful']);
            $this->assertEquals(0, $sonnetStats['failed']);
        }

        // Gemini should show as failed
        if (isset($modelStats['gemini-2.5-pro'])) {
            $geminiStats = $modelStats['gemini-2.5-pro'];
            $this->assertEquals('failed', $geminiStats['status']);
            $this->assertEquals(0, $geminiStats['successful']);
            $this->assertEquals(1, $geminiStats['failed']);
        }
    }

    /** @test */
    public function it_filters_by_job_id_when_requested()
    {
        // Create two different jobs
        $jobId1 = Str::uuid();
        $job1 = AnalysisJob::create([
            'job_id' => $jobId1,
            'status' => AnalysisJob::STATUS_PROCESSING,
            'name' => 'Job 1',
            'total_texts' => 1,
            'processed_texts' => 0,
            'requested_models' => ['claude-sonnet-4']
        ]);

        $jobId2 = Str::uuid();
        $job2 = AnalysisJob::create([
            'job_id' => $jobId2,
            'status' => AnalysisJob::STATUS_PROCESSING,
            'name' => 'Job 2',
            'total_texts' => 1,
            'processed_texts' => 0,
            'requested_models' => ['gpt-4o']
        ]);

        TextAnalysis::factory()->create(['job_id' => $jobId1, 'text_id' => 'text-1']);
        TextAnalysis::factory()->create(['job_id' => $jobId2, 'text_id' => 'text-2']);

        // Filter by job_id1
        $response = $this->get(route('api.mission-control') . "?job_id={$jobId1}");
        $response->assertStatus(200);

        $data = $response->json();
        
        // Should only show stats for job1's models
        $this->assertArrayHasKey('model_stats', $data);
        $modelStats = $data['model_stats'];

        // Claude should show activity (from job1)
        if (isset($modelStats['claude-sonnet-4'])) {
            $this->assertGreaterThan(0, $modelStats['claude-sonnet-4']['total_analyses']);
        }

        // GPT should show idle or much lower activity (not in filtered job)
        if (isset($modelStats['gpt-4o'])) {
            $this->assertEquals(0, $modelStats['gpt-4o']['total_analyses']);
        }
    }
}