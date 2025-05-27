<?php

namespace Tests\Unit\Unit\Jobs;

use App\Jobs\AnalyzeTextJob;
use App\Models\AnalysisJob;
use App\Services\ClaudeService;
use App\Services\GeminiService;
use App\Services\OpenAIService;
use App\Services\MetricsService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class AnalyzeTextJobTest extends TestCase
{
    use RefreshDatabase;

    public function test_job_can_be_dispatched(): void
    {
        Queue::fake();

        $job = AnalysisJob::factory()->pending()->create();
        $textData = [
            'id' => '1',
            'content' => 'Test propaganda text for analysis',
            'expert_annotations' => [
                ['type' => 'labels', 'value' => ['labels' => ['simplification']]]
            ]
        ];

        AnalyzeTextJob::dispatch($job->job_id, $textData, ['claude-4'], null);

        Queue::assertPushed(AnalyzeTextJob::class);
    }

    public function test_job_handles_single_model(): void
    {
        $this->mockLLMResponse('claude', [
            'primaryChoice' => ['choices' => ['yes']],
            'annotations' => [
                [
                    'type' => 'labels',
                    'value' => [
                        'start' => 0,
                        'end' => 4,
                        'text' => 'Test',
                        'labels' => ['simplification']
                    ]
                ]
            ],
            'desinformationTechnique' => ['choices' => ['propaganda']]
        ]);

        $job = AnalysisJob::factory()->pending()->create();
        $textData = [
            'id' => '1',
            'content' => 'Test propaganda text',
            'expert_annotations' => [
                ['type' => 'labels', 'value' => ['labels' => ['simplification']]]
            ]
        ];

        $analyzeJob = new AnalyzeTextJob($job->job_id, $textData, ['claude-4'], null);
        $analyzeJob->handle(
            app(ClaudeService::class),
            app(GeminiService::class),
            app(OpenAIService::class),
            app(MetricsService::class)
        );

        $this->assertDatabaseHas('text_analysis', [
            'job_id' => $job->job_id,
            'text_id' => '1'
        ]);
    }

    public function test_job_handles_multiple_models(): void
    {
        $this->mockLLMResponse('all', [
            'primaryChoice' => ['choices' => ['yes']],
            'annotations' => [],
            'desinformationTechnique' => ['choices' => []]
        ]);

        $job = AnalysisJob::factory()->pending()->create();
        $textData = [
            'id' => '1',
            'content' => 'Test text',
            'expert_annotations' => []
        ];

        $analyzeJob = new AnalyzeTextJob($job->job_id, $textData, ['claude-4', 'gemini-2.5-pro', 'gpt-4.1'], null);
        $analyzeJob->handle(
            app(ClaudeService::class),
            app(GeminiService::class),
            app(OpenAIService::class),
            app(MetricsService::class)
        );

        $this->assertDatabaseHas('text_analysis', [
            'job_id' => $job->job_id,
            'text_id' => '1'
        ]);

        // Should have comparison metrics for each model
        $this->assertDatabaseHas('comparison_metrics', [
            'job_id' => $job->job_id,
            'text_id' => '1',
            'model_name' => 'claude-4'
        ]);

        $this->assertDatabaseHas('comparison_metrics', [
            'job_id' => $job->job_id,
            'text_id' => '1',
            'model_name' => 'gemini-2.5-pro'
        ]);

        $this->assertDatabaseHas('comparison_metrics', [
            'job_id' => $job->job_id,
            'text_id' => '1',
            'model_name' => 'gpt-4.1'
        ]);
    }

    public function test_job_handles_custom_prompt(): void
    {
        $this->mockLLMResponse('claude');

        $job = AnalysisJob::factory()->pending()->create();
        $textData = [
            'id' => '1',
            'content' => 'Test text',
            'expert_annotations' => []
        ];
        $customPrompt = 'Custom analysis prompt for testing';

        $analyzeJob = new AnalyzeTextJob($job->job_id, $textData, ['claude-4'], $customPrompt);
        $analyzeJob->handle(
            app(ClaudeService::class),
            app(GeminiService::class),
            app(OpenAIService::class),
            app(MetricsService::class)
        );

        $this->assertDatabaseHas('text_analysis', [
            'job_id' => $job->job_id,
            'text_id' => '1'
        ]);
    }

    public function test_job_updates_analysis_job_progress(): void
    {
        $this->mockLLMResponse('claude');

        $job = AnalysisJob::factory()->create([
            'total_texts' => 2,
            'processed_texts' => 0
        ]);

        $textData = [
            'id' => '1',
            'content' => 'Test text',
            'expert_annotations' => []
        ];

        $analyzeJob = new AnalyzeTextJob($job->job_id, $textData, ['claude-4'], null);
        $analyzeJob->handle(
            app(ClaudeService::class),
            app(GeminiService::class),
            app(OpenAIService::class),
            app(MetricsService::class)
        );

        $job->refresh();
        $this->assertEquals(1, $job->processed_texts);
    }

    public function test_job_handles_llm_service_errors(): void
    {
        // Mock HTTP to simulate API errors
        $this->mockLLMResponse('claude', null); // This will cause an error

        $job = AnalysisJob::factory()->pending()->create();
        $textData = [
            'id' => '1',
            'content' => 'Test text',
            'expert_annotations' => []
        ];

        $analyzeJob = new AnalyzeTextJob($job->job_id, $textData, ['claude-4'], null);
        
        // Job should handle errors gracefully
        try {
            $analyzeJob->handle(
                app(ClaudeService::class),
                app(GeminiService::class),
                app(OpenAIService::class),
                app(MetricsService::class)
            );
        } catch (\Exception $e) {
            // This is expected for failed API calls
        }

        // Job should still be recorded even if it fails
        $this->assertDatabaseHas('text_analysis', [
            'job_id' => $job->job_id,
            'text_id' => '1'
        ]);
    }

    public function test_job_processes_complex_annotations(): void
    {
        $this->mockLLMResponse('claude', [
            'primaryChoice' => ['choices' => ['yes']],
            'annotations' => [
                [
                    'type' => 'labels',
                    'value' => [
                        'start' => 0,
                        'end' => 10,
                        'text' => 'Complex',
                        'labels' => ['simplification', 'emotionalExpression']
                    ]
                ],
                [
                    'type' => 'labels',
                    'value' => [
                        'start' => 15,
                        'end' => 25,
                        'text' => 'propaganda',
                        'labels' => ['doubt']
                    ]
                ]
            ],
            'desinformationTechnique' => ['choices' => ['distrustOfLithuanianInstitutions']]
        ]);

        $job = AnalysisJob::factory()->pending()->create();
        $textData = [
            'id' => '1',
            'content' => 'Complex propaganda text with multiple techniques',
            'expert_annotations' => [
                [
                    'type' => 'labels',
                    'value' => [
                        'start' => 0,
                        'end' => 10,
                        'text' => 'Complex',
                        'labels' => ['simplification']
                    ]
                ]
            ]
        ];

        $analyzeJob = new AnalyzeTextJob($job->job_id, $textData, ['claude-4'], null);
        $analyzeJob->handle(
            app(ClaudeService::class),
            app(GeminiService::class),
            app(OpenAIService::class),
            app(MetricsService::class)
        );

        $this->assertDatabaseHas('text_analysis', [
            'job_id' => $job->job_id,
            'text_id' => '1'
        ]);

        $this->assertDatabaseHas('comparison_metrics', [
            'job_id' => $job->job_id,
            'text_id' => '1',
            'model_name' => 'claude-4'
        ]);
    }
}