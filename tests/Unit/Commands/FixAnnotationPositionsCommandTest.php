<?php

namespace Tests\Unit\Commands;

use App\Console\Commands\FixAnnotationPositionsCommand;
use App\Models\AnalysisJob;
use App\Models\ModelResult;
use App\Models\TextAnalysis;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class FixAnnotationPositionsCommandTest extends TestCase
{
    use RefreshDatabase;

    /** @test */
    public function it_can_run_in_dry_run_mode()
    {
        $this->artisan('fix:annotation-positions --dry-run')
            ->expectsOutput('Running in DRY RUN mode - no changes will be made')
            ->assertExitCode(0);
    }

    /** @test */
    public function it_processes_model_results_with_annotations()
    {
        // Create test data
        $analysisJob = AnalysisJob::factory()->create([
            'status' => 'completed'
        ]);

        $textAnalysis = TextAnalysis::factory()->create([
            'analysis_job_id' => $analysisJob->id,
            'original_text' => 'This is a test text with UTF-8 characters: ąčęėįšųūž',
            'expert_annotations' => []
        ]);

        $modelResult = ModelResult::factory()->create([
            'text_analysis_id' => $textAnalysis->id,
            'model_name' => 'test-model',
            'raw_response' => json_encode([
                'techniques' => [
                    [
                        'technique' => 'Loaded Language',
                        'start_pos' => 30,
                        'end_pos' => 45,
                        'fragment' => 'UTF-8 characters'
                    ]
                ]
            ]),
            'status' => 'completed'
        ]);

        $this->artisan('fix:annotation-positions --dry-run')
            ->expectsOutput('Running in DRY RUN mode - no changes will be made')
            ->expectsOutput('Processing model result ID: ' . $modelResult->id)
            ->assertExitCode(0);
    }

    /** @test */
    public function it_can_filter_by_specific_model()
    {
        // Create test data for different models
        $analysisJob = AnalysisJob::factory()->create([
            'status' => 'completed'
        ]);

        $textAnalysis = TextAnalysis::factory()->create([
            'analysis_job_id' => $analysisJob->id,
            'original_text' => 'Test text',
            'expert_annotations' => []
        ]);

        $modelResult1 = ModelResult::factory()->create([
            'text_analysis_id' => $textAnalysis->id,
            'model_name' => 'claude-3-5-sonnet-20241022',
            'raw_response' => json_encode(['techniques' => []]),
            'status' => 'completed'
        ]);

        $modelResult2 = ModelResult::factory()->create([
            'text_analysis_id' => $textAnalysis->id,
            'model_name' => 'gpt-4o',
            'raw_response' => json_encode(['techniques' => []]),
            'status' => 'completed'
        ]);

        $this->artisan('fix:annotation-positions --dry-run --model=claude-3-5-sonnet-20241022')
            ->expectsOutput('Filtering by model: claude-3-5-sonnet-20241022')
            ->expectsOutput('Processing model result ID: ' . $modelResult1->id)
            ->assertExitCode(0);
    }

    /** @test */
    public function it_can_filter_by_specific_text_id()
    {
        // Create test data
        $analysisJob = AnalysisJob::factory()->create([
            'status' => 'completed'
        ]);

        $textAnalysis1 = TextAnalysis::factory()->create([
            'analysis_job_id' => $analysisJob->id,
            'original_text' => 'First test text',
            'expert_annotations' => []
        ]);

        $textAnalysis2 = TextAnalysis::factory()->create([
            'analysis_job_id' => $analysisJob->id,
            'original_text' => 'Second test text',
            'expert_annotations' => []
        ]);

        $modelResult1 = ModelResult::factory()->create([
            'text_analysis_id' => $textAnalysis1->id,
            'model_name' => 'test-model',
            'raw_response' => json_encode(['techniques' => []]),
            'status' => 'completed'
        ]);

        $modelResult2 = ModelResult::factory()->create([
            'text_analysis_id' => $textAnalysis2->id,
            'model_name' => 'test-model',
            'raw_response' => json_encode(['techniques' => []]),
            'status' => 'completed'
        ]);

        $this->artisan("fix:annotation-positions --dry-run --text-id={$textAnalysis1->id}")
            ->expectsOutput('Filtering by text ID: ' . $textAnalysis1->id)
            ->expectsOutput('Processing model result ID: ' . $modelResult1->id)
            ->assertExitCode(0);
    }

    /** @test */
    public function it_handles_utf8_position_calculations()
    {
        // Create test data with UTF-8 characters
        $analysisJob = AnalysisJob::factory()->create([
            'status' => 'completed'
        ]);

        $textWithUtf8 = 'Lietuvių kalba su ąčęėįšųūž simboliais ir dar daugiau teksto';
        $textAnalysis = TextAnalysis::factory()->create([
            'analysis_job_id' => $analysisJob->id,
            'original_text' => $textWithUtf8,
            'expert_annotations' => []
        ]);

        // Create annotations with potentially incorrect positions
        $modelResult = ModelResult::factory()->create([
            'text_analysis_id' => $textAnalysis->id,
            'model_name' => 'test-model',
            'raw_response' => json_encode([
                'techniques' => [
                    [
                        'technique' => 'Loaded Language',
                        'start_pos' => 20, // This might be byte position instead of character position
                        'end_pos' => 30,
                        'fragment' => 'ąčęėįšųūž'
                    ]
                ]
            ]),
            'status' => 'completed'
        ]);

        $this->artisan('fix:annotation-positions --dry-run')
            ->expectsOutput('Running in DRY RUN mode - no changes will be made')
            ->expectsOutput('Processing model result ID: ' . $modelResult->id)
            ->assertExitCode(0);
    }

    /** @test */
    public function it_provides_summary_statistics()
    {
        // Create test data
        $analysisJob = AnalysisJob::factory()->create([
            'status' => 'completed'
        ]);

        $textAnalysis = TextAnalysis::factory()->create([
            'analysis_job_id' => $analysisJob->id,
            'original_text' => 'Test text for statistics',
            'expert_annotations' => []
        ]);

        $modelResult = ModelResult::factory()->create([
            'text_analysis_id' => $textAnalysis->id,
            'model_name' => 'test-model',
            'raw_response' => json_encode([
                'techniques' => [
                    [
                        'technique' => 'Test Technique',
                        'start_pos' => 0,
                        'end_pos' => 4,
                        'fragment' => 'Test'
                    ]
                ]
            ]),
            'status' => 'completed'
        ]);

        $this->artisan('fix:annotation-positions --dry-run')
            ->expectsOutput('Running in DRY RUN mode - no changes will be made')
            ->expectsOutput('Summary:')
            ->expectsOutput('- Total model results processed: 1')
            ->expectsOutput('- Results with annotations: 1')
            ->expectsOutput('- Annotations processed: 1')
            ->assertExitCode(0);
    }

    /** @test */
    public function it_skips_results_without_annotations()
    {
        // Create test data without annotations
        $analysisJob = AnalysisJob::factory()->create([
            'status' => 'completed'
        ]);

        $textAnalysis = TextAnalysis::factory()->create([
            'analysis_job_id' => $analysisJob->id,
            'original_text' => 'Test text without annotations',
            'expert_annotations' => []
        ]);

        $modelResult = ModelResult::factory()->create([
            'text_analysis_id' => $textAnalysis->id,
            'model_name' => 'test-model',
            'raw_response' => json_encode([
                'techniques' => [] // No techniques
            ]),
            'status' => 'completed'
        ]);

        $this->artisan('fix:annotation-positions --dry-run')
            ->expectsOutput('Running in DRY RUN mode - no changes will be made')
            ->expectsOutput('Summary:')
            ->expectsOutput('- Total model results processed: 1')
            ->expectsOutput('- Results with annotations: 0')
            ->expectsOutput('- Annotations processed: 0')
            ->assertExitCode(0);
    }

    /** @test */
    public function it_handles_malformed_json_gracefully()
    {
        // Create test data with malformed JSON
        $analysisJob = AnalysisJob::factory()->create([
            'status' => 'completed'
        ]);

        $textAnalysis = TextAnalysis::factory()->create([
            'analysis_job_id' => $analysisJob->id,
            'original_text' => 'Test text',
            'expert_annotations' => []
        ]);

        $modelResult = ModelResult::factory()->create([
            'text_analysis_id' => $textAnalysis->id,
            'model_name' => 'test-model',
            'raw_response' => 'invalid json',
            'status' => 'completed'
        ]);

        $this->artisan('fix:annotation-positions --dry-run')
            ->expectsOutput('Running in DRY RUN mode - no changes will be made')
            ->expectsOutput('Skipping model result ID: ' . $modelResult->id . ' (invalid JSON)')
            ->assertExitCode(0);
    }
}