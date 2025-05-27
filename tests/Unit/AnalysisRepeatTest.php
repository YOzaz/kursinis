<?php

namespace Tests\Unit;

use Tests\TestCase;
use App\Models\AnalysisJob;
use App\Models\TextAnalysis;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Queue;

class AnalysisRepeatTest extends TestCase
{
    use RefreshDatabase;

    public function test_can_repeat_analysis_with_same_prompt()
    {
        Queue::fake();
        
        // Create original analysis
        $originalJob = AnalysisJob::factory()->create([
            'status' => 'completed',
            'custom_prompt' => 'Test custom prompt',
        ]);

        // Create text analyses for the original job
        $textAnalyses = TextAnalysis::factory()->count(2)->create([
            'job_id' => $originalJob->job_id,
        ]);

        // Make repeat request
        $response = $this->post(route('analysis.repeat'), [
            'reference_job_id' => (string) $originalJob->job_id,
            'prompt_type' => 'keep',
            'name' => 'Repeated Analysis',
            'description' => 'Test repeat functionality',
        ]);

        // Assert redirect to progress page
        $response->assertRedirect();
        $this->assertTrue(Str::contains($response->getTargetUrl(), '/progress/'));

        // Assert new job was created
        $newJobs = AnalysisJob::where('reference_analysis_id', $originalJob->job_id)->get();
        $this->assertCount(1, $newJobs);
        
        $newJob = $newJobs->first();
        $this->assertEquals('Repeated Analysis', $newJob->name);
        $this->assertEquals('Test repeat functionality', $newJob->description);
        $this->assertEquals('Test custom prompt', $newJob->custom_prompt);
        $this->assertEquals(2, $newJob->total_texts);
    }

    public function test_can_repeat_analysis_with_modified_prompt()
    {
        Queue::fake();
        
        // Create original analysis
        $originalJob = AnalysisJob::factory()->create([
            'status' => 'completed',
            'custom_prompt' => 'Original prompt',
        ]);

        // Create text analyses for the original job
        $textAnalysis = TextAnalysis::factory()->create([
            'job_id' => $originalJob->job_id,
        ]);
        
        // Make repeat request with custom prompt
        $response = $this->post(route('analysis.repeat'), [
            'reference_job_id' => (string) $originalJob->job_id,
            'prompt_type' => 'custom',
            'custom_prompt' => 'Modified prompt for testing',
            'name' => 'Modified Analysis',
        ]);

        // Assert redirect first
        $response->assertRedirect();
        
        // Assert new job has modified prompt
        $newJob = AnalysisJob::where('reference_analysis_id', $originalJob->job_id)->first();
        $this->assertNotNull($newJob);
        $this->assertEquals('Modified prompt for testing', $newJob->custom_prompt);
        $this->assertEquals('Modified Analysis', $newJob->name);
    }

    public function test_can_repeat_analysis_with_standard_prompt()
    {
        Queue::fake();
        
        // Create original analysis with custom prompt
        $originalJob = AnalysisJob::factory()->create([
            'status' => 'completed',
            'custom_prompt' => 'Some custom prompt',
        ]);

        TextAnalysis::factory()->create([
            'job_id' => $originalJob->job_id,
        ]);

        // Make repeat request with standard prompt
        $response = $this->post(route('analysis.repeat'), [
            'reference_job_id' => (string) $originalJob->job_id,
            'prompt_type' => 'standard',
            'name' => 'Standard Analysis',
        ]);

        // Assert new job uses standard prompt (null)
        $newJob = AnalysisJob::where('reference_analysis_id', $originalJob->job_id)->first();
        $this->assertNotNull($newJob);
        $this->assertNull($newJob->custom_prompt);
        $this->assertEquals('Standard Analysis', $newJob->name);
    }

    public function test_cannot_repeat_incomplete_analysis()
    {
        // Create incomplete analysis
        $incompleteJob = AnalysisJob::factory()->create([
            'status' => 'processing',
        ]);

        // Attempt to repeat
        $response = $this->post(route('analysis.repeat'), [
            'reference_job_id' => (string) $incompleteJob->job_id,
            'prompt_type' => 'keep',
            'name' => 'Should Fail',
        ]);

        // Assert error response
        $response->assertRedirect();
        $response->assertSessionHas('error');
        
        // Assert no new job was created
        $newJobs = AnalysisJob::where('reference_analysis_id', $incompleteJob->job_id)->get();
        $this->assertCount(0, $newJobs);
    }

    public function test_validation_fails_with_invalid_data()
    {
        $originalJob = AnalysisJob::factory()->create(['status' => 'completed']);

        // Test missing reference job
        $response = $this->post(route('analysis.repeat'), [
            'prompt_type' => 'keep',
        ]);
        $response->assertSessionHasErrors(['reference_job_id']);

        // Test invalid prompt type
        $response = $this->post(route('analysis.repeat'), [
            'reference_job_id' => (string) $originalJob->job_id,
            'prompt_type' => 'invalid',
        ]);
        $response->assertSessionHasErrors(['prompt_type']);

        // Test too long custom prompt
        $response = $this->post(route('analysis.repeat'), [
            'reference_job_id' => (string) $originalJob->job_id,
            'prompt_type' => 'custom',
            'custom_prompt' => str_repeat('a', 10001), // Too long
        ]);
        $response->assertSessionHasErrors(['custom_prompt']);
    }
}