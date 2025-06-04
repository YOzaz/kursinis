<?php

namespace Tests\Feature;

use Tests\TestCase;
use App\Models\AnalysisJob;
use App\Models\TextAnalysis;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;

class DetailedStatusTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        
        // Authenticate for all tests
        $this->withSession(['authenticated' => true]);
    }

    public function test_detailed_status_endpoint_returns_comprehensive_data()
    {
        // Create test job
        $jobId = Str::uuid()->toString();
        $job = AnalysisJob::create([
            'job_id' => $jobId,
            'status' => AnalysisJob::STATUS_PROCESSING,
            'total_texts' => 100,
            'processed_texts' => 50,
            'name' => 'Test Analysis Job'
        ]);

        // Create some test analyses
        for ($i = 1; $i <= 5; $i++) {
            TextAnalysis::create([
                'job_id' => $jobId,
                'text_id' => (string) $i,
                'content' => "Test content {$i} with various length",
                'expert_annotations' => [],
                'claude_annotations' => $i <= 3 ? ['test' => 'data'] : null,
                'gpt_annotations' => $i <= 2 ? ['test' => 'data'] : null,
                'gemini_annotations' => $i <= 1 ? ['test' => 'data'] : null,
            ]);
        }

        $response = $this->get("/status/{$jobId}");

        $response->assertStatus(200);
        
        $data = $response->json();
        
        // Verify structure
        $this->assertArrayHasKey('stats', $data);
        $this->assertArrayHasKey('logs', $data);
        $this->assertArrayHasKey('queue', $data);
        $this->assertArrayHasKey('timestamp', $data);

        // Verify job stats
        $this->assertEquals($jobId, $data['stats']['job']['id']);
        $this->assertEquals('processing', $data['stats']['job']['status']);
        $this->assertEquals(100, $data['stats']['job']['total_texts']);
        $this->assertEquals(50, $data['stats']['job']['processed_texts']);
        $this->assertEquals(50.0, $data['stats']['job']['progress_percentage']);

        // Verify text stats
        $this->assertEquals(5, $data['stats']['texts']['total_records']);
        $this->assertEquals(5, $data['stats']['texts']['unique_texts']);

        // Verify model stats
        $this->assertArrayHasKey('claude-opus-4', $data['stats']['models']);
        $this->assertArrayHasKey('gpt-4.1', $data['stats']['models']);
        $this->assertArrayHasKey('gemini-2.5-pro', $data['stats']['models']);

        // Check Claude model stats
        $claudeStats = $data['stats']['models']['claude-opus-4'];
        $this->assertEquals('anthropic', $claudeStats['provider']);
        $this->assertEquals(3, $claudeStats['completed']); // 3 out of 5 have claude_annotations
        $this->assertEquals(60.0, $claudeStats['success_rate']); // 3/5 = 60%

        // Check GPT model stats
        $gptStats = $data['stats']['models']['gpt-4.1'];
        $this->assertEquals('openai', $gptStats['provider']);
        $this->assertEquals(2, $gptStats['completed']); // 2 out of 5 have gpt_annotations

        // Check Gemini model stats
        $geminiStats = $data['stats']['models']['gemini-2.5-pro'];
        $this->assertEquals('google', $geminiStats['provider']);
        $this->assertEquals(1, $geminiStats['completed']); // 1 out of 5 has gemini_annotations
    }

    public function test_detailed_status_handles_nonexistent_job()
    {
        $response = $this->get("/status/nonexistent-job-id");
        
        $response->assertStatus(404);
        $response->assertJson(['error' => 'Job not found']);
    }

    public function test_detailed_status_view_loads_correctly()
    {
        $jobId = Str::uuid()->toString();
        
        $response = $this->get("/status-view/{$jobId}");
        
        $response->assertStatus(200);
        $response->assertSee('AI ANALYSIS MISSION CONTROL');
        $response->assertSee('Real-time Intelligence Processing Status');
        $response->assertSee($jobId); // Job ID should be embedded in the view
    }

    public function test_model_status_determination()
    {
        $jobId = Str::uuid()->toString();
        $job = AnalysisJob::create([
            'job_id' => $jobId,
            'status' => AnalysisJob::STATUS_PROCESSING,
            'total_texts' => 3,
            'processed_texts' => 0,
        ]);

        // Create 3 texts with different completion states
        TextAnalysis::create([
            'job_id' => $jobId,
            'text_id' => '1',
            'content' => 'Test 1',
            'expert_annotations' => [],
            'claude_annotations' => ['test' => 'completed'],
            'claude_error' => null,
        ]);

        TextAnalysis::create([
            'job_id' => $jobId,
            'text_id' => '2',
            'content' => 'Test 2',
            'expert_annotations' => [],
            'claude_annotations' => ['test' => 'completed'],
            'claude_error' => null,
        ]);

        TextAnalysis::create([
            'job_id' => $jobId,
            'text_id' => '3',
            'content' => 'Test 3',
            'expert_annotations' => [],
            'claude_annotations' => null,
            'claude_error' => 'Some error occurred',
        ]);

        $response = $this->get("/status/{$jobId}");
        $data = $response->json();

        $claudeStats = $data['stats']['models']['claude-opus-4'];
        
        // Debug what we're actually getting
        // dump($claudeStats);
        
        // Should show partial_failure status (completed < total and errors > 0)
        $this->assertEquals(2, $claudeStats['completed']);
        $this->assertEquals(1, $claudeStats['errors']);
        $this->assertEquals('partial_failure', $claudeStats['status']);
        $this->assertEquals(66.7, $claudeStats['success_rate']); // 2/3 = 66.7%
    }

    public function test_chunk_estimation_calculation()
    {
        $jobId = Str::uuid()->toString();
        $job = AnalysisJob::create([
            'job_id' => $jobId,
            'status' => AnalysisJob::STATUS_PROCESSING,
            'total_texts' => 10,
            'processed_texts' => 0,
        ]);

        // Create 10 texts
        for ($i = 1; $i <= 10; $i++) {
            TextAnalysis::create([
                'job_id' => $jobId,
                'text_id' => (string) $i,
                'content' => "Test content {$i}",
                'expert_annotations' => [],
            ]);
        }

        $response = $this->get("/status/{$jobId}");
        $data = $response->json();

        $claudeStats = $data['stats']['models']['claude-opus-4'];
        
        // With chunk size of 3, 10 texts should need ceil(10/3) = 4 chunks
        $this->assertEquals(4, $claudeStats['estimated_chunks']);
        $this->assertEquals(4, $claudeStats['api_calls_made']); // Should match estimated chunks
    }

    public function test_progress_calculation_edge_cases()
    {
        // Test with zero total texts
        $jobId1 = Str::uuid()->toString();
        $job1 = AnalysisJob::create([
            'job_id' => $jobId1,
            'status' => AnalysisJob::STATUS_PENDING,
            'total_texts' => 0,
            'processed_texts' => 0,
        ]);

        $response1 = $this->get("/status/{$jobId1}");
        $data1 = $response1->json();
        
        $this->assertEquals(0, $data1['stats']['job']['progress_percentage']);

        // Test with more processed than total (edge case)
        $jobId2 = Str::uuid()->toString();
        $job2 = AnalysisJob::create([
            'job_id' => $jobId2,
            'status' => AnalysisJob::STATUS_COMPLETED,
            'total_texts' => 10,
            'processed_texts' => 15, // More than total
        ]);

        $response2 = $this->get("/status/{$jobId2}");
        $data2 = $response2->json();
        
        $this->assertEquals(150.0, $data2['stats']['job']['progress_percentage']); // Should handle gracefully
    }

    public function test_queue_status_information()
    {
        $jobId = Str::uuid()->toString();
        $job = AnalysisJob::create([
            'job_id' => $jobId,
            'status' => AnalysisJob::STATUS_PROCESSING,
            'total_texts' => 1,
            'processed_texts' => 0,
        ]);

        $response = $this->get("/status/{$jobId}");
        $data = $response->json();

        $this->assertArrayHasKey('queue', $data);
        $this->assertArrayHasKey('batch_workers_active', $data['queue']);
        $this->assertArrayHasKey('jobs_in_queue', $data['queue']);
        $this->assertArrayHasKey('failed_jobs', $data['queue']);
        $this->assertArrayHasKey('last_queue_activity', $data['queue']);

        $this->assertIsBool($data['queue']['batch_workers_active']);
        $this->assertIsInt($data['queue']['jobs_in_queue']);
        $this->assertIsInt($data['queue']['failed_jobs']);
    }

    public function test_logs_structure()
    {
        $jobId = Str::uuid()->toString();
        $job = AnalysisJob::create([
            'job_id' => $jobId,
            'status' => AnalysisJob::STATUS_PROCESSING,
            'total_texts' => 1,
            'processed_texts' => 0,
        ]);

        $response = $this->get("/status/{$jobId}");
        $data = $response->json();

        $this->assertArrayHasKey('logs', $data);
        $this->assertIsArray($data['logs']);

        if (!empty($data['logs'])) {
            $firstLog = $data['logs'][0];
            $this->assertArrayHasKey('timestamp', $firstLog);
            $this->assertArrayHasKey('level', $firstLog);
            $this->assertArrayHasKey('message', $firstLog);
            $this->assertArrayHasKey('context', $firstLog);
        }
    }

    public function test_refresh_interval_configuration()
    {
        $jobId = Str::uuid()->toString();
        $job = AnalysisJob::create([
            'job_id' => $jobId,
            'status' => AnalysisJob::STATUS_PROCESSING,
            'total_texts' => 1,
            'processed_texts' => 0,
        ]);

        $response = $this->get("/status/{$jobId}");
        $data = $response->json();

        $this->assertArrayHasKey('refresh_interval', $data);
        $this->assertEquals(5, $data['refresh_interval']); // 5 seconds as configured
    }
}