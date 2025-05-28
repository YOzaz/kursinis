<?php

namespace Tests\Unit\Controllers;

use App\Http\Controllers\WebController;
use App\Jobs\BatchAnalysisJob;
use App\Models\AnalysisJob;
use App\Services\PromptService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class WebControllerTest extends TestCase
{
    use RefreshDatabase;

    private WebController $controller;

    protected function setUp(): void
    {
        parent::setUp();
        $this->controller = new WebController();
    }

    public function test_index_returns_view_with_recent_jobs()
    {
        $this->withSession(['authenticated' => true, 'username' => 'admin']);
        
        // Create some recent jobs
        AnalysisJob::factory()->create(['created_at' => now()->subMinutes(5)]);
        AnalysisJob::factory()->create(['created_at' => now()->subMinutes(10)]);

        $response = $this->get('/');

        $response->assertStatus(200)
                ->assertViewIs('index')
                ->assertViewHas('recentJobs')
                ->assertViewHas('standardPrompt');
    }

    public function test_upload_validates_required_fields()
    {
        $this->withSession(['authenticated' => true, 'username' => 'admin']);
        
        $response = $this->post('/upload', []);

        $response->assertSessionHasErrors(['json_file', 'models']);
    }

    public function test_upload_validates_file_type()
    {
        $this->withSession(['authenticated' => true, 'username' => 'admin']);
        
        $file = UploadedFile::fake()->create('test.txt', 100);

        $response = $this->post('/upload', [
            'json_file' => $file,
            'models' => ['claude-opus-4']
        ]);

        $response->assertSessionHasErrors(['json_file']);
    }

    public function test_upload_validates_models_array()
    {
        $this->withSession(['authenticated' => true, 'username' => 'admin']);
        
        $jsonContent = json_encode([
            [
                'id' => '1',
                'data' => ['content' => 'Test content'],
                'annotations' => []
            ]
        ]);
        
        $file = UploadedFile::fake()->createWithContent('test.json', $jsonContent);

        $response = $this->post('/upload', [
            'json_file' => $file,
            'models' => []
        ]);

        $response->assertSessionHasErrors(['models']);
    }

    public function test_upload_validates_model_names()
    {
        $this->withSession(['authenticated' => true, 'username' => 'admin']);
        
        $jsonContent = json_encode([
            [
                'id' => '1',
                'data' => ['content' => 'Test content'],
                'annotations' => []
            ]
        ]);
        
        $file = UploadedFile::fake()->createWithContent('test.json', $jsonContent);

        $response = $this->post('/upload', [
            'json_file' => $file,
            'models' => ['invalid-model']
        ]);

        $response->assertSessionHasErrors(['models.*']);
    }

    public function test_upload_validates_json_format()
    {
        $this->withSession(['authenticated' => true, 'username' => 'admin']);
        
        $file = UploadedFile::fake()->createWithContent('test.json', 'invalid json');

        $response = $this->post('/upload', [
            'json_file' => $file,
            'models' => ['claude-opus-4']
        ]);

        $response->assertSessionHasErrors(['json_file']);
    }

    public function test_upload_validates_json_structure()
    {
        $this->withSession(['authenticated' => true, 'username' => 'admin']);
        
        // Invalid structure - missing required fields
        $jsonContent = json_encode([
            [
                'id' => '1'
                // Missing 'data' and 'annotations'
            ]
        ]);
        
        $file = UploadedFile::fake()->createWithContent('test.json', $jsonContent);

        $response = $this->post('/upload', [
            'json_file' => $file,
            'models' => ['claude-opus-4']
        ]);

        $response->assertSessionHasErrors(['json_file']);
    }

    public function test_upload_creates_job_and_dispatches_batch_analysis()
    {
        Queue::fake();
        $this->withSession(['authenticated' => true, 'username' => 'admin']);

        $jsonContent = json_encode([
            [
                'id' => '1',
                'data' => ['content' => 'Test content 1'],
                'annotations' => []
            ],
            [
                'id' => '2',
                'data' => ['content' => 'Test content 2'],
                'annotations' => []
            ]
        ]);
        
        $file = UploadedFile::fake()->createWithContent('test.json', $jsonContent);

        $response = $this->post('/upload', [
            'json_file' => $file,
            'models' => ['claude-opus-4'],
            'name' => 'Test Analysis',
            'description' => 'Test description'
        ]);

        // Check job was created
        $this->assertDatabaseCount('analysis_jobs', 1);
        
        $job = AnalysisJob::first();
        $this->assertEquals('Test Analysis', $job->name);
        $this->assertEquals('Test description', $job->description);
        $this->assertEquals(2, $job->total_texts);
        $this->assertEquals(AnalysisJob::STATUS_PENDING, $job->status);

        // Check batch job was dispatched
        Queue::assertPushed(BatchAnalysisJob::class);

        // Check redirect
        $response->assertRedirect(route('progress', ['jobId' => $job->job_id]))
                ->assertSessionHas('success');
    }

    public function test_upload_handles_custom_prompt()
    {
        Queue::fake();
        $this->withSession(['authenticated' => true, 'username' => 'admin']);

        $jsonContent = json_encode([
            [
                'id' => '1',
                'data' => ['content' => 'Test content'],
                'annotations' => []
            ]
        ]);
        
        $file = UploadedFile::fake()->createWithContent('test.json', $jsonContent);

        $response = $this->post('/upload', [
            'json_file' => $file,
            'models' => ['claude-opus-4'],
            'custom_prompt' => 'Custom analysis prompt'
        ]);

        $job = AnalysisJob::first();
        $this->assertEquals('Custom analysis prompt', $job->custom_prompt);

        $response->assertRedirect();
    }

    public function test_upload_handles_custom_prompt_parts()
    {
        Queue::fake();
        $this->withSession(['authenticated' => true, 'username' => 'admin']);

        $jsonContent = json_encode([
            [
                'id' => '1',
                'data' => ['content' => 'Test content'],
                'annotations' => []
            ]
        ]);
        
        $file = UploadedFile::fake()->createWithContent('test.json', $jsonContent);

        $customParts = json_encode([
            'role' => 'Test role',
            'instructions' => 'Test instructions',
            'situation' => 'Test situation',
            'execution' => 'Test execution',
            'needle' => 'Test needle'
        ]);

        $response = $this->post('/upload', [
            'json_file' => $file,
            'models' => ['claude-opus-4'],
            'custom_prompt_parts' => $customParts
        ]);

        $job = AnalysisJob::first();
        $this->assertNotNull($job->custom_prompt);

        $response->assertRedirect();
    }

    public function test_progress_returns_view_for_existing_job()
    {
        $this->withSession(['authenticated' => true, 'username' => 'admin']);
        
        $job = AnalysisJob::factory()->create();

        $response = $this->get("/progress/{$job->job_id}");

        $response->assertStatus(200)
                ->assertViewIs('progress')
                ->assertViewHas('job');
    }

    public function test_progress_redirects_for_nonexistent_job()
    {
        $this->withSession(['authenticated' => true, 'username' => 'admin']);
        
        $response = $this->get('/progress/nonexistent-job-id');

        $response->assertRedirect(route('home'))
                ->assertSessionHasErrors();
    }

    public function test_validate_json_structure_returns_true_for_valid_data()
    {
        $validData = [
            [
                'id' => '1',
                'data' => ['content' => 'Test content'],
                'annotations' => []
            ]
        ];

        $reflection = new \ReflectionClass($this->controller);
        $method = $reflection->getMethod('validateJsonStructure');
        $method->setAccessible(true);

        $result = $method->invoke($this->controller, $validData);

        $this->assertTrue($result);
    }

    public function test_validate_json_structure_returns_false_for_invalid_data()
    {
        $invalidData = [
            [
                'id' => '1'
                // Missing required fields
            ]
        ];

        $reflection = new \ReflectionClass($this->controller);
        $method = $reflection->getMethod('validateJsonStructure');
        $method->setAccessible(true);

        $result = $method->invoke($this->controller, $invalidData);

        $this->assertFalse($result);
    }

    public function test_validate_json_structure_handles_empty_array()
    {
        // Test that the method handles empty array input  
        $reflection = new \ReflectionClass($this->controller);
        $method = $reflection->getMethod('validateJsonStructure');
        $method->setAccessible(true);

        // Empty array returns true (valid empty dataset)
        $result = $method->invoke($this->controller, []);
        $this->assertTrue($result);
    }

    public function test_upload_handles_file_size_limit()
    {
        $this->withSession(['authenticated' => true, 'username' => 'admin']);
        
        // Create a file larger than 10MB (10240KB)
        $file = UploadedFile::fake()->create('test.json', 11000);

        $response = $this->post('/upload', [
            'json_file' => $file,
            'models' => ['claude-opus-4']
        ]);

        $response->assertSessionHasErrors(['json_file']);
    }

    public function test_upload_sets_default_job_name()
    {
        Queue::fake();
        $this->withSession(['authenticated' => true, 'username' => 'admin']);

        $jsonContent = json_encode([
            [
                'id' => '1',
                'data' => ['content' => 'Test content'],
                'annotations' => []
            ]
        ]);
        
        $file = UploadedFile::fake()->createWithContent('test.json', $jsonContent);

        $response = $this->post('/upload', [
            'json_file' => $file,
            'models' => ['claude-opus-4']
            // No name provided
        ]);

        $job = AnalysisJob::first();
        $this->assertEquals('Batch analizė', $job->name);

        $response->assertRedirect();
    }
}