<?php

namespace Tests\Feature\Feature;

use App\Models\AnalysisJob;
use App\Models\TextAnalysis;
use App\Models\ComparisonMetric;
use App\Models\Experiment;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AnalysesControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_analyses_index_returns_successful_response(): void
    {
        // Create test data
        $analysisJob = AnalysisJob::factory()->create([
            'status' => 'completed'
        ]);

        $response = $this->get(route('analyses.index'));

        $response->assertStatus(200)
                ->assertViewIs('analyses.index')
                ->assertViewHas('analyses');
    }

    public function test_analyses_index_shows_both_standard_and_experiment_analyses(): void
    {
        // Create standard analysis
        $standardAnalysis = AnalysisJob::factory()->create([
            'status' => 'completed',
            'experiment_id' => null
        ]);

        // Create experiment analysis
        $experiment = Experiment::factory()->create();
        $experimentAnalysis = AnalysisJob::factory()->create([
            'status' => 'completed',
            'experiment_id' => $experiment->id
        ]);

        $response = $this->get(route('analyses.index'));

        $response->assertStatus(200)
                ->assertSee('Standartinė analizė')
                ->assertSee('Eksperimentas');
    }

    public function test_analyses_show_returns_successful_response(): void
    {
        $analysisJob = AnalysisJob::factory()->create([
            'status' => 'completed'
        ]);

        // Create some text analyses
        TextAnalysis::factory()->count(3)->create([
            'job_id' => $analysisJob->job_id
        ]);

        $response = $this->get(route('analyses.show', $analysisJob->job_id));

        $response->assertStatus(200)
                ->assertViewIs('analyses.show')
                ->assertViewHas('analysis')
                ->assertViewHas('statistics');
    }

    public function test_analyses_show_with_comparison_metrics(): void
    {
        $analysisJob = AnalysisJob::factory()->create([
            'status' => 'completed'
        ]);

        $textAnalysis = TextAnalysis::factory()->create([
            'job_id' => $analysisJob->job_id
        ]);

        // Create comparison metrics
        ComparisonMetric::factory()->create([
            'job_id' => $analysisJob->job_id,
            'text_id' => $textAnalysis->text_id,
            'model_name' => 'gemini-2.5-pro-preview-05-06',
            'precision' => '0.850',
            'recall' => '0.750',
            'f1_score' => '0.800'
        ]);

        $response = $this->get(route('analyses.show', $analysisJob->job_id));

        $response->assertStatus(200)
                ->assertSee('85.0%') // Should show precision percentage
                ->assertSee('75.0%') // Should show recall percentage
                ->assertSee('80.0%'); // Should show F1 score percentage
    }

    public function test_analyses_show_handles_nonexistent_job(): void
    {
        $response = $this->get(route('analyses.show', 'nonexistent-job-id'));

        $response->assertStatus(404);
    }

    public function test_analyses_show_displays_correct_model_names(): void
    {
        $analysisJob = AnalysisJob::factory()->create([
            'status' => 'completed'
        ]);

        $response = $this->get(route('analyses.show', $analysisJob->job_id));

        $response->assertStatus(200)
                ->assertSee($analysisJob->job_id); // Should show job ID
    }

    public function test_analyses_index_filters_by_status(): void
    {
        // Create analyses with different statuses
        AnalysisJob::factory()->create(['status' => 'completed']);
        AnalysisJob::factory()->create(['status' => 'failed']);
        AnalysisJob::factory()->create(['status' => 'processing']);

        $response = $this->get(route('analyses.index'));

        $response->assertStatus(200)
                ->assertSee('Completed')
                ->assertSee('Failed') 
                ->assertSee('Processing');
    }

    public function test_analyses_show_experiment_link(): void
    {
        $experiment = Experiment::factory()->create();
        $analysisJob = AnalysisJob::factory()->create([
            'status' => 'completed',
            'experiment_id' => $experiment->id
        ]);

        $response = $this->get(route('analyses.show', $analysisJob->job_id));

        $response->assertStatus(200)
                ->assertSee('Peržiūrėti eksperimentą')
                ->assertSee(route('experiments.show', $experiment->id));
    }
}