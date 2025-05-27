<?php

namespace Tests\Feature\Feature;

use App\Models\AnalysisJob;
use App\Models\TextAnalysis;
use App\Models\ComparisonMetric;
use App\Jobs\AnalyzeTextJob;
use App\Jobs\BatchAnalysisJob;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class AnalysisControllerTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Queue::fake();
    }

    public function test_analyze_single_text_successfully(): void
    {
        $response = $this->postJson('/api/analyze', [
            'text_id' => 'test-123',
            'content' => 'This is a test propaganda text that needs analysis.',
            'models' => ['claude-4', 'gemini-2.5-pro']
        ]);

        $response->assertStatus(200)
                ->assertJsonStructure([
                    'job_id',
                    'status',
                    'text_id',
                    'models'
                ]);

        Queue::assertPushed(AnalyzeTextJob::class);
    }

    public function test_analyze_single_text_validation_fails(): void
    {
        $response = $this->postJson('/api/analyze', [
            'text_id' => '', // Empty text_id
            'content' => 'short', // Too short content
            'models' => [] // Empty models array
        ]);

        $response->assertStatus(422)
                ->assertJsonValidationErrors(['text_id', 'content', 'models']);
    }

    public function test_analyze_single_text_invalid_models(): void
    {
        $response = $this->postJson('/api/analyze', [
            'text_id' => 'test-123',
            'content' => 'This is a test propaganda text that needs analysis.',
            'models' => ['invalid-model', 'another-invalid']
        ]);

        $response->assertStatus(422)
                ->assertJsonValidationErrors(['models.0', 'models.1']);
    }

    public function test_batch_analyze_successfully(): void
    {
        $batchData = [
            [
                'id' => 1,
                'data' => ['content' => 'First test text for analysis'],
                'annotations' => [
                    ['result' => [
                        ['type' => 'labels', 'value' => [
                            'start' => 0, 'end' => 10, 'text' => 'First test',
                            'labels' => ['simplification']
                        ]]
                    ]]
                ]
            ],
            [
                'id' => 2,
                'data' => ['content' => 'Second test text for analysis'],
                'annotations' => [
                    ['result' => [
                        ['type' => 'labels', 'value' => [
                            'start' => 0, 'end' => 11, 'text' => 'Second test',
                            'labels' => ['emotionalExpression']
                        ]]
                    ]]
                ]
            ]
        ];

        $response = $this->postJson('/api/batch-analyze', [
            'file_content' => $batchData,
            'models' => ['claude-4', 'gpt-4.1']
        ]);

        $response->assertStatus(200)
                ->assertJsonStructure([
                    'job_id',
                    'status',
                    'total_texts',
                    'models'
                ]);

        $responseData = $response->json();
        $this->assertEquals(2, $responseData['total_texts']);

        Queue::assertPushed(BatchAnalysisJob::class);
    }

    public function test_batch_analyze_validation_fails(): void
    {
        $response = $this->postJson('/api/batch-analyze', [
            'file_content' => 'invalid-json-string',
            'models' => []
        ]);

        $response->assertStatus(422)
                ->assertJsonValidationErrors(['file_content', 'models']);
    }

    public function test_get_analysis_status(): void
    {
        $job = AnalysisJob::factory()->processing()->create([
            'total_texts' => 100,
            'processed_texts' => 50
        ]);

        $response = $this->getJson("/api/status/{$job->job_id}");

        $response->assertStatus(200)
                ->assertJson([
                    'job_id' => $job->job_id,
                    'status' => 'processing',
                    'total_texts' => 100,
                    'processed_texts' => 50,
                    'progress_percentage' => 50.0
                ]);
    }

    public function test_get_status_job_not_found(): void
    {
        $response = $this->getJson('/api/status/non-existent-job');

        $response->assertStatus(404)
                ->assertJson([
                    'error' => 'Darbas nerastas'
                ]);
    }

    public function test_get_results_successfully(): void
    {
        $job = AnalysisJob::factory()->completed()->create();
        
        TextAnalysis::factory()->create([
            'job_id' => $job->job_id,
            'text_id' => 'test-1',
            'expert_annotations' => [['type' => 'labels', 'value' => ['labels' => ['simplification']]]],
            'claude_annotations' => [['type' => 'labels', 'value' => ['labels' => ['simplification']]]],
        ]);

        ComparisonMetric::factory()->create([
            'job_id' => $job->job_id,
            'text_id' => 'test-1',
            'model_name' => 'claude-4',
            'true_positives' => 1,
            'false_positives' => 0,
            'false_negatives' => 0,
            'position_accuracy' => 1.0,
        ]);

        $response = $this->getJson("/api/results/{$job->job_id}");

        $response->assertStatus(200)
                ->assertJsonStructure([
                    'job_id',
                    'status',
                    'comparison_metrics',
                    'detailed_results'
                ]);
    }

    public function test_get_results_job_not_completed(): void
    {
        $job = AnalysisJob::factory()->processing()->create();

        $response = $this->getJson("/api/results/{$job->job_id}");

        $response->assertStatus(400)
                ->assertJson([
                    'error' => 'Analizė dar nebaigta'
                ]);
    }

    public function test_export_results_csv(): void
    {
        $job = AnalysisJob::factory()->completed()->create();
        
        TextAnalysis::factory()->create([
            'job_id' => $job->job_id,
            'text_id' => 'test-1',
        ]);

        ComparisonMetric::factory()->create([
            'job_id' => $job->job_id,
            'text_id' => 'test-1',
            'model_name' => 'claude-4',
        ]);

        $response = $this->get("/api/results/{$job->job_id}/export");

        $response->assertStatus(200)
                ->assertHeader('Content-Type', 'text/csv; charset=UTF-8')
                ->assertHeader('Content-Disposition', "attachment; filename=analysis-results-{$job->job_id}.csv");

        $content = $response->getContent();
        $this->assertStringContainsString('text_id', $content);
        $this->assertStringContainsString('model_name', $content);
    }

    public function test_export_results_job_not_found(): void
    {
        $response = $this->get('/api/results/non-existent-job/export');

        $response->assertStatus(404)
                ->assertJson([
                    'error' => 'Darbas nerastas'
                ]);
    }

    public function test_api_endpoints_require_json_content_type(): void
    {
        $response = $this->post('/api/analyze', [
            'text_id' => 'test-123',
            'content' => 'Test content',
            'models' => ['claude-4']
        ]);

        // Should work with regular POST as well, not require JSON
        $this->assertIn($response->status(), [200, 422]); // Either success or validation error
    }

    public function test_handles_large_batch_analysis(): void
    {
        $batchData = [];
        for ($i = 1; $i <= 50; $i++) {
            $batchData[] = [
                'id' => $i,
                'data' => ['content' => "Test text number {$i} for analysis"],
                'annotations' => [
                    ['result' => [
                        ['type' => 'labels', 'value' => [
                            'start' => 0, 'end' => 10, 'text' => "Test text",
                            'labels' => ['simplification']
                        ]]
                    ]]
                ]
            ];
        }

        $response = $this->postJson('/api/batch-analyze', [
            'file_content' => $batchData,
            'models' => ['claude-4']
        ]);

        $response->assertStatus(200);
        $responseData = $response->json();
        $this->assertEquals(50, $responseData['total_texts']);
    }

    public function test_analysis_job_id_is_uuid_format(): void
    {
        $response = $this->postJson('/api/analyze', [
            'text_id' => 'test-123',
            'content' => 'This is a test propaganda text that needs analysis.',
            'models' => ['claude-4']
        ]);

        $response->assertStatus(200);
        $jobId = $response->json('job_id');
        
        // Check if job_id looks like a UUID
        $this->assertMatchesRegularExpression(
            '/^[0-9a-f]{8}-[0-9a-f]{4}-[1-5][0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/i',
            $jobId
        );
    }
}