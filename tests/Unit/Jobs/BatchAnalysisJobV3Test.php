<?php

namespace Tests\Unit\Jobs;

use Tests\TestCase;
use App\Jobs\BatchAnalysisJobV3;
use App\Models\AnalysisJob;
use App\Models\TextAnalysis;
use App\Models\ComparisonMetric;
use App\Services\MetricsService;
use App\Services\ClaudeService;
use App\Services\OpenAIService;
use App\Services\GeminiService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

class BatchAnalysisJobV3Test extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        
        // Mock HTTP responses to avoid real API calls
        Http::fake([
            'api.anthropic.com/*' => Http::response([
                'content' => [
                    [
                        'text' => json_encode([
                            [
                                'text_id' => '1',
                                'primaryChoice' => ['choices' => ['yes']],
                                'annotations' => [],
                                'desinformationTechnique' => ['choices' => []]
                            ]
                        ])
                    ]
                ]
            ], 200),
            'api.openai.com/*' => Http::response([
                'choices' => [
                    [
                        'message' => [
                            'content' => json_encode([
                                [
                                    'text_id' => '1',
                                    'primaryChoice' => ['choices' => ['no']],
                                    'annotations' => [],
                                    'desinformationTechnique' => ['choices' => []]
                                ]
                            ])
                        ]
                    ]
                ]
            ], 200),
            'generativelanguage.googleapis.com/*' => Http::response([
                'candidates' => [
                    [
                        'content' => [
                            'parts' => [
                                [
                                    'text' => json_encode([
                                        [
                                            'text_id' => '1',
                                            'primaryChoice' => ['choices' => ['yes']],
                                            'annotations' => [],
                                            'desinformationTechnique' => ['choices' => []]
                                        ]
                                    ])
                                ]
                            ]
                        ]
                    ]
                ]
            ], 200)
        ]);
    }

    public function test_job_creation_and_basic_properties()
    {
        $jobId = Str::uuid()->toString();
        $fileContent = [
            [
                'id' => 1,
                'data' => ['content' => 'Test text content'],
                'annotations' => []
            ]
        ];
        $models = ['claude-opus-4'];

        $job = new BatchAnalysisJobV3($jobId, $fileContent, $models);

        $this->assertEquals($jobId, $job->jobId);
        $this->assertEquals($fileContent, $job->fileContent);
        $this->assertEquals($models, $job->models);
        $this->assertEquals(3, $job->tries);
        $this->assertEquals(3600, $job->timeout);
    }

    public function test_job_creates_analysis_job_record()
    {
        $jobId = Str::uuid()->toString();
        $fileContent = [
            [
                'id' => 1,
                'data' => ['content' => 'Test text content'],
                'annotations' => []
            ]
        ];
        $models = ['claude-opus-4'];

        // Create the AnalysisJob record first (as WebController would)
        AnalysisJob::create([
            'job_id' => $jobId,
            'status' => AnalysisJob::STATUS_PENDING,
            'total_texts' => count($fileContent),
            'processed_texts' => 0,
        ]);

        $job = new BatchAnalysisJobV3($jobId, $fileContent, $models);
        $job->handle();

        $analysisJob = AnalysisJob::where('job_id', $jobId)->first();
        $this->assertNotNull($analysisJob);
        $this->assertEquals(AnalysisJob::STATUS_COMPLETED, $analysisJob->status);
    }

    public function test_job_creates_text_analysis_records()
    {
        $jobId = Str::uuid()->toString();
        $fileContent = [
            [
                'id' => 1,
                'data' => ['content' => 'Test text content 1'],
                'annotations' => []
            ],
            [
                'id' => 2,
                'data' => ['content' => 'Test text content 2'],
                'annotations' => []
            ]
        ];
        $models = ['claude-opus-4'];

        AnalysisJob::create([
            'job_id' => $jobId,
            'status' => AnalysisJob::STATUS_PENDING,
            'total_texts' => count($fileContent),
            'processed_texts' => 0,
        ]);

        $job = new BatchAnalysisJobV3($jobId, $fileContent, $models);
        $job->handle();

        $textAnalyses = TextAnalysis::where('job_id', $jobId)->get();
        $this->assertCount(2, $textAnalyses);
        
        $this->assertEquals('1', $textAnalyses[0]->text_id);
        $this->assertEquals('Test text content 1', $textAnalyses[0]->content);
        
        $this->assertEquals('2', $textAnalyses[1]->text_id);
        $this->assertEquals('Test text content 2', $textAnalyses[1]->content);
    }

    public function test_smart_chunking_with_small_dataset()
    {
        Log::shouldReceive('info')->andReturn(true);
        Log::shouldReceive('error')->andReturn(true);
        
        $jobId = Str::uuid()->toString();
        $fileContent = [
            [
                'id' => 1,
                'data' => ['content' => 'Test text 1'],
                'annotations' => []
            ],
            [
                'id' => 2,
                'data' => ['content' => 'Test text 2'],
                'annotations' => []
            ]
        ];
        $models = ['claude-opus-4'];

        AnalysisJob::create([
            'job_id' => $jobId,
            'status' => AnalysisJob::STATUS_PENDING,
            'total_texts' => count($fileContent),
            'processed_texts' => 0,
        ]);

        $job = new BatchAnalysisJobV3($jobId, $fileContent, $models);
        $job->handle();

        // Should process in 1 chunk (2 texts, chunk size = 3)
        $textAnalyses = TextAnalysis::where('job_id', $jobId)->get();
        $this->assertCount(2, $textAnalyses);
        
        // Verify Claude annotations were saved
        foreach ($textAnalyses as $analysis) {
            $this->assertNotNull($analysis->claude_annotations);
            $this->assertIsArray($analysis->claude_annotations);
        }
    }

    public function test_smart_chunking_with_multiple_chunks()
    {
        Log::shouldReceive('info')->andReturn(true);
        Log::shouldReceive('error')->andReturn(true);
        Log::shouldReceive('warning')->andReturn(true);
        Log::shouldReceive('error')->andReturn(true);
        
        $jobId = Str::uuid()->toString();
        $fileContent = [];
        
        // Create 7 texts to test multiple chunks (chunk size = 3)
        for ($i = 1; $i <= 7; $i++) {
            $fileContent[] = [
                'id' => $i,
                'data' => ['content' => "Test text content {$i}"],
                'annotations' => []
            ];
        }
        
        $models = ['claude-opus-4'];

        AnalysisJob::create([
            'job_id' => $jobId,
            'status' => AnalysisJob::STATUS_PENDING,
            'total_texts' => count($fileContent),
            'processed_texts' => 0,
        ]);

        $job = new BatchAnalysisJobV3($jobId, $fileContent, $models);
        $job->handle();

        // Should create 7 TextAnalysis records
        $textAnalyses = TextAnalysis::where('job_id', $jobId)->get();
        $this->assertCount(7, $textAnalyses);
        
        // Verify all texts were processed
        $analysisJob = AnalysisJob::where('job_id', $jobId)->first();
        $this->assertEquals(AnalysisJob::STATUS_COMPLETED, $analysisJob->status);
        $this->assertEquals(7, $analysisJob->processed_texts);
    }

    public function test_multiple_models_processing()
    {
        Log::shouldReceive('info')->andReturn(true);
        Log::shouldReceive('error')->andReturn(true);
        
        $jobId = Str::uuid()->toString();
        $fileContent = [
            [
                'id' => 1,
                'data' => ['content' => 'Test text content'],
                'annotations' => []
            ]
        ];
        $models = ['claude-opus-4', 'gpt-4.1', 'gemini-2.5-pro'];

        AnalysisJob::create([
            'job_id' => $jobId,
            'status' => AnalysisJob::STATUS_PENDING,
            'total_texts' => count($fileContent),
            'processed_texts' => 0,
        ]);

        $job = new BatchAnalysisJobV3($jobId, $fileContent, $models);
        $job->handle();

        $textAnalysis = TextAnalysis::where('job_id', $jobId)->first();
        $this->assertNotNull($textAnalysis);
        
        // Verify all models processed
        $this->assertNotNull($textAnalysis->claude_annotations);
        $this->assertNotNull($textAnalysis->gpt_annotations);
        $this->assertNotNull($textAnalysis->gemini_annotations);
        
        // Verify final progress
        $analysisJob = AnalysisJob::where('job_id', $jobId)->first();
        $this->assertEquals(3, $analysisJob->processed_texts); // 1 text × 3 models
    }

    public function test_job_handles_missing_analysis_job()
    {
        Log::shouldReceive('error')->once()->with('Analysis job not found', ['job_id' => 'nonexistent']);
        
        $job = new BatchAnalysisJobV3('nonexistent', [], ['claude-opus-4']);
        
        // Should not throw exception, just log error and return
        $job->handle();
        
        $this->assertTrue(true); // If we get here, the test passes
    }

    public function test_chunk_processing_with_api_timeout()
    {
        // Mock HTTP timeout
        Http::fake([
            'api.anthropic.com/*' => function () {
                throw new \Exception('cURL error 28: Operation timed out after 300 seconds');
            }
        ]);
        
        // Mock individual service for fallback
        $mockClaudeService = $this->createMock(ClaudeService::class);
        $mockClaudeService->method('analyzeText')->willReturn([
            'primaryChoice' => ['choices' => ['yes']],
            'annotations' => [],
            'desinformationTechnique' => ['choices' => []]
        ]);
        
        $this->app->instance(ClaudeService::class, $mockClaudeService);
        
        Log::shouldReceive('info')->andReturn(true);
        Log::shouldReceive('error')->andReturn(true);
        Log::shouldReceive('warning')->once()->withArgs(function ($message, $context) {
            return str_contains($message, 'Chunk processing failed, falling back to individual');
        });
        
        $jobId = Str::uuid()->toString();
        $fileContent = [
            [
                'id' => 1,
                'data' => ['content' => 'Test text content'],
                'annotations' => []
            ]
        ];
        $models = ['claude-opus-4'];

        AnalysisJob::create([
            'job_id' => $jobId,
            'status' => AnalysisJob::STATUS_PENDING,
            'total_texts' => count($fileContent),
            'processed_texts' => 0,
        ]);

        $job = new BatchAnalysisJobV3($jobId, $fileContent, $models);
        $job->handle();

        // Should still complete successfully via fallback
        $analysisJob = AnalysisJob::where('job_id', $jobId)->first();
        $this->assertEquals(AnalysisJob::STATUS_COMPLETED, $analysisJob->status);
    }

    public function test_comparison_metrics_creation()
    {
        Log::shouldReceive('info')->andReturn(true);
        Log::shouldReceive('error')->andReturn(true);
        
        $mockMetricsService = $this->createMock(MetricsService::class);
        $mockMetricsService->method('calculateMetricsForText')->willReturn([
            'precision' => 0.8,
            'recall' => 0.7,
            'f1_score' => 0.75,
            'exact_matches' => 5,
            'partial_matches' => 3,
            'false_positives' => 2,
            'false_negatives' => 1,
            'total_expert_annotations' => 8,
            'total_ai_annotations' => 7,
            'overlap_threshold' => 0.5,
            'detailed_results' => []
        ]);
        
        $this->app->instance(MetricsService::class, $mockMetricsService);
        
        $jobId = Str::uuid()->toString();
        $fileContent = [
            [
                'id' => 1,
                'data' => ['content' => 'Test text content'],
                'annotations' => [
                    ['start' => 0, 'end' => 10, 'text' => 'Test text', 'labels' => ['propaganda']]
                ]
            ]
        ];
        $models = ['claude-opus-4'];

        AnalysisJob::create([
            'job_id' => $jobId,
            'status' => AnalysisJob::STATUS_PENDING,
            'total_texts' => count($fileContent),
            'processed_texts' => 0,
        ]);

        $job = new BatchAnalysisJobV3($jobId, $fileContent, $models);
        $job->handle();

        // Verify comparison metrics were created
        $metrics = ComparisonMetric::where('job_id', $jobId)->first();
        $this->assertNotNull($metrics);
        $this->assertEquals(0.8, $metrics->precision);
        $this->assertEquals(0.7, $metrics->recall);
        $this->assertEquals(0.75, $metrics->f1_score);
    }

    public function test_job_failure_handling()
    {
        Log::shouldReceive('error')->andReturn(true);
        
        // Mock a failure in model processing
        Http::fake([
            'api.anthropic.com/*' => Http::response('Invalid response', 500)
        ]);
        
        $jobId = Str::uuid()->toString();
        $fileContent = [
            [
                'id' => 1,
                'data' => ['content' => 'Test text content'],
                'annotations' => []
            ]
        ];
        $models = ['claude-opus-4'];

        AnalysisJob::create([
            'job_id' => $jobId,
            'status' => AnalysisJob::STATUS_PENDING,
            'total_texts' => count($fileContent),
            'processed_texts' => 0,
        ]);

        $job = new BatchAnalysisJobV3($jobId, $fileContent, $models);
        
        try {
            $job->handle();
        } catch (\Exception $e) {
            // Expected to throw exception on failure
        }

        // Job should be marked as failed
        $analysisJob = AnalysisJob::where('job_id', $jobId)->first();
        $this->assertEquals(AnalysisJob::STATUS_FAILED, $analysisJob->status);
        $this->assertNotNull($analysisJob->error_message);
    }

    public function test_json_response_parsing()
    {
        Log::shouldReceive('info')->andReturn(true);
        Log::shouldReceive('error')->andReturn(true);
        
        // Mock response with JSON in code block
        Http::fake([
            'api.anthropic.com/*' => Http::response([
                'content' => [
                    [
                        'text' => "Here's the analysis:\n```json\n" . json_encode([
                            [
                                'text_id' => '1',
                                'primaryChoice' => ['choices' => ['yes']],
                                'annotations' => [],
                                'desinformationTechnique' => ['choices' => ['propaganda']]
                            ]
                        ]) . "\n```"
                    ]
                ]
            ], 200)
        ]);
        
        $jobId = Str::uuid()->toString();
        $fileContent = [
            [
                'id' => 1,
                'data' => ['content' => 'Test text content'],
                'annotations' => []
            ]
        ];
        $models = ['claude-opus-4'];

        AnalysisJob::create([
            'job_id' => $jobId,
            'status' => AnalysisJob::STATUS_PENDING,
            'total_texts' => count($fileContent),
            'processed_texts' => 0,
        ]);

        $job = new BatchAnalysisJobV3($jobId, $fileContent, $models);
        $job->handle();

        $textAnalysis = TextAnalysis::where('job_id', $jobId)->first();
        $this->assertNotNull($textAnalysis->claude_annotations);
        
        $annotations = $textAnalysis->claude_annotations;
        $this->assertArrayHasKey('primaryChoice', $annotations);
        $this->assertEquals(['yes'], $annotations['primaryChoice']['choices']);
    }

    public function test_progress_tracking_updates()
    {
        Log::shouldReceive('info')->andReturn(true);
        Log::shouldReceive('error')->andReturn(true);
        
        $jobId = Str::uuid()->toString();
        $fileContent = [
            [
                'id' => 1,
                'data' => ['content' => 'Test text 1'],
                'annotations' => []
            ],
            [
                'id' => 2,
                'data' => ['content' => 'Test text 2'],
                'annotations' => []
            ]
        ];
        $models = ['claude-opus-4', 'gpt-4.1'];

        AnalysisJob::create([
            'job_id' => $jobId,
            'status' => AnalysisJob::STATUS_PENDING,
            'total_texts' => count($fileContent),
            'processed_texts' => 0,
        ]);

        $job = new BatchAnalysisJobV3($jobId, $fileContent, $models);
        $job->handle();

        $analysisJob = AnalysisJob::where('job_id', $jobId)->first();
        
        // Final state should show completion
        $this->assertEquals(AnalysisJob::STATUS_COMPLETED, $analysisJob->status);
        $this->assertEquals(4, $analysisJob->processed_texts); // 2 texts × 2 models
        $this->assertEquals(4, $analysisJob->total_texts);
    }
}