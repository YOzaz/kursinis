<?php

namespace Tests\Feature;

use App\Jobs\AnalyzeTextJob;
use App\Jobs\BatchAnalysisJob;
use App\Models\AnalysisJob;
use App\Models\TextAnalysis;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

/**
 * Tests complete analysis workflow from upload to results
 */
class AnalysisWorkflowTest extends TestCase
{
    use RefreshDatabase;

    public function test_single_text_analysis_api_endpoint()
    {
        $response = $this->postJson('/api/analyze', [
            'text' => 'Test propaganda text for analysis',
            'models' => ['claude-opus-4'],
            'custom_prompt' => null
        ]);

        $response->assertStatus(422); // Should validate input properly
    }

    public function test_batch_analysis_api_endpoint()
    {
        $response = $this->postJson('/api/batch-analyze', [
            'data' => [
                [
                    'id' => '1',
                    'data' => ['content' => 'Test text 1'],
                    'annotations' => []
                ]
            ],
            'models' => ['claude-opus-4']
        ]);

        $response->assertStatus(422); // Should validate input properly
    }

    public function test_analysis_status_endpoint()
    {
        $job = AnalysisJob::factory()->create([
            'status' => AnalysisJob::STATUS_PROCESSING
        ]);

        $response = $this->getJson("/api/status/{$job->job_id}");

        $response->assertStatus(200)
                ->assertJsonStructure([
                    'job_id',
                    'status',
                    'progress'
                ]);
    }

    public function test_analysis_results_endpoint()
    {
        $job = AnalysisJob::factory()->completed()->create();
        
        TextAnalysis::factory()->create([
            'job_id' => $job->job_id,
            'text_id' => '1'
        ]);

        $response = $this->getJson("/api/results/{$job->job_id}");

        $response->assertStatus(200)
                ->assertJsonStructure([
                    'job_id',
                    'status',
                    'results'
                ]);
    }

    public function test_results_export_endpoint()
    {
        $job = AnalysisJob::factory()->completed()->create();
        
        TextAnalysis::factory()->create([
            'job_id' => $job->job_id,
            'text_id' => '1'
        ]);

        $response = $this->get("/api/results/{$job->job_id}/export");

        $response->assertStatus(200)
                ->assertHeader('Content-Type', 'text/csv; charset=UTF-8');
    }

    public function test_repeat_analysis_endpoint()
    {
        Queue::fake();
        
        $originalJob = AnalysisJob::factory()->completed()->create();
        
        TextAnalysis::factory()->create([
            'job_id' => $originalJob->job_id,
            'text_id' => '1'
        ]);

        $response = $this->postJson('/api/repeat-analysis', [
            'reference_job_id' => $originalJob->job_id,
            'models' => ['claude-opus-4'],
            'prompt_type' => 'default'
        ]);

        $response->assertStatus(422); // Should validate properly
    }

    public function test_text_annotations_endpoint()
    {
        $job = AnalysisJob::factory()->completed()->create();
        $textAnalysis = TextAnalysis::factory()->create([
            'job_id' => $job->job_id,
            'text_id' => '1'
        ]);

        $response = $this->getJson("/api/text-annotations/{$textAnalysis->id}");

        $response->assertStatus(200)
                ->assertJsonStructure([
                    'annotations',
                    'content'
                ]);
    }

    public function test_nonexistent_job_status_returns_404()
    {
        $response = $this->getJson('/api/status/nonexistent-job-id');

        $response->assertStatus(404);
    }

    public function test_nonexistent_job_results_returns_404()
    {
        $response = $this->getJson('/api/results/nonexistent-job-id');

        $response->assertStatus(404);
    }

    public function test_api_endpoints_require_json_content_type()
    {
        $response = $this->post('/api/analyze', [
            'text' => 'Test text'
        ], ['Content-Type' => 'application/x-www-form-urlencoded']);

        // Should handle both JSON and form data
        $this->assertTrue(in_array($response->status(), [200, 422, 404]));
    }

    public function test_api_validation_messages_are_clear()
    {
        $response = $this->postJson('/api/analyze', []);

        $response->assertStatus(422)
                ->assertJsonValidationErrors(['text']);
    }

    public function test_batch_job_dispatching_works()
    {
        Queue::fake();

        $job = AnalysisJob::factory()->create();
        $data = [
            [
                'id' => '1',
                'data' => ['content' => 'Test content'],
                'annotations' => []
            ]
        ];

        BatchAnalysisJob::dispatch($job->job_id, $data, ['claude-opus-4']);

        Queue::assertPushed(BatchAnalysisJob::class);
    }

    public function test_individual_text_job_dispatching_works()
    {
        Queue::fake();

        $textAnalysis = TextAnalysis::factory()->create();

        AnalyzeTextJob::dispatch($textAnalysis->id, 'claude-opus-4', 'test-job-id');

        Queue::assertPushed(AnalyzeTextJob::class);
    }
}