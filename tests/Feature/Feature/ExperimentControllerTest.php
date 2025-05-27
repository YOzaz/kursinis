<?php

namespace Tests\Feature\Feature;

use App\Models\Experiment;
use App\Models\ExperimentResult;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;
use Tests\TestCase;

class ExperimentControllerTest extends TestCase
{
    use RefreshDatabase, WithFaker;

    public function test_index_displays_experiments(): void
    {
        $experiments = Experiment::factory()->count(3)->create();

        $response = $this->get(route('experiments.index'));

        $response->assertStatus(200);
        $response->assertViewIs('experiments.index');
        $response->assertViewHas('experiments');
        
        foreach ($experiments as $experiment) {
            $response->assertSee($experiment->name);
        }
    }

    public function test_create_displays_form(): void
    {
        $response = $this->get(route('experiments.create'));

        $response->assertStatus(200);
        $response->assertViewIs('experiments.create');
        $response->assertViewHas('defaultConfig');
        $response->assertSee('RISEN');
        $response->assertSee('Role');
        $response->assertSee('Instructions');
    }

    public function test_store_creates_experiment(): void
    {
        $experimentData = [
            'name' => 'Test Experiment',
            'description' => 'Test Description',
            'risen_config' => [
                'role' => 'Test Role',
                'instructions' => 'Test Instructions',
                'situation' => 'Test Situation',
                'execution' => 'Test Execution',
                'needle' => 'Test Needle',
            ],
        ];

        $response = $this->postJson(route('experiments.store'), $experimentData);

        $response->assertStatus(200);
        $response->assertJsonStructure([
            'id', 'name', 'description', 'custom_prompt', 'risen_config', 'status'
        ]);

        $this->assertDatabaseHas('experiments', [
            'name' => 'Test Experiment',
            'description' => 'Test Description',
            'status' => 'draft',
        ]);

        $experiment = Experiment::where('name', 'Test Experiment')->first();
        $this->assertEquals($experimentData['risen_config'], $experiment->risen_config);
        $this->assertStringContainsString('Test Role', $experiment->custom_prompt);
    }

    public function test_store_validates_required_fields(): void
    {
        $response = $this->postJson(route('experiments.store'), []);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['name', 'risen_config']);
    }

    public function test_store_validates_risen_config_structure(): void
    {
        $invalidData = [
            'name' => 'Test',
            'risen_config' => [
                'role' => 'Test Role',
                // Missing required fields
            ],
        ];

        $response = $this->postJson(route('experiments.store'), $invalidData);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors([
            'risen_config.instructions',
            'risen_config.situation',
            'risen_config.execution',
            'risen_config.needle',
        ]);
    }

    public function test_show_displays_experiment_with_statistics(): void
    {
        $experiment = Experiment::factory()->create();
        ExperimentResult::factory()->count(2)->forExperiment($experiment)->create();

        $response = $this->get(route('experiments.show', $experiment));

        $response->assertStatus(200);
        $response->assertViewIs('experiments.show');
        $response->assertViewHas(['experiment', 'statistics']);
        $response->assertSee($experiment->name);
        $response->assertSee($experiment->description);
    }

    public function test_edit_displays_form_with_experiment_data(): void
    {
        $experiment = Experiment::factory()->create([
            'name' => 'Edit Test',
            'description' => 'Edit Description',
        ]);

        $response = $this->get(route('experiments.edit', $experiment));

        $response->assertStatus(200);
        $response->assertViewIs('experiments.edit');
        $response->assertViewHas('experiment');
        $response->assertSee('Edit Test');
        $response->assertSee('Edit Description');
    }

    public function test_update_modifies_experiment(): void
    {
        $experiment = Experiment::factory()->create();
        
        $updateData = [
            'name' => 'Updated Name',
            'description' => 'Updated Description',
            'risen_config' => [
                'role' => 'Updated Role',
                'instructions' => 'Updated Instructions',
                'situation' => 'Updated Situation',
                'execution' => 'Updated Execution',
                'needle' => 'Updated Needle',
            ],
        ];

        $response = $this->putJson(route('experiments.update', $experiment), $updateData);

        $response->assertStatus(200);
        $response->assertJsonStructure(['id', 'name', 'description']);

        $experiment->refresh();
        $this->assertEquals('Updated Name', $experiment->name);
        $this->assertEquals('Updated Description', $experiment->description);
        $this->assertEquals($updateData['risen_config'], $experiment->risen_config);
        $this->assertStringContainsString('Updated Role', $experiment->custom_prompt);
    }

    public function test_destroy_deletes_experiment(): void
    {
        $experiment = Experiment::factory()->create();

        $response = $this->deleteJson(route('experiments.destroy', $experiment));

        $response->assertStatus(200);
        $response->assertJson(['message' => 'Eksperimentas sėkmingai ištrintas']);
        $this->assertDatabaseMissing('experiments', ['id' => $experiment->id]);
    }

    public function test_preview_prompt_generates_prompt(): void
    {
        $risenConfig = [
            'role' => 'Test Role',
            'instructions' => 'Test Instructions',
            'situation' => 'Test Situation',
            'execution' => 'Test Execution',
            'needle' => 'Test Needle',
        ];

        $response = $this->postJson(route('experiments.preview-prompt'), [
            'risen_config' => $risenConfig,
        ]);

        $response->assertStatus(200);
        $response->assertJsonStructure(['prompt']);
        
        $prompt = $response->json('prompt');
        $this->assertStringContainsString('Test Role', $prompt);
        $this->assertStringContainsString('Test Instructions', $prompt);
        $this->assertStringContainsString('Test Situation', $prompt);
        $this->assertStringContainsString('Test Execution', $prompt);
        $this->assertStringContainsString('Test Needle', $prompt);
    }

    public function test_export_csv_downloads_experiment_results(): void
    {
        $experiment = Experiment::factory()->create(['name' => 'Export Test']);
        ExperimentResult::factory()->count(3)->forExperiment($experiment)->create();

        $response = $this->get(route('experiments.export-csv', $experiment));

        $response->assertStatus(200);
        $response->assertHeader('Content-Type', 'text/csv; charset=UTF-8');
        $response->assertHeader('Content-Disposition');
        
        $content = $response->getContent();
        $this->assertStringContainsString('experiment_name', $content);
        $this->assertStringContainsString('Export Test', $content);
        $this->assertStringContainsString('llm_model', $content);
    }

    public function test_export_stats_csv_downloads_statistics(): void
    {
        $experiment = Experiment::factory()->create(['name' => 'Stats Export Test']);
        ExperimentResult::factory()->count(2)->forExperiment($experiment)->claude()->create();

        $response = $this->get(route('experiments.export-stats-csv', $experiment));

        $response->assertStatus(200);
        $response->assertHeader('Content-Type', 'text/csv; charset=UTF-8');
        
        $content = $response->getContent();
        $this->assertStringContainsString('model', $content);
        $this->assertStringContainsString('avg_precision', $content);
        $this->assertStringContainsString('claude-4', $content);
    }

    public function test_export_json_downloads_complete_data(): void
    {
        $experiment = Experiment::factory()->create(['name' => 'JSON Export Test']);
        ExperimentResult::factory()->count(2)->forExperiment($experiment)->create();

        $response = $this->get(route('experiments.export-json', $experiment));

        $response->assertStatus(200);
        $response->assertHeader('Content-Type', 'application/json; charset=UTF-8');
        
        $data = $response->json();
        $this->assertArrayHasKey('experiment', $data);
        $this->assertArrayHasKey('results', $data);
        $this->assertArrayHasKey('exported_at', $data);
        $this->assertEquals('JSON Export Test', $data['experiment']['name']);
    }

    public function test_experiment_routes_require_valid_experiment(): void
    {
        $response = $this->get(route('experiments.show', 999));
        $response->assertStatus(404);

        $response = $this->putJson(route('experiments.update', 999), []);
        $response->assertStatus(404);

        $response = $this->deleteJson(route('experiments.destroy', 999));
        $response->assertStatus(404);
    }

    public function test_experiment_creation_handles_long_names(): void
    {
        $longName = str_repeat('a', 300); // Longer than typical varchar limit
        
        $response = $this->postJson(route('experiments.store'), [
            'name' => $longName,
            'risen_config' => [
                'role' => 'Test Role',
                'instructions' => 'Test Instructions',
                'situation' => 'Test Situation',
                'execution' => 'Test Execution',
                'needle' => 'Test Needle',
            ],
        ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['name']);
    }

    public function test_shows_export_buttons_only_when_results_exist(): void
    {
        $experimentWithoutResults = Experiment::factory()->create();
        $experimentWithResults = Experiment::factory()->create();
        ExperimentResult::factory()->forExperiment($experimentWithResults)->create();

        $responseWithoutResults = $this->get(route('experiments.show', $experimentWithoutResults));
        $responseWithResults = $this->get(route('experiments.show', $experimentWithResults));

        $responseWithoutResults->assertDontSee('Eksportuoti');
        $responseWithResults->assertSee('Eksportuoti');
    }
}
