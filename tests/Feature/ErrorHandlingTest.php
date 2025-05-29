<?php

namespace Tests\Feature;

use App\Models\AnalysisJob;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class ErrorHandlingTest extends TestCase
{
    use RefreshDatabase;

    public function test_analysis_controller_handles_invalid_job_id()
    {
        $this->withSession(['authenticated' => true, 'username' => 'admin']);

        $response = $this->getJson('/api/status/invalid-job-id');

        $response->assertStatus(404);
    }

    public function test_upload_rejects_invalid_file_types()
    {
        $this->withSession(['authenticated' => true, 'username' => 'admin']);
        
        Storage::fake('local');
        
        $file = UploadedFile::fake()->create('test.exe', 1024);

        $response = $this->post('/upload', [
            'json_file' => $file,
            'name' => 'Test Analysis',
            'models' => ['claude-opus-4'],
        ]);

        $response->assertStatus(302);
        $response->assertSessionHasErrors();
    }

    public function test_upload_rejects_oversized_files()
    {
        $this->withSession(['authenticated' => true, 'username' => 'admin']);
        
        Storage::fake('local');
        
        // Create a file larger than 10MB (assuming that's the limit)
        $file = UploadedFile::fake()->create('large.txt', 12 * 1024); // 12MB

        $response = $this->post('/upload', [
            'json_file' => $file,
            'name' => 'Test Analysis',
            'models' => ['claude-opus-4'],
        ]);

        $response->assertStatus(302);
        $response->assertSessionHasErrors();
    }

    public function test_batch_analysis_handles_malformed_json()
    {
        $response = $this->postJson('/api/batch-analyze', [
            'data' => 'invalid json string',
            'models' => ['claude-opus-4']
        ]);

        $response->assertStatus(422)
                ->assertJsonStructure([
                    'message',
                    'errors'
                ]);
    }

    public function test_single_analysis_handles_empty_text()
    {
        $response = $this->postJson('/api/analyze', [
            'text' => '',
            'models' => ['claude-opus-4']
        ]);

        $response->assertStatus(422)
                ->assertJsonStructure([
                    'message',
                    'errors'
                ]);
    }

    public function test_progress_page_handles_invalid_job_id()
    {
        $this->withSession(['authenticated' => true, 'username' => 'admin']);

        $response = $this->get('/progress/invalid-job-id');

        $response->assertStatus(302)
                ->assertSessionHasErrors();
    }

    public function test_analysis_results_handles_nonexistent_job()
    {
        $response = $this->getJson('/api/results/nonexistent-job');

        $response->assertStatus(404);
    }

    public function test_repeat_analysis_handles_invalid_job()
    {
        $response = $this->postJson('/api/repeat-analysis', [
            'reference_analysis_id' => 'invalid-job',
            'name' => 'Test Repeat Analysis',
            'models' => ['claude-opus-4']
        ]);

        $response->assertStatus(422)
                ->assertJsonStructure([
                    'message',
                    'errors'
                ]);
    }

    public function test_file_upload_handles_corrupted_files()
    {
        $this->withSession(['authenticated' => true, 'username' => 'admin']);
        
        Storage::fake('local');
        
        // Create a file with invalid content
        $file = UploadedFile::fake()->createWithContent('invalid.txt', "\x00\x01\x02invalid binary");

        $response = $this->post('/upload', [
            'json_file' => $file,
            'name' => 'Test Analysis',
            'models' => ['claude-opus-4'],
        ]);

        // Should handle gracefully, either accepting or rejecting with clear error
        $this->assertTrue(
            $response->isRedirect() || $response->status() === 422,
            'Should handle corrupted files gracefully'
        );
    }

    public function test_analysis_status_handles_concurrent_requests()
    {
        $job = AnalysisJob::factory()->processing()->create();

        // Simulate multiple concurrent status requests
        $responses = [];
        for ($i = 0; $i < 5; $i++) {
            $responses[] = $this->getJson("/api/status/{$job->job_id}");
        }

        // All requests should succeed
        foreach ($responses as $response) {
            $response->assertStatus(200);
        }
    }
}