<?php

namespace Tests\Feature;

use App\Models\AnalysisJob;
use App\Models\TextAnalysis;
use App\Models\ComparisonMetric;
use App\Jobs\AnalyzeTextJob;
use App\Jobs\BatchAnalysisJob;
use App\Jobs\BatchAnalysisJobV4;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Str;
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
            'models' => ['claude-opus-4', 'gemini-2.5-pro']
        ]);

        $response->assertStatus(200)
                ->assertJsonStructure([
                    'job_id',
                    'status',
                    'text_id'
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

        $response->assertStatus(422);
    }

    public function test_analyze_single_text_invalid_models(): void
    {
        $response = $this->postJson('/api/analyze', [
            'text_id' => 'test-123',
            'content' => 'This is a test propaganda text that needs analysis.',
            'models' => ['invalid-model', 'another-invalid']
        ]);

        $response->assertStatus(422);
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
            'models' => ['claude-opus-4', 'gpt-4.1']
        ]);

        $response->assertStatus(200)
                ->assertJsonStructure([
                    'job_id',
                    'status',
                    'total_texts'
                ]);

        $responseData = $response->json();
        $this->assertEquals(2, $responseData['total_texts']);

        Queue::assertPushed(BatchAnalysisJobV4::class);
    }

    public function test_batch_analyze_validation_fails(): void
    {
        $response = $this->postJson('/api/batch-analyze', [
            'file_content' => 'invalid-json-string',
            'models' => []
        ]);

        $response->assertStatus(422);
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
                    'progress' => 50.0
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
            'model_name' => 'claude-opus-4',
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

        $response->assertStatus(200)
                ->assertJsonStructure([
                    'job_id',
                    'status',
                    'progress'
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
            'model_name' => 'claude-opus-4',
        ]);

        $response = $this->get("/api/results/{$job->job_id}/export");

        $response->assertStatus(200)
                ->assertHeader('Content-Type', 'text/csv; charset=UTF-8');

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
            'models' => ['claude-opus-4']
        ]);

        // Should work with regular POST as well, not require JSON
        $this->assertContains($response->status(), [200, 422]); // Either success or validation error
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
            'models' => ['claude-opus-4']
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
            'models' => ['claude-opus-4']
        ]);

        $response->assertStatus(200);
        $jobId = $response->json('job_id');
        
        // Check if job_id looks like a UUID
        $this->assertMatchesRegularExpression(
            '/^[0-9a-f]{8}-[0-9a-f]{4}-[1-5][0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/i',
            $jobId
        );
    }

    public function test_analyze_single_text_with_custom_prompt(): void
    {
        $response = $this->postJson('/api/analyze', [
            'text_id' => 'test-123',
            'content' => 'This is a test propaganda text that needs analysis.',
            'models' => ['claude-opus-4'],
            'custom_prompt' => 'Custom analysis prompt for this specific test',
            'name' => 'Test Analysis with Custom Prompt',
            'description' => 'Testing custom prompt functionality'
        ]);

        $response->assertStatus(200)
                ->assertJsonStructure([
                    'job_id',
                    'status',
                    'text_id'
                ]);

        // Verify the job was created with custom prompt
        $jobId = $response->json('job_id');
        $job = AnalysisJob::where('job_id', $jobId)->first();
        
        $this->assertNotNull($job);
        $this->assertEquals('Custom analysis prompt for this specific test', $job->custom_prompt);
        $this->assertEquals('Test Analysis with Custom Prompt', $job->name);
        $this->assertEquals('Testing custom prompt functionality', $job->description);
        $this->assertTrue($job->usesCustomPrompt());
    }

    public function test_repeat_analysis_successfully(): void
    {
        // Create original analysis
        $originalJob = AnalysisJob::factory()->completed()->create([
            'name' => 'Original Analysis'
        ]);
        
        TextAnalysis::factory()->create([
            'job_id' => $originalJob->job_id,
            'text_id' => 'test-1',
            'content' => 'Original analysis text content',
        ]);

        $response = $this->postJson('/api/repeat-analysis', [
            'reference_analysis_id' => $originalJob->job_id,
            'models' => ['claude-opus-4', 'gemini-2.5-pro'],
            'custom_prompt' => 'New custom prompt for repeated analysis',
            'name' => 'Repeated Analysis',
            'description' => 'This is a repeated analysis with new prompt'
        ]);

        $response->assertStatus(200)
                ->assertJsonStructure([
                    'job_id',
                    'status',
                    'reference_analysis_id',
                    'total_texts'
                ]);

        $responseData = $response->json();
        $this->assertEquals($originalJob->job_id, $responseData['reference_analysis_id']);
        $this->assertEquals(1, $responseData['total_texts']);

        // Verify the new job was created correctly
        $newJob = AnalysisJob::where('job_id', $responseData['job_id'])->first();
        $this->assertNotNull($newJob);
        $this->assertEquals($originalJob->job_id, $newJob->reference_analysis_id);
        $this->assertEquals('New custom prompt for repeated analysis', $newJob->custom_prompt);
        $this->assertEquals('Repeated Analysis', $newJob->name);

        Queue::assertPushed(AnalyzeTextJob::class);
    }

    public function test_repeat_analysis_validation_fails(): void
    {
        $response = $this->postJson('/api/repeat-analysis', [
            'reference_analysis_id' => 'non-existent-id',
            'models' => [],
            'name' => '' // Required field is empty
        ]);

        $response->assertStatus(422);
    }

    public function test_repeat_analysis_reference_not_completed(): void
    {
        $originalJob = AnalysisJob::factory()->processing()->create();

        $response = $this->postJson('/api/repeat-analysis', [
            'reference_analysis_id' => $originalJob->job_id,
            'models' => ['claude-opus-4'],
            'name' => 'Repeated Analysis'
        ]);

        $response->assertStatus(400)
                ->assertJson([
                    'error' => 'Nuorodos analizė dar nebaigta'
                ]);
    }

    public function test_batch_analyze_with_custom_parameters(): void
    {
        $batchData = [
            [
                'id' => 1,
                'data' => ['content' => 'First test text for custom analysis'],
                'annotations' => [
                    ['result' => [
                        ['type' => 'labels', 'value' => [
                            'start' => 0, 'end' => 10, 'text' => 'First test',
                            'labels' => ['simplification']
                        ]]
                    ]]
                ]
            ]
        ];

        $response = $this->postJson('/api/batch-analyze', [
            'file_content' => $batchData,
            'models' => ['claude-opus-4'],
            'custom_prompt' => 'Custom batch analysis prompt',
            'name' => 'Custom Batch Analysis',
            'description' => 'Testing batch analysis with custom parameters'
        ]);

        $response->assertStatus(200);
        
        $jobId = $response->json('job_id');
        $job = AnalysisJob::where('job_id', $jobId)->first();
        
        $this->assertNotNull($job);
        $this->assertEquals('Custom batch analysis prompt', $job->custom_prompt);
        $this->assertEquals('Custom Batch Analysis', $job->name);
        $this->assertEquals('Testing batch analysis with custom parameters', $job->description);
    }

    /**
     * Set up authenticated session for each test.
     */
    protected function authenticateUser(): void
    {
        $this->withSession(['authenticated' => true, 'username' => 'admin']);
    }

    /**
     * Test successful deletion of a cancelled analysis job.
     */
    public function test_delete_cancelled_analysis_successfully(): void
    {
        $this->authenticateUser();
        
        // Create a cancelled analysis job with related data
        $jobId = (string) Str::uuid();
        $job = AnalysisJob::create([
            'job_id' => $jobId,
            'status' => AnalysisJob::STATUS_CANCELLED,
            'name' => 'Test Cancelled Analysis',
            'description' => 'Analysis that was cancelled',
            'total_texts' => 1,
            'processed_texts' => 0
        ]);

        // Create related data
        $textAnalysis = TextAnalysis::factory()->create([
            'job_id' => $job->job_id,
            'text_id' => 'test-delete-1',
            'content' => 'Test content for deletion'
        ]);

        ComparisonMetric::factory()->create([
            'job_id' => $job->job_id,
            'text_id' => 'test-delete-1',
            'model_name' => 'claude-opus-4'
        ]);

        \App\Models\ModelResult::factory()->create([
            'job_id' => $job->job_id,
            'text_id' => 'test-delete-1',
            'model_key' => 'claude-opus-4'
        ]);

        // Verify data exists before deletion
        $this->assertDatabaseHas('analysis_jobs', ['job_id' => $job->job_id]);
        $this->assertDatabaseHas('text_analysis', ['job_id' => $job->job_id]);
        $this->assertDatabaseHas('comparison_metrics', ['job_id' => $job->job_id]);
        $this->assertDatabaseHas('model_results', ['job_id' => $job->job_id]);

        // Perform deletion
        $response = $this->delete('/analysis/delete', [
            'job_id' => $job->job_id
        ]);

        // Assert redirect with success message
        $response->assertRedirect(route('analyses.index'))
                ->assertSessionHas('success', 'Analizė sėkmingai ištrinta.');

        // Verify cascade deletion - all related data should be deleted
        $this->assertDatabaseMissing('analysis_jobs', ['job_id' => $job->job_id]);
        $this->assertDatabaseMissing('text_analysis', ['job_id' => $job->job_id]);
        $this->assertDatabaseMissing('comparison_metrics', ['job_id' => $job->job_id]);
        $this->assertDatabaseMissing('model_results', ['job_id' => $job->job_id]);
    }

    /**
     * Test rejection of deletion for non-cancelled analysis (completed status).
     */
    public function test_delete_completed_analysis_rejected(): void
    {
        $this->authenticateUser();
        
        $jobId = (string) Str::uuid();
        $job = AnalysisJob::create([
            'job_id' => $jobId,
            'status' => AnalysisJob::STATUS_COMPLETED,
            'name' => 'Completed Analysis',
            'total_texts' => 1,
            'processed_texts' => 1
        ]);

        $response = $this->delete('/analysis/delete', [
            'job_id' => $job->job_id
        ]);

        $response->assertRedirect()
                ->assertSessionHas('error', 'Galima ištrinti tik atšauktas analizes.');

        // Verify job still exists
        $this->assertDatabaseHas('analysis_jobs', ['job_id' => $job->job_id]);
    }

    /**
     * Test rejection of deletion for processing analysis.
     */
    public function test_delete_processing_analysis_rejected(): void
    {
        $this->authenticateUser();
        
        $jobId = (string) Str::uuid();
        $job = AnalysisJob::create([
            'job_id' => $jobId,
            'status' => AnalysisJob::STATUS_PROCESSING,
            'name' => 'Processing Analysis',
            'total_texts' => 1,
            'processed_texts' => 0
        ]);

        $response = $this->delete('/analysis/delete', [
            'job_id' => $job->job_id
        ]);

        $response->assertRedirect()
                ->assertSessionHas('error', 'Galima ištrinti tik atšauktas analizes.');

        // Verify job still exists
        $this->assertDatabaseHas('analysis_jobs', ['job_id' => $job->job_id]);
    }

    /**
     * Test rejection of deletion for pending analysis.
     */
    public function test_delete_pending_analysis_rejected(): void
    {
        $this->authenticateUser();
        
        $jobId = (string) Str::uuid();
        $job = AnalysisJob::create([
            'job_id' => $jobId,
            'status' => AnalysisJob::STATUS_PENDING,
            'name' => 'Pending Analysis',
            'total_texts' => 1,
            'processed_texts' => 0
        ]);

        $response = $this->delete('/analysis/delete', [
            'job_id' => $job->job_id
        ]);

        $response->assertRedirect()
                ->assertSessionHas('error', 'Galima ištrinti tik atšauktas analizes.');

        // Verify job still exists
        $this->assertDatabaseHas('analysis_jobs', ['job_id' => $job->job_id]);
    }

    /**
     * Test rejection of deletion for failed analysis.
     */
    public function test_delete_failed_analysis_rejected(): void
    {
        $this->authenticateUser();
        
        $jobId = (string) Str::uuid();
        $job = AnalysisJob::create([
            'job_id' => $jobId,
            'status' => AnalysisJob::STATUS_FAILED,
            'name' => 'Failed Analysis',
            'error_message' => 'Analysis failed due to API error',
            'total_texts' => 1,
            'processed_texts' => 0
        ]);

        $response = $this->delete('/analysis/delete', [
            'job_id' => $job->job_id
        ]);

        $response->assertRedirect()
                ->assertSessionHas('error', 'Galima ištrinti tik atšauktas analizes.');

        // Verify job still exists
        $this->assertDatabaseHas('analysis_jobs', ['job_id' => $job->job_id]);
    }

    /**
     * Test validation error when job_id is missing.
     */
    public function test_delete_analysis_validation_job_id_required(): void
    {
        $this->authenticateUser();
        
        $response = $this->delete('/analysis/delete', []);

        $response->assertSessionHasErrors(['job_id']);
    }

    /**
     * Test validation error when job_id doesn't exist.
     */
    public function test_delete_analysis_validation_job_id_not_exists(): void
    {
        $this->authenticateUser();
        
        $response = $this->delete('/analysis/delete', [
            'job_id' => 'non-existent-job-id'
        ]);

        $response->assertSessionHasErrors(['job_id']);
    }

    /**
     * Test cascade deletion verification with multiple related records.
     */
    public function test_delete_cancelled_analysis_cascade_deletion_multiple_records(): void
    {
        $this->authenticateUser();
        
        $jobId = (string) Str::uuid();
        $job = AnalysisJob::create([
            'job_id' => $jobId,
            'status' => AnalysisJob::STATUS_CANCELLED,
            'name' => 'Test Multiple Records Deletion',
            'total_texts' => 2,
            'processed_texts' => 0
        ]);

        // Create multiple text analyses
        $textAnalysis1 = TextAnalysis::factory()->create([
            'job_id' => $job->job_id,
            'text_id' => 'multi-test-1'
        ]);

        $textAnalysis2 = TextAnalysis::factory()->create([
            'job_id' => $job->job_id,
            'text_id' => 'multi-test-2'
        ]);

        // Create multiple comparison metrics
        ComparisonMetric::factory()->create([
            'job_id' => $job->job_id,
            'text_id' => 'multi-test-1',
            'model_name' => 'claude-opus-4'
        ]);

        ComparisonMetric::factory()->create([
            'job_id' => $job->job_id,
            'text_id' => 'multi-test-1',
            'model_name' => 'gpt-4.1'
        ]);

        ComparisonMetric::factory()->create([
            'job_id' => $job->job_id,
            'text_id' => 'multi-test-2',
            'model_name' => 'claude-opus-4'
        ]);

        // Create multiple model results
        \App\Models\ModelResult::factory()->create([
            'job_id' => $job->job_id,
            'text_id' => 'multi-test-1',
            'model_key' => 'claude-opus-4'
        ]);

        \App\Models\ModelResult::factory()->create([
            'job_id' => $job->job_id,
            'text_id' => 'multi-test-1',
            'model_key' => 'gpt-4.1'
        ]);

        \App\Models\ModelResult::factory()->create([
            'job_id' => $job->job_id,
            'text_id' => 'multi-test-2',
            'model_key' => 'claude-opus-4'
        ]);

        // Verify all records exist before deletion
        $this->assertEquals(1, AnalysisJob::where('job_id', $job->job_id)->count());
        $this->assertEquals(2, TextAnalysis::where('job_id', $job->job_id)->count());
        $this->assertEquals(3, ComparisonMetric::where('job_id', $job->job_id)->count());
        $this->assertEquals(3, \App\Models\ModelResult::where('job_id', $job->job_id)->count());

        // Perform deletion
        $response = $this->delete('/analysis/delete', [
            'job_id' => $job->job_id
        ]);

        $response->assertRedirect(route('analyses.index'))
                ->assertSessionHas('success', 'Analizė sėkmingai ištrinta.');

        // Verify all records are deleted
        $this->assertEquals(0, AnalysisJob::where('job_id', $job->job_id)->count());
        $this->assertEquals(0, TextAnalysis::where('job_id', $job->job_id)->count());
        $this->assertEquals(0, ComparisonMetric::where('job_id', $job->job_id)->count());
        $this->assertEquals(0, \App\Models\ModelResult::where('job_id', $job->job_id)->count());
    }

    /**
     * Test that deleting one analysis doesn't affect other analyses.
     */
    public function test_delete_analysis_doesnt_affect_other_analyses(): void
    {
        $this->authenticateUser();
        
        // Create two analyses
        $jobToDeleteId = (string) Str::uuid();
        $jobToDelete = AnalysisJob::create([
            'job_id' => $jobToDeleteId,
            'status' => AnalysisJob::STATUS_CANCELLED,
            'name' => 'Analysis to Delete',
            'total_texts' => 1,
            'processed_texts' => 0
        ]);

        $jobToKeepId = (string) Str::uuid();
        $jobToKeep = AnalysisJob::create([
            'job_id' => $jobToKeepId,
            'status' => AnalysisJob::STATUS_COMPLETED,
            'name' => 'Analysis to Keep',
            'total_texts' => 1,
            'processed_texts' => 1
        ]);

        // Create related data for both analyses
        TextAnalysis::factory()->create([
            'job_id' => $jobToDelete->job_id,
            'text_id' => 'delete-text-1'
        ]);

        TextAnalysis::factory()->create([
            'job_id' => $jobToKeep->job_id,
            'text_id' => 'keep-text-1'
        ]);

        ComparisonMetric::factory()->create([
            'job_id' => $jobToDelete->job_id,
            'text_id' => 'delete-text-1'
        ]);

        ComparisonMetric::factory()->create([
            'job_id' => $jobToKeep->job_id,
            'text_id' => 'keep-text-1'
        ]);

        // Perform deletion of only the cancelled analysis
        $response = $this->delete('/analysis/delete', [
            'job_id' => $jobToDelete->job_id
        ]);

        $response->assertRedirect(route('analyses.index'))
                ->assertSessionHas('success', 'Analizė sėkmingai ištrinta.');

        // Verify only the target analysis is deleted
        $this->assertDatabaseMissing('analysis_jobs', ['job_id' => $jobToDelete->job_id]);
        $this->assertDatabaseMissing('text_analysis', ['job_id' => $jobToDelete->job_id]);
        $this->assertDatabaseMissing('comparison_metrics', ['job_id' => $jobToDelete->job_id]);

        // Verify the other analysis is preserved
        $this->assertDatabaseHas('analysis_jobs', ['job_id' => $jobToKeep->job_id]);
        $this->assertDatabaseHas('text_analysis', ['job_id' => $jobToKeep->job_id]);
        $this->assertDatabaseHas('comparison_metrics', ['job_id' => $jobToKeep->job_id]);
    }

    /**
     * Test delete functionality handles cases where some related data doesn't exist.
     */
    public function test_delete_cancelled_analysis_with_partial_data(): void
    {
        $this->authenticateUser();
        
        $jobId = (string) Str::uuid();
        $job = AnalysisJob::create([
            'job_id' => $jobId,
            'status' => AnalysisJob::STATUS_CANCELLED,
            'name' => 'Analysis with Partial Data',
            'total_texts' => 1,
            'processed_texts' => 0
        ]);

        // Create only text analysis but no metrics or model results
        TextAnalysis::factory()->create([
            'job_id' => $job->job_id,
            'text_id' => 'partial-test-1'
        ]);

        // No ComparisonMetric or ModelResult records created intentionally

        $response = $this->delete('/analysis/delete', [
            'job_id' => $job->job_id
        ]);

        $response->assertRedirect(route('analyses.index'))
                ->assertSessionHas('success', 'Analizė sėkmingai ištrinta.');

        // Verify deletion succeeded even with partial data
        $this->assertDatabaseMissing('analysis_jobs', ['job_id' => $job->job_id]);
        $this->assertDatabaseMissing('text_analysis', ['job_id' => $job->job_id]);
    }

    /**
     * Test delete functionality with a cancelled analysis that has no related data.
     */
    public function test_delete_cancelled_analysis_with_no_related_data(): void
    {
        $this->authenticateUser();
        
        $jobId = (string) Str::uuid();
        $job = AnalysisJob::create([
            'job_id' => $jobId,
            'status' => AnalysisJob::STATUS_CANCELLED,
            'name' => 'Analysis with No Related Data',
            'total_texts' => 0,
            'processed_texts' => 0
        ]);

        // No related data created

        $response = $this->delete('/analysis/delete', [
            'job_id' => $job->job_id
        ]);

        $response->assertRedirect(route('analyses.index'))
                ->assertSessionHas('success', 'Analizė sėkmingai ištrinta.');

        // Verify deletion succeeded
        $this->assertDatabaseMissing('analysis_jobs', ['job_id' => $job->job_id]);
    }

    /**
     * Test delete functionality without authentication - should redirect to login.
     */
    public function test_delete_analysis_unauthenticated_redirects_to_login(): void
    {
        $jobId = (string) Str::uuid();
        $job = AnalysisJob::create([
            'job_id' => $jobId,
            'status' => AnalysisJob::STATUS_CANCELLED,
            'name' => 'Test Cancelled Analysis',
            'total_texts' => 1,
            'processed_texts' => 0
        ]);

        $response = $this->delete('/analysis/delete', [
            'job_id' => $job->job_id
        ]);

        $response->assertRedirect('/login');

        // Verify job still exists
        $this->assertDatabaseHas('analysis_jobs', ['job_id' => $job->job_id]);
    }
}