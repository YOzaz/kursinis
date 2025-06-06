<?php

namespace Tests\Feature;

use Tests\TestCase;
use App\Models\AnalysisJob;
use App\Models\TextAnalysis;
use App\Models\ModelResult;
use App\Jobs\BatchAnalysisJobV4;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Str;

class AnalysisRepeatTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        // Simulate authenticated session
        session(['authenticated' => true, 'username' => 'testuser']);
        Queue::fake();
    }

    /** @test */
    public function it_can_repeat_completed_analysis_with_standard_prompt()
    {
        // Create a completed analysis job with text analyses
        $originalJobId = Str::uuid();
        $originalJob = AnalysisJob::create([
            'job_id' => $originalJobId,
            'status' => AnalysisJob::STATUS_COMPLETED,
            'name' => 'Original Analysis',
            'total_texts' => 2,
            'processed_texts' => 2,
            'requested_models' => ['claude-sonnet-4', 'gpt-4o'],
            'custom_prompt' => 'Original custom prompt'
        ]);

        // Create text analyses
        $textAnalysis1 = TextAnalysis::factory()->create([
            'job_id' => $originalJobId,
            'text_id' => 'text-1',
            'content' => 'Test content 1',
            'expert_annotations' => [['type' => 'test', 'value' => 'annotation1']]
        ]);

        $textAnalysis2 = TextAnalysis::factory()->create([
            'job_id' => $originalJobId,
            'text_id' => 'text-2',
            'content' => 'Test content 2',
            'expert_annotations' => [['type' => 'test', 'value' => 'annotation2']]
        ]);

        // Add some model results to track which models were used
        ModelResult::factory()->create([
            'job_id' => $originalJobId,
            'text_id' => 'text-1',
            'model_key' => 'claude-sonnet-4',
            'status' => ModelResult::STATUS_COMPLETED
        ]);

        ModelResult::factory()->create([
            'job_id' => $originalJobId,
            'text_id' => 'text-1',
            'model_key' => 'gpt-4o',
            'status' => ModelResult::STATUS_COMPLETED
        ]);

        // Repeat the analysis with standard prompt
        $response = $this->post(route('analysis.repeat'), [
            'reference_job_id' => $originalJobId,
            'prompt_type' => 'standard',
            'name' => 'Repeated Analysis',
            'description' => 'Test repeat with standard prompt'
        ]);

        // Assert redirect to progress page
        $response->assertRedirect();
        $response->assertSessionHas('success', 'Pakartotinė analizė sėkmingai paleista!');

        // Assert new job was created
        $newJobs = AnalysisJob::where('reference_analysis_id', $originalJobId)->get();
        $this->assertCount(1, $newJobs);

        $newJob = $newJobs->first();
        $this->assertEquals('Repeated Analysis', $newJob->name);
        $this->assertEquals('Test repeat with standard prompt', $newJob->description);
        $this->assertNull($newJob->custom_prompt); // Should be null for standard prompt
        $this->assertEquals(['claude-sonnet-4', 'gpt-4o'], $newJob->requested_models);
        $this->assertEquals(AnalysisJob::STATUS_PROCESSING, $newJob->status);

        // Assert text analyses were copied
        $newTextAnalyses = TextAnalysis::where('job_id', $newJob->job_id)->get();
        $this->assertCount(2, $newTextAnalyses);

        // Assert BatchAnalysisJobV4 was dispatched
        Queue::assertPushed(BatchAnalysisJobV4::class, function ($job) use ($newJob) {
            return $job->jobId === $newJob->job_id;
        });
    }

    /** @test */
    public function it_can_repeat_analysis_keeping_original_prompt()
    {
        // Create a completed analysis job with custom prompt
        $originalJobId = Str::uuid();
        $originalJob = AnalysisJob::create([
            'job_id' => $originalJobId,
            'status' => AnalysisJob::STATUS_COMPLETED,
            'name' => 'Original Analysis',
            'total_texts' => 1,
            'processed_texts' => 1,
            'requested_models' => ['gemini-2.5-pro'],
            'custom_prompt' => 'Special custom prompt for analysis'
        ]);

        TextAnalysis::factory()->create([
            'job_id' => $originalJobId,
            'text_id' => 'text-1',
            'content' => 'Test content',
            'expert_annotations' => []
        ]);

        // Repeat keeping the original prompt
        $response = $this->post(route('analysis.repeat'), [
            'reference_job_id' => $originalJobId,
            'prompt_type' => 'keep',
            'name' => 'Repeated with Original Prompt'
        ]);

        $response->assertRedirect();
        $response->assertSessionHas('success');

        // Assert new job has the original custom prompt
        $newJob = AnalysisJob::where('reference_analysis_id', $originalJobId)->first();
        $this->assertEquals('Special custom prompt for analysis', $newJob->custom_prompt);
    }

    /** @test */
    public function it_can_repeat_analysis_with_new_custom_prompt()
    {
        // Create a completed analysis job
        $originalJobId = Str::uuid();
        $originalJob = AnalysisJob::create([
            'job_id' => $originalJobId,
            'status' => AnalysisJob::STATUS_COMPLETED,
            'name' => 'Original Analysis',
            'total_texts' => 1,
            'processed_texts' => 1,
            'requested_models' => ['claude-opus-4'],
            'custom_prompt' => 'Old prompt'
        ]);

        TextAnalysis::factory()->create([
            'job_id' => $originalJobId,
            'text_id' => 'text-1',
            'content' => 'Test content',
            'expert_annotations' => []
        ]);

        // Repeat with new custom prompt
        $response = $this->post(route('analysis.repeat'), [
            'reference_job_id' => $originalJobId,
            'prompt_type' => 'custom',
            'custom_prompt' => 'Brand new custom prompt for repeat analysis',
            'name' => 'Repeated with New Prompt'
        ]);

        $response->assertRedirect();
        $response->assertSessionHas('success');

        // Assert new job has the new custom prompt
        $newJob = AnalysisJob::where('reference_analysis_id', $originalJobId)->first();
        $this->assertEquals('Brand new custom prompt for repeat analysis', $newJob->custom_prompt);
    }

    /** @test */
    public function it_cannot_repeat_non_completed_analysis()
    {
        // Create a processing analysis job
        $jobId = Str::uuid();
        $job = AnalysisJob::create([
            'job_id' => $jobId,
            'status' => AnalysisJob::STATUS_PROCESSING,
            'name' => 'Processing Analysis',
            'total_texts' => 1,
            'processed_texts' => 0,
            'requested_models' => ['claude-sonnet-4']
        ]);

        // Try to repeat the processing analysis
        $response = $this->post(route('analysis.repeat'), [
            'reference_job_id' => $jobId,
            'prompt_type' => 'standard',
            'name' => 'Should Not Work'
        ]);

        $response->assertRedirect();
        $response->assertSessionHas('error', 'Galima pakartoti tik sėkmingai baigtą analizę.');

        // Assert no new job was created
        $newJobs = AnalysisJob::where('reference_analysis_id', $jobId)->get();
        $this->assertCount(0, $newJobs);

        // Assert no job was dispatched
        Queue::assertNotPushed(BatchAnalysisJobV4::class);
    }

    /** @test */
    public function it_requires_valid_reference_job_id()
    {
        $response = $this->post(route('analysis.repeat'), [
            'reference_job_id' => 'non-existent-job',
            'prompt_type' => 'standard'
        ]);

        $response->assertSessionHasErrors(['reference_job_id']);
    }

    /** @test */
    public function it_requires_valid_prompt_type()
    {
        $jobId = Str::uuid();
        AnalysisJob::create([
            'job_id' => $jobId,
            'status' => AnalysisJob::STATUS_COMPLETED,
            'name' => 'Test Job',
            'total_texts' => 1,
            'processed_texts' => 1,
            'requested_models' => ['claude-sonnet-4']
        ]);

        $response = $this->post(route('analysis.repeat'), [
            'reference_job_id' => $jobId,
            'prompt_type' => 'invalid_type'
        ]);

        $response->assertSessionHasErrors(['prompt_type']);
    }

    /** @test */
    public function it_validates_custom_prompt_length()
    {
        $jobId = Str::uuid();
        AnalysisJob::create([
            'job_id' => $jobId,
            'status' => AnalysisJob::STATUS_COMPLETED,
            'name' => 'Test Job',
            'total_texts' => 1,
            'processed_texts' => 1,
            'requested_models' => ['claude-sonnet-4']
        ]);

        // Try with too long custom prompt
        $response = $this->post(route('analysis.repeat'), [
            'reference_job_id' => $jobId,
            'prompt_type' => 'custom',
            'custom_prompt' => str_repeat('a', 10001) // Over 10000 char limit
        ]);

        $response->assertSessionHasErrors(['custom_prompt']);
    }

    /** @test */
    public function it_extracts_models_from_legacy_text_analyses_when_no_requested_models()
    {
        // Create job without requested_models (legacy format)
        $originalJobId = Str::uuid();
        $originalJob = AnalysisJob::create([
            'job_id' => $originalJobId,
            'status' => AnalysisJob::STATUS_COMPLETED,
            'name' => 'Legacy Analysis',
            'total_texts' => 1,
            'processed_texts' => 1,
            'requested_models' => null // No requested models stored
        ]);

        // Create text analysis with legacy model annotations
        $textAnalysis = TextAnalysis::factory()->create([
            'job_id' => $originalJobId,
            'text_id' => 'text-1',
            'content' => 'Test content',
            'claude_annotations' => ['some' => 'annotation'],
            'gpt_annotations' => ['other' => 'annotation'],
        ]);

        // Repeat the analysis
        $response = $this->post(route('analysis.repeat'), [
            'reference_job_id' => $originalJobId,
            'prompt_type' => 'standard',
            'name' => 'Repeated Legacy Analysis'
        ]);

        $response->assertRedirect();
        $response->assertSessionHas('success');

        // Assert new job was created with extracted models
        $newJob = AnalysisJob::where('reference_analysis_id', $originalJobId)->first();
        $this->assertNotNull($newJob);
        $this->assertNotEmpty($newJob->requested_models);
        
        // Should contain models based on which annotations exist
        $requestedModels = $newJob->requested_models;
        $this->assertIsArray($requestedModels);
    }
}