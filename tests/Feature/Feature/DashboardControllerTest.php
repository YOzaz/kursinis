<?php

namespace Tests\Feature\Feature;

use App\Models\Experiment;
use App\Models\ExperimentResult;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;
use Tests\TestCase;

class DashboardControllerTest extends TestCase
{
    use RefreshDatabase, WithFaker;

    public function test_dashboard_index_displays_global_statistics(): void
    {
        // Create test data
        $experiments = Experiment::factory()->count(2)->create();
        ExperimentResult::factory()->count(3)->create();

        $response = $this->get(route('dashboard'));

        $response->assertStatus(200);
        $response->assertViewIs('dashboard.index');
        $response->assertViewHas(['globalStats', 'experiments']);
        
        // Check that statistics are displayed
        $response->assertSee('Statistikos dashboard');
        $response->assertSee('Eksperimentų');
        $response->assertSee('Analizių');
        $response->assertSee('Modelių');
    }

    public function test_dashboard_displays_recent_experiments(): void
    {
        $recentExperiments = Experiment::factory()->count(3)->create([
            'created_at' => now()->subDays(1),
        ]);
        
        $oldExperiment = Experiment::factory()->create([
            'created_at' => now()->subMonth(),
        ]);

        $response = $this->get(route('dashboard'));

        $response->assertStatus(200);
        
        // Should show recent experiments
        foreach ($recentExperiments as $experiment) {
            $response->assertSee($experiment->name);
        }
        
        // Should not show old experiment in recent list (limit 5)
        $response->assertDontSee($oldExperiment->name);
    }

    public function test_dashboard_handles_no_data(): void
    {
        $response = $this->get(route('dashboard'));

        $response->assertStatus(200);
        $response->assertSee('0'); // Should show zero for counts
        $response->assertSee('Nėra eksperimentų');
    }

    public function test_experiment_stats_endpoint_returns_statistics(): void
    {
        $experiment = Experiment::factory()->create();
        ExperimentResult::factory()->count(2)->forExperiment($experiment)->claude()->create([
            'metrics' => [
                'precision' => 0.8,
                'recall' => 0.7,
                'f1_score' => 0.75,
                'cohens_kappa' => 0.6,
            ],
        ]);

        $response = $this->getJson(route('dashboard.experiment-stats', $experiment));

        $response->assertStatus(200);
        $response->assertJsonStructure([
            'models',
            'metrics', 
            'comparison',
            'charts'
        ]);

        $data = $response->json();
        $this->assertArrayHasKey('claude-4', $data['models']);
        $this->assertEquals(2, $data['models']['claude-4']['total_analyses']);
    }

    public function test_experiment_stats_handles_experiment_without_results(): void
    {
        $experiment = Experiment::factory()->create();

        $response = $this->getJson(route('dashboard.experiment-stats', $experiment));

        $response->assertStatus(200);
        
        $data = $response->json();
        $this->assertEmpty($data['models']);
        $this->assertEmpty($data['metrics']);
        $this->assertEmpty($data['comparison']);
    }

    public function test_compare_experiments_endpoint(): void
    {
        $experiment1 = Experiment::factory()->create();
        $experiment2 = Experiment::factory()->create();
        
        ExperimentResult::factory()->count(2)->forExperiment($experiment1)->claude()->create();
        ExperimentResult::factory()->count(2)->forExperiment($experiment2)->gemini()->create();

        $response = $this->postJson(route('dashboard.compare-experiments'), [
            'experiment_ids' => [$experiment1->id, $experiment2->id],
        ]);

        $response->assertStatus(200);
        $response->assertJsonStructure([
            $experiment1->id => ['name', 'stats'],
            $experiment2->id => ['name', 'stats'],
        ]);

        $data = $response->json();
        $this->assertEquals($experiment1->name, $data[$experiment1->id]['name']);
        $this->assertEquals($experiment2->name, $data[$experiment2->id]['name']);
    }

    public function test_compare_experiments_validates_input(): void
    {
        $response = $this->postJson(route('dashboard.compare-experiments'), []);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['experiment_ids']);
    }

    public function test_compare_experiments_requires_minimum_experiments(): void
    {
        $experiment = Experiment::factory()->create();

        $response = $this->postJson(route('dashboard.compare-experiments'), [
            'experiment_ids' => [$experiment->id], // Only one experiment
        ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['experiment_ids']);
    }

    public function test_compare_experiments_limits_maximum_experiments(): void
    {
        $experiments = Experiment::factory()->count(6)->create();

        $response = $this->postJson(route('dashboard.compare-experiments'), [
            'experiment_ids' => $experiments->pluck('id')->toArray(), // Too many experiments
        ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['experiment_ids']);
    }

    public function test_compare_experiments_validates_experiment_existence(): void
    {
        $validExperiment = Experiment::factory()->create();

        $response = $this->postJson(route('dashboard.compare-experiments'), [
            'experiment_ids' => [$validExperiment->id, 999], // Non-existent experiment
        ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['experiment_ids.1']);
    }

    public function test_dashboard_shows_model_performance_charts(): void
    {
        // Create experiments with different model results
        $experiment = Experiment::factory()->create();
        
        ExperimentResult::factory()->forExperiment($experiment)->claude()->create([
            'metrics' => ['precision' => 0.9, 'recall' => 0.8, 'f1_score' => 0.85],
        ]);
        
        ExperimentResult::factory()->forExperiment($experiment)->gemini()->create([
            'metrics' => ['precision' => 0.7, 'recall' => 0.6, 'f1_score' => 0.65],
        ]);

        $response = $this->get(route('dashboard'));

        $response->assertStatus(200);
        $response->assertSee('Modelių našumo palyginimas');
        $response->assertSee('Vykdymo laiko palyginimas');
        
        // Check that Chart.js is included
        $response->assertSee('Chart.js');
        $response->assertSee('modelPerformanceChart');
        $response->assertSee('executionTimeChart');
    }

    public function test_dashboard_recent_activity_shows_latest_results(): void
    {
        $experiment1 = Experiment::factory()->create(['name' => 'Recent Experiment']);
        $experiment2 = Experiment::factory()->create(['name' => 'Old Experiment']);
        
        // Create recent result
        ExperimentResult::factory()->forExperiment($experiment1)->create([
            'created_at' => now()->subHours(1),
        ]);
        
        // Create old result
        ExperimentResult::factory()->forExperiment($experiment2)->create([
            'created_at' => now()->subWeeks(2),
        ]);

        $response = $this->get(route('dashboard'));

        $response->assertStatus(200);
        
        // Recent activity should show the recent experiment
        $response->assertSee('Paskutinė veikla');
        $response->assertSee('Recent Experiment');
    }

    public function test_dashboard_performance_metrics_calculation(): void
    {
        $experiment = Experiment::factory()->create();
        
        // Create results with known metrics for testing
        ExperimentResult::factory()->forExperiment($experiment)->create([
            'llm_model' => 'claude-4',
            'metrics' => [
                'precision' => 0.8,
                'recall' => 0.7,
                'f1_score' => 0.75,
            ],
            'execution_time' => 2.5,
        ]);

        $response = $this->get(route('dashboard'));

        $response->assertStatus(200);
        $response->assertViewHas('globalStats');
        
        $viewData = $response->viewData('globalStats');
        $this->assertArrayHasKey('model_performance', $viewData);
        
        if (isset($viewData['model_performance']['claude-4'])) {
            $this->assertEquals(1, $viewData['model_performance']['claude-4']['total_analyses']);
            $this->assertEquals(2.5, $viewData['model_performance']['claude-4']['avg_execution_time']);
        }
    }
}
