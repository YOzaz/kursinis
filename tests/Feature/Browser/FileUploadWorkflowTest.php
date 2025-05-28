<?php

namespace Tests\Feature\Browser;

use App\Models\AnalysisJob;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

/**
 * Browser tests for file upload workflow
 */
class FileUploadWorkflowTest extends TestCase
{
    use RefreshDatabase;

    public function test_file_upload_form_is_present()
    {
        $this->withSession(['authenticated' => true, 'username' => 'admin']);
        
        $response = $this->get('/');

        $response->assertStatus(200)
                ->assertSee('name="json_file"', false)
                ->assertSee('type="file"', false)
                ->assertSee('accept=".json"', false);
    }

    public function test_model_selection_checkboxes_are_functional()
    {
        $this->withSession(['authenticated' => true, 'username' => 'admin']);
        
        $response = $this->get('/');

        $response->assertStatus(200)
                ->assertSee('name="models[]"', false)
                ->assertSee('claude-opus-4')
                ->assertSee('gpt-4.1')
                ->assertSee('gemini-2.5-pro');
    }

    public function test_custom_prompt_textarea_is_present()
    {
        $this->withSession(['authenticated' => true, 'username' => 'admin']);
        
        $response = $this->get('/');

        $response->assertStatus(200)
                ->assertSee('name="custom_prompt"', false)
                ->assertSee('textarea', false);
    }

    public function test_upload_form_has_csrf_protection()
    {
        $this->withSession(['authenticated' => true, 'username' => 'admin']);
        
        $response = $this->get('/');

        $response->assertStatus(200)
                ->assertSee('name="_token"', false);
    }

    public function test_file_upload_validation_shows_errors()
    {
        $this->withSession(['authenticated' => true, 'username' => 'admin']);
        
        $response = $this->post('/upload', []);

        $response->assertRedirect('/')
                ->assertSessionHasErrors(['json_file', 'models']);
    }

    public function test_successful_upload_redirects_to_progress()
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
            'name' => 'Test Upload'
        ]);

        $job = AnalysisJob::first();
        
        $response->assertRedirect("/progress/{$job->job_id}")
                ->assertSessionHas('success');
    }

    public function test_progress_page_shows_job_information()
    {
        $this->withSession(['authenticated' => true, 'username' => 'admin']);
        
        $job = AnalysisJob::factory()->create([
            'name' => 'Test Analysis Job',
            'status' => AnalysisJob::STATUS_PROCESSING,
            'total_texts' => 10,
            'processed_texts' => 3
        ]);

        $response = $this->get("/progress/{$job->job_id}");

        $response->assertStatus(200)
                ->assertSee('Test Analysis Job')
                ->assertSee('3')
                ->assertSee('10');
    }

    public function test_progress_page_has_refresh_functionality()
    {
        $this->withSession(['authenticated' => true, 'username' => 'admin']);
        
        $job = AnalysisJob::factory()->create();

        $response = $this->get("/progress/{$job->job_id}");

        $response->assertStatus(200)
                ->assertSee('auto-refresh', false);
    }

    public function test_completed_job_shows_results_link()
    {
        $this->withSession(['authenticated' => true, 'username' => 'admin']);
        
        $job = AnalysisJob::factory()->completed()->create();

        $response = $this->get("/progress/{$job->job_id}");

        $response->assertStatus(200)
                ->assertSee('/analyses/');
    }

    public function test_upload_form_accepts_job_description()
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
            'name' => 'Test Job',
            'description' => 'Test job description'
        ]);

        $job = AnalysisJob::first();
        $this->assertEquals('Test job description', $job->description);
    }

    public function test_prompt_builder_modal_functionality()
    {
        $this->withSession(['authenticated' => true, 'username' => 'admin']);
        
        $response = $this->get('/');

        $response->assertStatus(200)
                ->assertSee('promptBuilderModal', false)
                ->assertSee('showPromptBuilder', false);
    }

    public function test_default_prompt_preview_functionality()
    {
        $this->withSession(['authenticated' => true, 'username' => 'admin']);
        
        $response = $this->get('/');

        $response->assertStatus(200)
                ->assertSee('loadPromptPreview', false)
                ->assertSee('/api/default-prompt');
    }

    public function test_file_drag_and_drop_support()
    {
        $this->withSession(['authenticated' => true, 'username' => 'admin']);
        
        $response = $this->get('/');

        $response->assertStatus(200)
                ->assertSee('dragover', false)
                ->assertSee('drop', false);
    }

    public function test_upload_progress_indicator()
    {
        $this->withSession(['authenticated' => true, 'username' => 'admin']);
        
        $response = $this->get('/');

        $response->assertStatus(200)
                ->assertSee('progress-circle', false);
    }

    public function test_recent_analyses_display()
    {
        $this->withSession(['authenticated' => true, 'username' => 'admin']);
        
        AnalysisJob::factory()->create([
            'name' => 'Recent Analysis',
            'created_at' => now()->subMinutes(5)
        ]);

        $response = $this->get('/');

        $response->assertStatus(200)
                ->assertSee('Recent Analysis');
    }

    public function test_model_configuration_display()
    {
        $this->withSession(['authenticated' => true, 'username' => 'admin']);
        
        $response = $this->get('/');

        $response->assertStatus(200)
                ->assertSee('Anthropic')
                ->assertSee('OpenAI')
                ->assertSee('Google');
    }
}