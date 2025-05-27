<?php

namespace Tests\Unit\Unit\Models;

use App\Models\Experiment;
use App\Models\ExperimentResult;
use App\Models\AnalysisJob;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ExperimentTest extends TestCase
{
    use RefreshDatabase;

    public function test_experiment_can_be_created(): void
    {
        $experiment = Experiment::factory()->create([
            'name' => 'Test Experiment',
            'description' => 'Test description',
            'status' => 'draft',
        ]);

        $this->assertInstanceOf(Experiment::class, $experiment);
        $this->assertEquals('Test Experiment', $experiment->name);
        $this->assertEquals('draft', $experiment->status);
        $this->assertDatabaseHas('experiments', [
            'name' => 'Test Experiment',
            'status' => 'draft',
        ]);
    }

    public function test_experiment_has_required_fields(): void
    {
        $experiment = Experiment::factory()->create();

        $this->assertNotNull($experiment->name);
        $this->assertNotNull($experiment->custom_prompt);
        $this->assertNotNull($experiment->risen_config);
        $this->assertNotNull($experiment->status);
    }

    public function test_experiment_casts_risen_config_to_array(): void
    {
        $config = [
            'role' => 'Test role',
            'instructions' => 'Test instructions',
            'situation' => 'Test situation',
            'execution' => 'Test execution',
            'needle' => 'Test needle',
        ];

        $experiment = Experiment::factory()->create([
            'risen_config' => $config,
        ]);

        $this->assertIsArray($experiment->risen_config);
        $this->assertEquals($config, $experiment->risen_config);
    }

    public function test_experiment_casts_dates_properly(): void
    {
        $now = now();
        $experiment = Experiment::factory()->create([
            'started_at' => $now,
            'completed_at' => $now->addHour(),
        ]);

        $this->assertInstanceOf(\Carbon\Carbon::class, $experiment->started_at);
        $this->assertInstanceOf(\Carbon\Carbon::class, $experiment->completed_at);
    }

    public function test_experiment_has_results_relationship(): void
    {
        $experiment = Experiment::factory()->create();
        ExperimentResult::factory()->count(3)->forExperiment($experiment)->create();

        $this->assertCount(3, $experiment->results);
        $this->assertInstanceOf(ExperimentResult::class, $experiment->results->first());
    }

    public function test_experiment_has_analysis_jobs_relationship(): void
    {
        $experiment = Experiment::factory()->create();
        AnalysisJob::factory()->count(2)->forExperiment($experiment)->create();

        $this->assertCount(2, $experiment->analysisJobs);
        $this->assertInstanceOf(AnalysisJob::class, $experiment->analysisJobs->first());
    }

    public function test_experiment_status_enum_values(): void
    {
        $validStatuses = ['draft', 'running', 'completed', 'failed'];

        foreach ($validStatuses as $status) {
            $experiment = Experiment::factory()->create(['status' => $status]);
            $this->assertEquals($status, $experiment->status);
        }
    }

    public function test_experiment_factory_states(): void
    {
        $draftExperiment = Experiment::factory()->draft()->create();
        $this->assertEquals('draft', $draftExperiment->status);
        $this->assertNull($draftExperiment->started_at);
        $this->assertNull($draftExperiment->completed_at);

        $runningExperiment = Experiment::factory()->running()->create();
        $this->assertEquals('running', $runningExperiment->status);
        $this->assertNotNull($runningExperiment->started_at);
        $this->assertNull($runningExperiment->completed_at);

        $completedExperiment = Experiment::factory()->completed()->create();
        $this->assertEquals('completed', $completedExperiment->status);
        $this->assertNotNull($completedExperiment->started_at);
        $this->assertNotNull($completedExperiment->completed_at);

        $failedExperiment = Experiment::factory()->failed()->create();
        $this->assertEquals('failed', $failedExperiment->status);
        $this->assertNotNull($failedExperiment->started_at);
        $this->assertNull($failedExperiment->completed_at);
    }
}
