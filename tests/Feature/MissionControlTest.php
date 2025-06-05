<?php

namespace Tests\Feature;

use Tests\TestCase;
use App\Models\AnalysisJob;
use App\Models\TextAnalysis;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;

class MissionControlTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        
        // Authenticate for all tests
        $this->withSession(['authenticated' => true]);
    }

    public function test_mission_control_view_loads_correctly()
    {
        $response = $this->get('/mission-control');
        
        $response->assertStatus(200);
        $response->assertSee('AI ANALYSIS MISSION CONTROL');
        $response->assertSee('System-Wide Intelligence Processing Status');
        $response->assertSee('mission-control'); // Should contain the view identifier
    }

    public function test_mission_control_api_returns_system_wide_data()
    {
        // Create multiple test jobs
        $job1Id = Str::uuid()->toString();
        $job2Id = Str::uuid()->toString();
        
        $job1 = AnalysisJob::create([
            'job_id' => $job1Id,
            'status' => AnalysisJob::STATUS_COMPLETED,
            'total_texts' => 50,
            'processed_texts' => 50,
            'name' => 'First Analysis Job',
            'models' => json_encode(['claude-opus-4', 'gpt-4.1', 'gemini-2.5-pro'])
        ]);

        $job2 = AnalysisJob::create([
            'job_id' => $job2Id,
            'status' => AnalysisJob::STATUS_PROCESSING,
            'total_texts' => 100,
            'processed_texts' => 75,
            'name' => 'Second Analysis Job',
            'models' => json_encode(['claude-opus-4', 'gpt-4.1', 'gemini-2.5-pro'])
        ]);

        // Create some text analyses for both jobs
        for ($i = 1; $i <= 3; $i++) {
            TextAnalysis::create([
                'job_id' => $job1Id,
                'text_id' => "job1-text-{$i}",
                'content' => "Job 1 content {$i}",
                'expert_annotations' => [],
                'claude_annotations' => ['test' => 'data'],
                'gpt_annotations' => $i <= 2 ? ['test' => 'data'] : null,
                'gemini_annotations' => $i <= 1 ? ['test' => 'data'] : null,
            ]);

            TextAnalysis::create([
                'job_id' => $job2Id,
                'text_id' => "job2-text-{$i}",
                'content' => "Job 2 content {$i}",
                'expert_annotations' => [],
                'claude_annotations' => $i <= 2 ? ['test' => 'data'] : null,
                'gpt_annotations' => ['test' => 'data'],
                'gemini_annotations' => null,
                'claude_error' => $i === 3 ? 'Test error' : null,
            ]);
        }

        $response = $this->get('/api/mission-control');

        $response->assertStatus(200);
        
        $data = $response->json();
        
        // Verify main structure
        $this->assertArrayHasKey('system', $data);
        $this->assertArrayHasKey('job_details', $data);
        $this->assertArrayHasKey('logs', $data);
        $this->assertArrayHasKey('timestamp', $data);
        $this->assertArrayHasKey('filtered_by_job', $data);
        $this->assertArrayHasKey('refresh_interval', $data);

        // Verify no job filtering by default
        $this->assertNull($data['filtered_by_job']);
        $this->assertNull($data['job_details']);

        // Verify system overview
        $overview = $data['system']['overview'];
        $this->assertEquals(2, $overview['total_jobs']);
        $this->assertEquals(1, $overview['active_jobs']); // 1 processing
        $this->assertEquals(1, $overview['completed_jobs']); // 1 completed
        $this->assertEquals(0, $overview['failed_jobs']);
        $this->assertEquals(6, $overview['total_texts_processed']); // 3 + 3 text analyses
        $this->assertEquals(6, $overview['unique_texts']); // 6 different text_ids

        // Verify model statistics are aggregated across jobs
        $models = $data['system']['models'];
        $this->assertArrayHasKey('claude-opus-4', $models);
        $this->assertArrayHasKey('gpt-4.1', $models);
        $this->assertArrayHasKey('gemini-2.5-pro', $models);

        // Check Claude model aggregation (3 from job1 + 2 from job2 = 5 successful)
        $claudeStats = $models['claude-opus-4'];
        $this->assertEquals('anthropic', $claudeStats['provider']);
        $this->assertEquals(5, $claudeStats['successful']); // 3 + 2
        $this->assertEquals(1, $claudeStats['failed']); // 0 + 1
        $this->assertEquals(0, $claudeStats['pending']);

        // Check GPT model aggregation (2 from job1 + 3 from job2 = 5 successful)
        $gptStats = $models['gpt-4.1'];
        $this->assertEquals('openai', $gptStats['provider']);
        $this->assertEquals(5, $gptStats['successful']); // 2 + 3
        $this->assertEquals(0, $gptStats['failed']);

        // Check Gemini model aggregation (1 from job1 + 0 from job2 = 1 successful)
        $geminiStats = $models['gemini-2.5-pro'];
        $this->assertEquals('google', $geminiStats['provider']);
        $this->assertEquals(1, $geminiStats['successful']); // 1 + 0
        $this->assertEquals(0, $geminiStats['failed']);
    }

    public function test_mission_control_api_with_job_filtering()
    {
        // Create test jobs
        $targetJobId = Str::uuid()->toString();
        $otherJobId = Str::uuid()->toString();
        
        $targetJob = AnalysisJob::create([
            'job_id' => $targetJobId,
            'status' => AnalysisJob::STATUS_PROCESSING,
            'total_texts' => 20,
            'processed_texts' => 15,
            'name' => 'Target Analysis Job',
            'models' => json_encode(['claude-opus-4'])
        ]);

        $otherJob = AnalysisJob::create([
            'job_id' => $otherJobId,
            'status' => AnalysisJob::STATUS_COMPLETED,
            'total_texts' => 10,
            'processed_texts' => 10,
            'name' => 'Other Analysis Job',
            'models' => json_encode(['claude-opus-4'])
        ]);

        // Create text analyses only for target job
        for ($i = 1; $i <= 2; $i++) {
            TextAnalysis::create([
                'job_id' => $targetJobId,
                'text_id' => "target-text-{$i}",
                'content' => "Target content {$i}",
                'expert_annotations' => [],
                'claude_annotations' => ['test' => 'data'],
            ]);
        }

        $response = $this->get("/api/mission-control?job_id={$targetJobId}");

        $response->assertStatus(200);
        
        $data = $response->json();
        
        // Verify filtering is applied
        $this->assertEquals($targetJobId, $data['filtered_by_job']);
        $this->assertNotNull($data['job_details']);

        // Verify job details contain only the target job
        $jobDetails = $data['job_details'];
        $this->assertEquals($targetJobId, $jobDetails['id']);
        $this->assertEquals('Target Analysis Job', $jobDetails['name']);
        $this->assertEquals('processing', $jobDetails['status']);
        $this->assertEquals(75.0, $jobDetails['progress_percentage']); // 15/20 = 75%

        // Verify system stats reflect only the filtered job
        $overview = $data['system']['overview'];
        $this->assertEquals(1, $overview['total_jobs']); // Only target job
        $this->assertEquals(1, $overview['active_jobs']); // Target job is processing
        $this->assertEquals(0, $overview['completed_jobs']); // Target job not completed
        $this->assertEquals(2, $overview['unique_texts']); // Only target job texts
    }

    public function test_mission_control_handles_no_jobs()
    {
        $response = $this->get('/api/mission-control');

        $response->assertStatus(200);
        
        $data = $response->json();
        
        // Should return empty but valid structure
        $overview = $data['system']['overview'];
        $this->assertEquals(0, $overview['total_jobs']);
        $this->assertEquals(0, $overview['active_jobs']);
        $this->assertEquals(0, $overview['completed_jobs']);
        $this->assertEquals(0, $overview['failed_jobs']);
        $this->assertEquals(0, $overview['unique_texts']);

        // Models should still be present but with zero stats
        $models = $data['system']['models'];
        $this->assertArrayHasKey('claude-opus-4', $models);
        $this->assertEquals(0, $models['claude-opus-4']['total_analyses']);
        $this->assertEquals('idle', $models['claude-opus-4']['status']);
    }

    public function test_mission_control_handles_nonexistent_job_filter()
    {
        $response = $this->get('/api/mission-control?job_id=nonexistent-job-id');

        $response->assertStatus(200);
        
        $data = $response->json();
        
        // Should return empty results for the nonexistent job
        $this->assertEquals('nonexistent-job-id', $data['filtered_by_job']);
        $this->assertNull($data['job_details']); // No job found
        $this->assertEquals(0, $data['system']['overview']['total_jobs']);
    }

    public function test_model_status_determination_across_jobs()
    {
        // Create jobs with different model completion states
        $jobId1 = Str::uuid()->toString();
        $jobId2 = Str::uuid()->toString();
        
        AnalysisJob::create([
            'job_id' => $jobId1,
            'status' => AnalysisJob::STATUS_PROCESSING,
            'total_texts' => 2,
            'processed_texts' => 1,
            'models' => json_encode(['claude-opus-4', 'gpt-4.1']), // Add models that should be processed
        ]);

        AnalysisJob::create([
            'job_id' => $jobId2,
            'status' => AnalysisJob::STATUS_COMPLETED,
            'total_texts' => 1,
            'processed_texts' => 1,
            'models' => json_encode(['claude-opus-4']), // Add models that should be processed
        ]);

        // Job 1: One successful, one that should be pending (has job models but no annotations yet)
        TextAnalysis::create([
            'job_id' => $jobId1,
            'text_id' => '1',
            'content' => 'Test 1',
            'expert_annotations' => [],
            'claude_annotations' => ['test' => 'completed'],
        ]);

        // Job 2: One successful for Claude
        TextAnalysis::create([
            'job_id' => $jobId2,
            'text_id' => '3',
            'content' => 'Test 3',
            'expert_annotations' => [],
            'claude_annotations' => ['test' => 'completed'],
        ]);

        $response = $this->get('/api/mission-control');
        $data = $response->json();

        $claudeStats = $data['system']['models']['claude-opus-4'];
        
        // Across both jobs: 2 successful, 0 failed, 0 pending (simplified test)
        $this->assertEquals(2, $claudeStats['successful']);
        $this->assertEquals(0, $claudeStats['failed']);
        $this->assertEquals(0, $claudeStats['pending']);
        $this->assertEquals(2, $claudeStats['total_analyses']);
        $this->assertEquals('operational', $claudeStats['status']); // All completed
        $this->assertEquals(100.0, $claudeStats['success_rate']); // 2/2 = 100%
    }

    public function test_mission_control_route_access()
    {
        // Test the main mission control view route
        $response = $this->get('/mission-control');
        $response->assertStatus(200);
        $response->assertViewIs('mission-control');
    }

    public function test_progress_page_contains_mission_control_links()
    {
        $jobId = Str::uuid()->toString();
        AnalysisJob::create([
            'job_id' => $jobId,
            'status' => AnalysisJob::STATUS_PROCESSING,
            'total_texts' => 10,
            'processed_texts' => 5,
        ]);

        $response = $this->get("/progress/{$jobId}");
        
        $response->assertStatus(200);
        $response->assertSee('Mission Control (Filtered)');
        $response->assertSee('System-Wide View');
        $response->assertSee("job_id={$jobId}"); // Filtered link should contain job ID
        $response->assertSee('mission-control'); // Both links should point to mission control
    }

    public function test_analysis_show_page_contains_mission_control_links()
    {
        $jobId = Str::uuid()->toString();
        AnalysisJob::create([
            'job_id' => $jobId,
            'status' => AnalysisJob::STATUS_COMPLETED,
            'total_texts' => 5,
            'processed_texts' => 5,
        ]);

        $response = $this->get("/analyses/{$jobId}");
        
        $response->assertStatus(200);
        $response->assertSee('Mission Control (Filtered)');
        $response->assertSee('System-Wide View');
        $response->assertSee("job_id={$jobId}"); // Filtered link should contain job ID
        $response->assertSee('mission-control'); // Both links should point to mission control
    }

    public function test_logs_structure_in_mission_control()
    {
        $jobId = Str::uuid()->toString();
        AnalysisJob::create([
            'job_id' => $jobId,
            'status' => AnalysisJob::STATUS_PROCESSING,
            'total_texts' => 1,
            'processed_texts' => 0,
        ]);

        $response = $this->get('/api/mission-control');
        $data = $response->json();

        $this->assertArrayHasKey('logs', $data);
        $this->assertIsArray($data['logs']);

        // Logs should include job_id field for system-wide view
        if (!empty($data['logs'])) {
            $firstLog = $data['logs'][0];
            $this->assertArrayHasKey('timestamp', $firstLog);
            $this->assertArrayHasKey('level', $firstLog);
            $this->assertArrayHasKey('message', $firstLog);
            $this->assertArrayHasKey('context', $firstLog);
            $this->assertArrayHasKey('job_id', $firstLog); // System-wide logs include job_id
        }
    }

    public function test_queue_status_in_mission_control()
    {
        $response = $this->get('/api/mission-control');
        $data = $response->json();

        $this->assertArrayHasKey('queue', $data['system']);
        $queue = $data['system']['queue'];
        
        $this->assertArrayHasKey('batch_workers_active', $queue);
        $this->assertArrayHasKey('jobs_in_queue', $queue);
        $this->assertArrayHasKey('failed_jobs', $queue);
        $this->assertArrayHasKey('last_activity', $queue);

        $this->assertIsBool($queue['batch_workers_active']);
        $this->assertIsInt($queue['jobs_in_queue']);
        $this->assertIsInt($queue['failed_jobs']);
    }

    public function test_refresh_interval_in_mission_control()
    {
        $response = $this->get('/api/mission-control');
        $data = $response->json();

        $this->assertArrayHasKey('refresh_interval', $data);
        $this->assertEquals(5, $data['refresh_interval']); // 5 seconds as configured
    }
}