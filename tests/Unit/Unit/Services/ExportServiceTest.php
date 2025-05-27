<?php

namespace Tests\Unit\Unit\Services;

use App\Services\ExportService;
use App\Models\Experiment;
use App\Models\ExperimentResult;
use App\Models\AnalysisJob;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ExportServiceTest extends TestCase
{
    use RefreshDatabase;

    private ExportService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new ExportService();
    }

    public function test_exports_experiment_results_to_csv(): void
    {
        $experiment = Experiment::factory()->create(['name' => 'Test Experiment']);
        $analysisJob = AnalysisJob::factory()->forExperiment($experiment)->create();
        
        ExperimentResult::factory()->create([
            'experiment_id' => $experiment->id,
            'analysis_job_id' => $analysisJob->job_id,
            'llm_model' => 'claude-4',
            'metrics' => [
                'precision' => 0.85,
                'recall' => 0.78,
                'f1_score' => 0.81,
                'cohens_kappa' => 0.65,
            ],
            'execution_time' => 2.5,
        ]);

        $csv = $this->service->exportExperimentToCsv($experiment);

        $this->assertIsString($csv);
        $this->assertStringContainsString('claude-4', $csv);
        $this->assertStringContainsString('0.85', $csv);
        $this->assertStringContainsString('0.78', $csv);
        $this->assertStringContainsString('2.5', $csv);
    }

    public function test_csv_export_has_correct_headers(): void
    {
        $experiment = Experiment::factory()->create();
        $analysisJob = AnalysisJob::factory()->forExperiment($experiment)->create();
        
        ExperimentResult::factory()->create([
            'experiment_id' => $experiment->id,
            'analysis_job_id' => $analysisJob->job_id,
        ]);

        $csv = $this->service->exportExperimentToCsv($experiment);

        $this->assertStringContainsString('llm_model', $csv);
        $this->assertStringContainsString('precision', $csv);
        $this->assertStringContainsString('recall', $csv);
        $this->assertStringContainsString('f1_score', $csv);
        $this->assertStringContainsString('cohens_kappa', $csv);
        $this->assertStringContainsString('execution_time', $csv);
    }

    public function test_exports_multiple_results(): void
    {
        $experiment = Experiment::factory()->create();
        $analysisJob = AnalysisJob::factory()->forExperiment($experiment)->create();
        
        ExperimentResult::factory()->claude()->create([
            'experiment_id' => $experiment->id,
            'analysis_job_id' => $analysisJob->job_id,
        ]);
        
        ExperimentResult::factory()->gemini()->create([
            'experiment_id' => $experiment->id,
            'analysis_job_id' => $analysisJob->job_id,
        ]);
        
        ExperimentResult::factory()->openai()->create([
            'experiment_id' => $experiment->id,
            'analysis_job_id' => $analysisJob->job_id,
        ]);

        $csv = $this->service->exportExperimentToCsv($experiment);

        $this->assertStringContainsString('claude-4', $csv);
        $this->assertStringContainsString('gemini-2.5-pro', $csv);
        $this->assertStringContainsString('gpt-4.1', $csv);
    }

    public function test_exports_experiment_to_json(): void
    {
        $experiment = Experiment::factory()->create([
            'name' => 'JSON Test Experiment',
            'description' => 'Test description',
        ]);
        $analysisJob = AnalysisJob::factory()->forExperiment($experiment)->create();
        
        ExperimentResult::factory()->create([
            'experiment_id' => $experiment->id,
            'analysis_job_id' => $analysisJob->job_id,
            'llm_model' => 'claude-4',
            'raw_results' => [
                'primaryChoice' => ['choices' => ['yes']],
                'annotations' => [],
                'desinformationTechnique' => ['choices' => ['propaganda']],
            ],
        ]);

        $json = $this->service->exportExperimentToJson($experiment);

        $this->assertIsString($json);
        
        $decoded = json_decode($json, true);
        $this->assertIsArray($decoded);
        $this->assertArrayHasKey('experiment', $decoded);
        $this->assertArrayHasKey('results', $decoded);
        $this->assertEquals('JSON Test Experiment', $decoded['experiment']['name']);
    }

    public function test_exports_statistics_to_csv(): void
    {
        $experiment = Experiment::factory()->create();
        $analysisJob = AnalysisJob::factory()->forExperiment($experiment)->create();
        
        ExperimentResult::factory()->highAccuracy()->create([
            'experiment_id' => $experiment->id,
            'analysis_job_id' => $analysisJob->job_id,
            'llm_model' => 'claude-4',
        ]);

        $statistics = [
            'models' => [
                'claude-4' => [
                    'total_analyses' => 1,
                    'avg_precision' => 0.85,
                    'avg_recall' => 0.78,
                    'avg_f1' => 0.81,
                    'avg_kappa' => 0.65,
                ]
            ]
        ];

        $csv = $this->service->exportStatisticsToCsv($statistics);

        $this->assertIsString($csv);
        $this->assertStringContainsString('claude-4', $csv);
        $this->assertStringContainsString('0.85', $csv);
        $this->assertStringContainsString('0.78', $csv);
    }

    public function test_handles_empty_experiment(): void
    {
        $experiment = Experiment::factory()->create();

        $csv = $this->service->exportExperimentToCsv($experiment);

        $this->assertIsString($csv);
        // Should still have headers even with no data
        $this->assertStringContainsString('llm_model', $csv);
    }

    public function test_handles_experiment_with_missing_metrics(): void
    {
        $experiment = Experiment::factory()->create();
        $analysisJob = AnalysisJob::factory()->forExperiment($experiment)->create();
        
        ExperimentResult::factory()->create([
            'experiment_id' => $experiment->id,
            'analysis_job_id' => $analysisJob->job_id,
            'metrics' => null, // Missing metrics
        ]);

        $csv = $this->service->exportExperimentToCsv($experiment);

        $this->assertIsString($csv);
        // Should handle null metrics gracefully
    }

    public function test_csv_format_is_valid(): void
    {
        $experiment = Experiment::factory()->create();
        $analysisJob = AnalysisJob::factory()->forExperiment($experiment)->create();
        
        ExperimentResult::factory()->create([
            'experiment_id' => $experiment->id,
            'analysis_job_id' => $analysisJob->job_id,
        ]);

        $csv = $this->service->exportExperimentToCsv($experiment);

        $lines = explode("\n", trim($csv));
        $this->assertGreaterThan(1, count($lines)); // At least header + 1 data row
        
        $header = str_getcsv($lines[0]);
        $dataRow = str_getcsv($lines[1]);
        
        $this->assertEquals(count($header), count($dataRow)); // Same number of columns
    }

    public function test_json_format_is_valid(): void
    {
        $experiment = Experiment::factory()->create();
        $analysisJob = AnalysisJob::factory()->forExperiment($experiment)->create();
        
        ExperimentResult::factory()->create([
            'experiment_id' => $experiment->id,
            'analysis_job_id' => $analysisJob->job_id,
        ]);

        $json = $this->service->exportExperimentToJson($experiment);

        $this->assertJson($json);
        
        $decoded = json_decode($json, true);
        $this->assertNull(json_last_error());
        $this->assertIsArray($decoded);
    }
}