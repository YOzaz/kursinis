<?php

namespace Tests\Unit\Unit\Models;

use App\Models\ExperimentResult;
use App\Models\Experiment;
use App\Models\AnalysisJob;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ExperimentResultTest extends TestCase
{
    use RefreshDatabase;

    public function test_experiment_result_can_be_created(): void
    {
        $experiment = Experiment::factory()->create();
        $analysisJob = AnalysisJob::factory()->forExperiment($experiment)->create();
        
        $result = ExperimentResult::factory()->create([
            'experiment_id' => $experiment->id,
            'analysis_job_id' => $analysisJob->job_id,
            'llm_model' => 'claude-4',
        ]);

        $this->assertDatabaseHas('experiment_results', [
            'experiment_id' => $experiment->id,
            'llm_model' => 'claude-4',
        ]);
    }

    public function test_experiment_result_has_required_fields(): void
    {
        $experiment = Experiment::factory()->create();
        $analysisJob = AnalysisJob::factory()->forExperiment($experiment)->create();
        
        $result = ExperimentResult::factory()->create([
            'experiment_id' => $experiment->id,
            'analysis_job_id' => $analysisJob->job_id,
        ]);

        $this->assertNotNull($result->experiment_id);
        $this->assertNotNull($result->analysis_job_id);
        $this->assertNotNull($result->llm_model);
        $this->assertNotNull($result->metrics);
        $this->assertNotNull($result->raw_results);
        $this->assertNotNull($result->execution_time);
    }

    public function test_experiment_result_casts_metrics_to_array(): void
    {
        $experiment = Experiment::factory()->create();
        $analysisJob = AnalysisJob::factory()->forExperiment($experiment)->create();
        
        $metrics = [
            'precision' => 0.85,
            'recall' => 0.78,
            'f1_score' => 0.81,
            'cohens_kappa' => 0.65,
        ];
        
        $result = ExperimentResult::factory()->create([
            'experiment_id' => $experiment->id,
            'analysis_job_id' => $analysisJob->job_id,
            'metrics' => $metrics,
        ]);

        $this->assertIsArray($result->metrics);
        $this->assertEquals($metrics, $result->metrics);
    }

    public function test_experiment_result_casts_raw_results_to_array(): void
    {
        $experiment = Experiment::factory()->create();
        $analysisJob = AnalysisJob::factory()->forExperiment($experiment)->create();
        
        $rawResults = [
            'primaryChoice' => ['choices' => ['yes']],
            'annotations' => [],
            'desinformationTechnique' => ['choices' => ['propaganda']],
        ];
        
        $result = ExperimentResult::factory()->create([
            'experiment_id' => $experiment->id,
            'analysis_job_id' => $analysisJob->job_id,
            'raw_results' => $rawResults,
        ]);

        $this->assertIsArray($result->raw_results);
        $this->assertEquals($rawResults, $result->raw_results);
    }

    public function test_experiment_result_has_experiment_relationship(): void
    {
        $experiment = Experiment::factory()->create();
        $analysisJob = AnalysisJob::factory()->forExperiment($experiment)->create();
        
        $result = ExperimentResult::factory()->create([
            'experiment_id' => $experiment->id,
            'analysis_job_id' => $analysisJob->job_id,
        ]);

        $this->assertInstanceOf(\Illuminate\Database\Eloquent\Relations\BelongsTo::class, $result->experiment());
        $this->assertEquals($experiment->id, $result->experiment->id);
    }

    public function test_experiment_result_has_analysis_job_relationship(): void
    {
        $experiment = Experiment::factory()->create();
        $analysisJob = AnalysisJob::factory()->forExperiment($experiment)->create();
        
        $result = ExperimentResult::factory()->create([
            'experiment_id' => $experiment->id,
            'analysis_job_id' => $analysisJob->job_id,
        ]);

        $this->assertInstanceOf(\Illuminate\Database\Eloquent\Relations\BelongsTo::class, $result->analysisJob());
    }

    public function test_factory_model_states(): void
    {
        $experiment = Experiment::factory()->create();
        $analysisJob = AnalysisJob::factory()->forExperiment($experiment)->create();
        
        $claudeResult = ExperimentResult::factory()->claude()->create([
            'experiment_id' => $experiment->id,
            'analysis_job_id' => $analysisJob->job_id,
        ]);
        
        $geminiResult = ExperimentResult::factory()->gemini()->create([
            'experiment_id' => $experiment->id,
            'analysis_job_id' => $analysisJob->job_id,
        ]);
        
        $openaiResult = ExperimentResult::factory()->openai()->create([
            'experiment_id' => $experiment->id,
            'analysis_job_id' => $analysisJob->job_id,
        ]);

        $this->assertEquals('claude-4', $claudeResult->llm_model);
        $this->assertEquals('gemini-2.5-pro', $geminiResult->llm_model);
        $this->assertEquals('gpt-4.1', $openaiResult->llm_model);
    }

    public function test_factory_accuracy_states(): void
    {
        $experiment = Experiment::factory()->create();
        $analysisJob = AnalysisJob::factory()->forExperiment($experiment)->create();
        
        $highAccuracyResult = ExperimentResult::factory()->highAccuracy()->create([
            'experiment_id' => $experiment->id,
            'analysis_job_id' => $analysisJob->job_id,
        ]);
        
        $lowAccuracyResult = ExperimentResult::factory()->lowAccuracy()->create([
            'experiment_id' => $experiment->id,
            'analysis_job_id' => $analysisJob->job_id,
        ]);

        $this->assertGreaterThan(0.8, $highAccuracyResult->metrics['precision']);
        $this->assertLessThan(0.6, $lowAccuracyResult->metrics['precision']);
    }

    public function test_execution_time_is_decimal(): void
    {
        $experiment = Experiment::factory()->create();
        $analysisJob = AnalysisJob::factory()->forExperiment($experiment)->create();
        
        $result = ExperimentResult::factory()->create([
            'experiment_id' => $experiment->id,
            'analysis_job_id' => $analysisJob->job_id,
            'execution_time' => 2.567,
        ]);

        $this->assertEquals(2.567, $result->execution_time);
        $this->assertIsFloat($result->execution_time);
    }
}