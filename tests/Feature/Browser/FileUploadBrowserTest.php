<?php

namespace Tests\Feature\Browser;

use Tests\TestCase;
use App\Models\AnalysisJob;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

/**
 * Browser-style tests for file upload functionality
 * Tests the file upload interface and JavaScript validation
 */
class FileUploadBrowserTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');
    }

    public function test_upload_form_elements_are_present()
    {
        $this->withSession(['authenticated' => true, 'username' => 'admin']);
        
        $response = $this->get('/');
        
        $response->assertStatus(200)
                ->assertSee('Įkelti JSON failą')
                ->assertSee('name="json_file"', false)
                ->assertSee('type="file"', false)
                ->assertSee('accept=".json"', false)
                ->assertSee('required', false);
    }

    public function test_model_selection_checkboxes_are_present()
    {
        $this->withSession(['authenticated' => true, 'username' => 'admin']);
        
        $response = $this->get('/');
        
        $content = $response->getContent();
        
        $this->assertStringContainsString('name="models[]"', $content);
        $this->assertStringContainsString('type="checkbox"', $content);
        $this->assertStringContainsString('claude-opus-4', $content);
        $this->assertStringContainsString('gpt-4.1', $content);
        $this->assertStringContainsString('gemini-2.5-pro', $content);
    }

    public function test_upload_form_has_proper_enctype()
    {
        $this->withSession(['authenticated' => true, 'username' => 'admin']);
        
        $response = $this->get('/');
        
        $response->assertStatus(200)
                ->assertSee('enctype="multipart/form-data"', false);
    }

    public function test_upload_form_validation_javascript()
    {
        $this->withSession(['authenticated' => true, 'username' => 'admin']);
        
        $response = $this->get('/');
        
        $content = $response->getContent();
        
        // Check for JavaScript validation
        $this->assertStringContainsString('validateFile', $content);
        $this->assertStringContainsString('validateModels', $content);
        $this->assertStringContainsString('.json', $content);
    }

    public function test_file_upload_with_valid_json()
    {
        $this->withSession(['authenticated' => true, 'username' => 'admin']);
        
        $jsonData = [
            [
                'id' => 'test-1',
                'data' => ['content' => 'Test propaganda text content'],
                'annotations' => []
            ]
        ];
        
        $file = UploadedFile::fake()->createWithContent(
            'test.json',
            json_encode($jsonData)
        );
        
        $response = $this->post('/upload', [
            'json_file' => $file,
            'models' => ['claude-opus-4'],
            'name' => 'Test Upload'
        ]);
        
        $response->assertRedirect();
        $this->assertDatabaseHas('analysis_jobs', [
            'name' => 'Test Upload'
        ]);
    }

    public function test_file_upload_validates_file_type()
    {
        $this->withSession(['authenticated' => true, 'username' => 'admin']);
        
        $file = UploadedFile::fake()->create('test.txt', 100);
        
        $response = $this->post('/upload', [
            'json_file' => $file,
            'models' => ['claude-opus-4']
        ]);
        
        $response->assertRedirect()
                ->assertSessionHasErrors('json_file');
    }

    public function test_file_upload_validates_models_selection()
    {
        $this->withSession(['authenticated' => true, 'username' => 'admin']);
        
        $jsonData = ['texts' => []];
        $file = UploadedFile::fake()->createWithContent(
            'test.json',
            json_encode($jsonData)
        );
        
        $response = $this->post('/upload', [
            'json_file' => $file
            // No models selected
        ]);
        
        $response->assertRedirect()
                ->assertSessionHasErrors('models');
    }

    public function test_file_upload_validates_file_size()
    {
        $this->withSession(['authenticated' => true, 'username' => 'admin']);
        
        // Create a file larger than 10MB
        $file = UploadedFile::fake()->create('large.json', 12000); // 12MB
        
        $response = $this->post('/upload', [
            'json_file' => $file,
            'models' => ['claude-opus-4']
        ]);
        
        $response->assertRedirect()
                ->assertSessionHasErrors('json_file');
    }

    public function test_upload_form_shows_progress_indicator()
    {
        $this->withSession(['authenticated' => true, 'username' => 'admin']);
        
        $response = $this->get('/');
        
        $content = $response->getContent();
        
        // Check for progress indicator elements
        $this->assertStringContainsString('progress', $content);
        $this->assertStringContainsString('spinner', $content);
        $this->assertStringContainsString('fa-spin', $content);
    }

    public function test_upload_form_has_submit_button()
    {
        $this->withSession(['authenticated' => true, 'username' => 'admin']);
        
        $response = $this->get('/');
        
        $response->assertStatus(200)
                ->assertSee('Pradėti analizę')
                ->assertSee('type="submit"', false);
    }

    public function test_upload_redirects_to_progress_page()
    {
        $this->withSession(['authenticated' => true, 'username' => 'admin']);
        
        $jsonData = [
            [
                'id' => 'test-1',
                'data' => ['content' => 'Test content'],
                'annotations' => []
            ]
        ];
        
        $file = UploadedFile::fake()->createWithContent(
            'test.json',
            json_encode($jsonData)
        );
        
        $response = $this->post('/upload', [
            'json_file' => $file,
            'models' => ['claude-opus-4']
        ]);
        
        $response->assertRedirect();
        
        // Check redirect URL contains progress
        $location = $response->headers->get('Location');
        $this->assertStringContainsString('progress', $location);
    }

    public function test_upload_form_shows_file_format_instructions()
    {
        $this->withSession(['authenticated' => true, 'username' => 'admin']);
        
        $response = $this->get('/');
        
        $response->assertStatus(200)
                ->assertSee('JSON')
                ->assertSee('format')
                ->assertSee('example', false);
    }

    public function test_upload_form_shows_model_descriptions()
    {
        $this->withSession(['authenticated' => true, 'username' => 'admin']);
        
        $response = $this->get('/');
        
        $response->assertStatus(200)
                ->assertSee('Claude')
                ->assertSee('GPT')
                ->assertSee('Gemini')
                ->assertSee('Anthropic')
                ->assertSee('OpenAI')
                ->assertSee('Google');
    }

    public function test_upload_form_has_csrf_protection()
    {
        $this->withSession(['authenticated' => true, 'username' => 'admin']);
        
        $response = $this->get('/');
        
        $content = $response->getContent();
        
        $this->assertStringContainsString('_token', $content);
        $this->assertStringContainsString('name="_token"', $content);
    }

    public function test_upload_form_shows_custom_prompt_option()
    {
        $this->withSession(['authenticated' => true, 'username' => 'admin']);
        
        $response = $this->get('/');
        
        $response->assertStatus(200)
                ->assertSee('custom_prompt')
                ->assertSee('Pasirinktinis prompt')
                ->assertSee('textarea', false);
    }

    public function test_upload_with_custom_prompt()
    {
        $this->withSession(['authenticated' => true, 'username' => 'admin']);
        
        $jsonData = [
            [
                'id' => 'test-1',
                'data' => ['content' => 'Test content'],
                'annotations' => []
            ]
        ];
        
        $file = UploadedFile::fake()->createWithContent(
            'test.json',
            json_encode($jsonData)
        );
        
        $response = $this->post('/upload', [
            'json_file' => $file,
            'models' => ['claude-opus-4'],
            'custom_prompt' => 'Custom analysis prompt'
        ]);
        
        $response->assertRedirect();
        
        $this->assertDatabaseHas('analysis_jobs', [
            'custom_prompt' => 'Custom analysis prompt'
        ]);
    }

    public function test_upload_form_shows_recent_analyses()
    {
        $this->withSession(['authenticated' => true, 'username' => 'admin']);
        
        AnalysisJob::factory()->create([
            'name' => 'Recent Test Analysis',
            'status' => 'completed'
        ]);
        
        $response = $this->get('/');
        
        $response->assertStatus(200)
                ->assertSee('Tekstų analizės paleidimas')
                ->assertSee('Recent Test Analysis');
    }

    public function test_upload_form_responsive_design()
    {
        $this->withSession(['authenticated' => true, 'username' => 'admin']);
        
        $response = $this->get('/');
        
        $content = $response->getContent();
        
        // Check for responsive Bootstrap classes
        $this->assertStringContainsString('col-md-', $content);
        $this->assertStringContainsString('col-lg-', $content);
        $this->assertStringContainsString('container', $content);
        $this->assertStringContainsString('row', $content);
    }

    public function test_upload_form_accessibility_attributes()
    {
        $this->withSession(['authenticated' => true, 'username' => 'admin']);
        
        $response = $this->get('/');
        
        $content = $response->getContent();
        
        // Check for accessibility attributes
        $this->assertStringContainsString('aria-label', $content);
        $this->assertStringContainsString('role=', $content);
        $this->assertStringContainsString('for=', $content);
    }
}