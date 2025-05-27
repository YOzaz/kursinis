<?php

namespace Tests\Feature\Feature;

use App\Models\Experiment;
use App\Models\ExperimentResult;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;
use Tests\TestCase;

class ExperimentBrowserTest extends TestCase
{
    use RefreshDatabase, WithFaker;

    public function test_user_can_navigate_to_experiments_page(): void
    {
        $response = $this->get('/');
        
        $response->assertStatus(200);
        $response->assertSee('Eksperimentai');
        
        // Follow link to experiments
        $experimentsResponse = $this->get(route('experiments.index'));
        $experimentsResponse->assertStatus(200);
        $experimentsResponse->assertSee('Eksperimentai');
    }

    public function test_user_can_create_experiment_workflow(): void
    {
        // Visit experiments page
        $response = $this->get(route('experiments.index'));
        $response->assertStatus(200);
        $response->assertSee('Naujas eksperimentas');

        // Go to create page
        $createResponse = $this->get(route('experiments.create'));
        $createResponse->assertStatus(200);
        $createResponse->assertSee('RISEN');
        $createResponse->assertSee('Role');
        $createResponse->assertSee('Instructions');

        // Create experiment via form submission
        $experimentData = [
            'name' => 'Browser Test Experiment',
            'description' => 'Created via browser test',
            'risen_config' => [
                'role' => 'Test browser role',
                'instructions' => 'Test browser instructions',
                'situation' => 'Test browser situation',
                'execution' => 'Test browser execution',
                'needle' => 'Test browser needle',
            ],
        ];

        $submitResponse = $this->postJson(route('experiments.store'), $experimentData);
        $submitResponse->assertStatus(200);

        // Verify experiment was created
        $this->assertDatabaseHas('experiments', [
            'name' => 'Browser Test Experiment',
        ]);
    }

    public function test_user_can_view_experiment_details(): void
    {
        $experiment = Experiment::factory()->create([
            'name' => 'Test Experiment Details',
            'description' => 'Description for browser test',
        ]);

        ExperimentResult::factory()->count(2)->forExperiment($experiment)->create();

        $response = $this->get(route('experiments.show', $experiment));

        $response->assertStatus(200);
        $response->assertSee('Test Experiment Details');
        $response->assertSee('Description for browser test');
        $response->assertSee('Eksperimento informacija');
        $response->assertSee('Naudotas prompt');
        $response->assertSee('Eksportuoti'); // Should see export button since results exist
    }

    public function test_user_can_edit_experiment(): void
    {
        $experiment = Experiment::factory()->create([
            'name' => 'Original Name',
            'description' => 'Original Description',
        ]);

        // Visit edit page
        $response = $this->get(route('experiments.edit', $experiment));
        $response->assertStatus(200);
        $response->assertSee('Redaguoti eksperimentą');
        $response->assertSee('Original Name');
        $response->assertSee('Original Description');

        // Submit update
        $updateData = [
            'name' => 'Updated Name via Browser',
            'description' => 'Updated via browser test',
            'risen_config' => [
                'role' => 'Updated role',
                'instructions' => 'Updated instructions',
                'situation' => 'Updated situation',
                'execution' => 'Updated execution',
                'needle' => 'Updated needle',
            ],
        ];

        $updateResponse = $this->putJson(route('experiments.update', $experiment), $updateData);
        $updateResponse->assertStatus(200);

        // Verify update
        $experiment->refresh();
        $this->assertEquals('Updated Name via Browser', $experiment->name);
    }

    public function test_user_can_preview_prompt_in_real_time(): void
    {
        $response = $this->get(route('experiments.create'));
        $response->assertStatus(200);

        // Test prompt preview functionality
        $risenConfig = [
            'role' => 'Preview test role',
            'instructions' => 'Preview test instructions',
            'situation' => 'Preview test situation',
            'execution' => 'Preview test execution',
            'needle' => 'Preview test needle',
        ];

        $previewResponse = $this->postJson(route('experiments.preview-prompt'), [
            'risen_config' => $risenConfig,
        ]);

        $previewResponse->assertStatus(200);
        $previewResponse->assertJsonStructure(['prompt']);
        
        $prompt = $previewResponse->json('prompt');
        $this->assertStringContainsString('Preview test role', $prompt);
        $this->assertStringContainsString('Preview test instructions', $prompt);
    }

    public function test_user_can_access_dashboard_and_view_statistics(): void
    {
        // Create test data
        $experiments = Experiment::factory()->count(2)->create();
        ExperimentResult::factory()->count(3)->create();

        $response = $this->get(route('dashboard'));

        $response->assertStatus(200);
        $response->assertSee('Statistikos dashboard');
        $response->assertSee('Eksperimentų');
        $response->assertSee('Analizių');
        $response->assertSee('Modelių našumo palyginimas');
        $response->assertSee('Chart.js'); // Verify charts are loaded
    }

    public function test_user_can_export_experiment_data(): void
    {
        $experiment = Experiment::factory()->create(['name' => 'Export Test']);
        ExperimentResult::factory()->count(2)->forExperiment($experiment)->create();

        // Test CSV export
        $csvResponse = $this->get(route('experiments.export-csv', $experiment));
        $csvResponse->assertStatus(200);
        $csvResponse->assertHeader('Content-Type', 'text/csv; charset=UTF-8');

        // Test JSON export
        $jsonResponse = $this->get(route('experiments.export-json', $experiment));
        $jsonResponse->assertStatus(200);
        $jsonResponse->assertHeader('Content-Type', 'application/json; charset=UTF-8');

        // Test statistics CSV export
        $statsResponse = $this->get(route('experiments.export-stats-csv', $experiment));
        $statsResponse->assertStatus(200);
        $statsResponse->assertHeader('Content-Type', 'text/csv; charset=UTF-8');
    }

    public function test_user_can_upload_csv_for_analysis(): void
    {
        $experiment = Experiment::factory()->create();

        // Test showing experiment page with upload form
        $response = $this->get(route('experiments.show', $experiment));
        $response->assertStatus(200);
        $response->assertSee('Paleisti analizę');
        $response->assertSee('CSV failas');
        $response->assertSee('Pasirinkite modelius');
    }

    public function test_navigation_between_pages_works(): void
    {
        $experiment = Experiment::factory()->create();

        // Start from home
        $homeResponse = $this->get('/');
        $homeResponse->assertStatus(200);

        // Navigate to experiments
        $experimentsResponse = $this->get(route('experiments.index'));
        $experimentsResponse->assertStatus(200);
        $experimentsResponse->assertSee('Eksperimentai');

        // Navigate to dashboard
        $dashboardResponse = $this->get(route('dashboard'));
        $dashboardResponse->assertStatus(200);
        $dashboardResponse->assertSee('Statistikos dashboard');

        // Navigate to specific experiment
        $experimentResponse = $this->get(route('experiments.show', $experiment));
        $experimentResponse->assertStatus(200);
        $experimentResponse->assertSee($experiment->name);

        // Navigate to edit
        $editResponse = $this->get(route('experiments.edit', $experiment));
        $editResponse->assertStatus(200);
        $editResponse->assertSee('Redaguoti eksperimentą');
    }

    public function test_responsive_design_elements_are_present(): void
    {
        $experiment = Experiment::factory()->create();
        ExperimentResult::factory()->count(2)->forExperiment($experiment)->create();

        $response = $this->get(route('experiments.show', $experiment));

        $response->assertStatus(200);
        
        // Check for Bootstrap classes that indicate responsive design
        $response->assertSee('container');
        $response->assertSee('row');
        $response->assertSee('col-');
        $response->assertSee('btn');
        $response->assertSee('card');
        
        // Check for responsive navigation
        $response->assertSee('navbar');
    }

    public function test_javascript_components_are_loaded(): void
    {
        $experiment = Experiment::factory()->create();
        ExperimentResult::factory()->count(2)->forExperiment($experiment)->create();

        // Test experiment show page with charts
        $showResponse = $this->get(route('experiments.show', $experiment));
        $showResponse->assertStatus(200);
        $showResponse->assertSee('Chart.js');
        $showResponse->assertSee('metricsChart');
        $showResponse->assertSee('timeChart');

        // Test dashboard with charts
        $dashboardResponse = $this->get(route('dashboard'));
        $dashboardResponse->assertStatus(200);
        $dashboardResponse->assertSee('Chart.js');
        $dashboardResponse->assertSee('modelPerformanceChart');
        $dashboardResponse->assertSee('executionTimeChart');

        // Test create page with preview functionality
        $createResponse = $this->get(route('experiments.create'));
        $createResponse->assertStatus(200);
        $createResponse->assertSee('previewPrompt');
        $createResponse->assertSee('addEventListener');
    }

    public function test_error_handling_and_validation_messages(): void
    {
        // Test form validation
        $invalidResponse = $this->postJson(route('experiments.store'), []);
        $invalidResponse->assertStatus(422);
        $invalidResponse->assertJsonValidationErrors(['name', 'risen_config']);

        // Test 404 handling
        $notFoundResponse = $this->get(route('experiments.show', 999));
        $notFoundResponse->assertStatus(404);
    }

    public function test_search_and_filter_functionality(): void
    {
        // Create experiments with different statuses
        $draftExperiment = Experiment::factory()->draft()->create(['name' => 'Draft Experiment']);
        $completedExperiment = Experiment::factory()->completed()->create(['name' => 'Completed Experiment']);

        $response = $this->get(route('experiments.index'));
        $response->assertStatus(200);
        
        // Both should be visible on index page
        $response->assertSee('Draft Experiment');
        $response->assertSee('Completed Experiment');
        $response->assertSee('draft');
        $response->assertSee('completed');
    }
}
