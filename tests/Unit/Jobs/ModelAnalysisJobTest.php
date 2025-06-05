<?php

namespace Tests\Unit\Jobs;

use Tests\TestCase;
use App\Jobs\ModelAnalysisJob;
use App\Models\AnalysisJob;
use App\Models\TextAnalysis;
use App\Models\ModelResult;
use App\Models\ComparisonMetric;
use App\Services\MetricsService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Queue;

class ModelAnalysisJobTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        
        // Mock HTTP responses to avoid real API calls
        Http::fake([
            'https://api.anthropic.com/*' => Http::response([
                'content' => [
                    [
                        'text' => json_encode([
                            [
                                'text_id' => '1',
                                'primaryChoice' => ['choices' => ['yes']],
                                'annotations' => [],
                                'desinformationTechnique' => ['choices' => ['propaganda']]
                            ],
                            [
                                'text_id' => '2',
                                'primaryChoice' => ['choices' => ['no']],
                                'annotations' => [],
                                'desinformationTechnique' => ['choices' => []]
                            ]
                        ])
                    ]
                ]
            ], 200),
            'https://api.openai.com/*' => Http::response([
                'choices' => [
                    [
                        'message' => [
                            'content' => json_encode([
                                [
                                    'text_id' => '1',
                                    'primaryChoice' => ['choices' => ['no']],
                                    'annotations' => [],
                                    'desinformationTechnique' => ['choices' => []]
                                ],
                                [
                                    'text_id' => '2',
                                    'primaryChoice' => ['choices' => ['yes']],
                                    'annotations' => [],
                                    'desinformationTechnique' => ['choices' => ['propaganda']]
                                ]
                            ])
                        ]
                    ]
                ]
            ], 200),
            'https://generativelanguage.googleapis.com/upload/*' => Http::response([
                'file' => [
                    'uri' => 'gs://test-bucket/test-file'
                ]
            ], 200),
            'https://generativelanguage.googleapis.com/v1beta/*' => Http::response([
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
                                            'desinformationTechnique' => ['choices' => ['propaganda']]
                                        ],
                                        [
                                            'text_id' => '2',
                                            'primaryChoice' => ['choices' => ['no']],
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

    public function test_job_creation_and_basic_properties(): void
    {
        $jobId = Str::uuid()->toString();
        $tempFile = tempnam(sys_get_temp_dir(), 'test_') . '.json';
        file_put_contents($tempFile, '[]'); // Create actual file
        $fileContent = [
            [
                'id' => '1',
                'data' => ['content' => 'Test content'],
                'annotations' => []
            ]
        ];

        $job = new ModelAnalysisJob($jobId, 'claude-opus-4', $tempFile, $fileContent);

        $this->assertEquals($jobId, $job->jobId);
        $this->assertEquals('claude-opus-4', $job->modelKey);
        $this->assertEquals($tempFile, $job->tempFile);
        $this->assertEquals($fileContent, $job->fileContent);
        $this->assertEquals(3, $job->tries);
        $this->assertEquals(1800, $job->timeout);

        if (file_exists($tempFile)) {
            unlink($tempFile);
        }
    }

    public function test_job_is_queued_to_models_queue(): void
    {
        $jobId = Str::uuid()->toString();
        $tempFile = tempnam(sys_get_temp_dir(), 'test_') . '.json';
        file_put_contents($tempFile, '[]');
        $fileContent = [];

        Queue::fake();

        $job = new ModelAnalysisJob($jobId, 'claude-opus-4', $tempFile, $fileContent);
        
        // The queue should be set in constructor
        $this->assertEquals('models', $job->queue);

        if (file_exists($tempFile)) {
            unlink($tempFile);
        }
    }

    public function test_claude_model_processing(): void
    {
        Log::shouldReceive('info')->andReturn(true);
        Log::shouldReceive('error')->andReturn(true);

        $jobId = Str::uuid()->toString();
        $fileContent = [
            [
                'id' => '1',
                'data' => ['content' => 'Test propaganda content'],
                'annotations' => []
            ],
            [
                'id' => '2',
                'data' => ['content' => 'Test neutral content'],
                'annotations' => []
            ]
        ];

        // Create job and text analyses
        $job = AnalysisJob::factory()->create([
            'job_id' => $jobId,
            'requested_models' => ['claude-opus-4']
        ]);

        $textAnalysis1 = TextAnalysis::factory()->create([
            'job_id' => $jobId,
            'text_id' => '1',
            'content' => 'Test propaganda content'
        ]);

        $textAnalysis2 = TextAnalysis::factory()->create([
            'job_id' => $jobId,
            'text_id' => '2',
            'content' => 'Test neutral content'
        ]);

        $tempFile = tempnam(sys_get_temp_dir(), 'test_') . '.json';
        file_put_contents($tempFile, json_encode($fileContent));

        $modelJob = new ModelAnalysisJob($jobId, 'claude-opus-4', $tempFile, $fileContent);
        $modelJob->handle();

        // Verify model results were stored in new architecture
        $this->assertDatabaseHas('model_results', [
            'job_id' => $jobId,
            'text_id' => '1',
            'model_key' => 'claude-opus-4',
            'provider' => 'anthropic',
            'status' => 'completed'
        ]);

        $this->assertDatabaseHas('model_results', [
            'job_id' => $jobId,
            'text_id' => '2',
            'model_key' => 'claude-opus-4',
            'provider' => 'anthropic',
            'status' => 'completed'
        ]);

        // Verify legacy fields were also updated for backward compatibility
        $textAnalysis1->refresh();
        $textAnalysis2->refresh();
        
        $this->assertNotNull($textAnalysis1->claude_annotations);
        $this->assertNotNull($textAnalysis2->claude_annotations);

        unlink($tempFile);
    }

    public function test_openai_model_processing(): void
    {
        Log::shouldReceive('info')->andReturn(true);
        Log::shouldReceive('error')->andReturn(true);

        $jobId = Str::uuid()->toString();
        $fileContent = [
            [
                'id' => '1',
                'data' => ['content' => 'Test content'],
                'annotations' => []
            ]
        ];

        $job = AnalysisJob::factory()->create([
            'job_id' => $jobId,
            'requested_models' => ['gpt-4o-latest']
        ]);

        $textAnalysis = TextAnalysis::factory()->create([
            'job_id' => $jobId,
            'text_id' => '1',
            'content' => 'Test content'
        ]);

        $tempFile = tempnam(sys_get_temp_dir(), 'test_') . '.json';
        file_put_contents($tempFile, json_encode($fileContent));

        $modelJob = new ModelAnalysisJob($jobId, 'gpt-4o-latest', $tempFile, $fileContent);
        $modelJob->handle();

        $this->assertDatabaseHas('model_results', [
            'job_id' => $jobId,
            'text_id' => '1',
            'model_key' => 'gpt-4o-latest',
            'provider' => 'openai',
            'status' => 'completed'
        ]);

        $textAnalysis->refresh();
        $this->assertNotNull($textAnalysis->gpt_annotations);

        unlink($tempFile);
    }

    public function test_gemini_model_processing_with_file_api(): void
    {
        Log::shouldReceive('info')->andReturn(true);
        Log::shouldReceive('error')->andReturn(true);

        $jobId = Str::uuid()->toString();
        $fileContent = [
            [
                'id' => '1',
                'data' => ['content' => 'Test content'],
                'annotations' => []
            ]
        ];

        $job = AnalysisJob::factory()->create([
            'job_id' => $jobId,
            'requested_models' => ['gemini-2.5-pro']
        ]);

        $textAnalysis = TextAnalysis::factory()->create([
            'job_id' => $jobId,
            'text_id' => '1',
            'content' => 'Test content'
        ]);

        $tempFile = tempnam(sys_get_temp_dir(), 'test_') . '.json';
        file_put_contents($tempFile, json_encode($fileContent));

        $modelJob = new ModelAnalysisJob($jobId, 'gemini-2.5-pro', $tempFile, $fileContent);
        $modelJob->handle();

        $this->assertDatabaseHas('model_results', [
            'job_id' => $jobId,
            'text_id' => '1',
            'model_key' => 'gemini-2.5-pro',
            'provider' => 'google',
            'status' => 'completed'
        ]);

        $textAnalysis->refresh();
        $this->assertNotNull($textAnalysis->gemini_annotations);

        unlink($tempFile);
    }

    public function test_job_handles_missing_analysis_job(): void
    {
        Log::shouldReceive('error')->once()->with('Analysis job not found for model processing', [
            'job_id' => 'nonexistent',
            'model' => 'claude-opus-4'
        ]);

        $tempFile = tempnam(sys_get_temp_dir(), 'test_') . '.json';
        file_put_contents($tempFile, '[]');
        $job = new ModelAnalysisJob('nonexistent', 'claude-opus-4', $tempFile, []);
        
        // Should not throw exception, just log error and return
        $job->handle();
        
        $this->assertTrue(true); // If we get here, the test passes

        if (file_exists($tempFile)) {
            unlink($tempFile);
        }
    }

    public function test_job_handles_api_failure(): void
    {
        Log::shouldReceive('info')->andReturn(true);
        Log::shouldReceive('error')->andReturn(true);

        // Mock API failure
        Http::fake([
            'https://api.anthropic.com/*' => Http::response('Server Error', 500)
        ]);

        $jobId = Str::uuid()->toString();
        $fileContent = [
            [
                'id' => '1',
                'data' => ['content' => 'Test content'],
                'annotations' => []
            ]
        ];

        $job = AnalysisJob::factory()->create([
            'job_id' => $jobId,
            'requested_models' => ['claude-opus-4']
        ]);

        $textAnalysis = TextAnalysis::factory()->create([
            'job_id' => $jobId,
            'text_id' => '1',
            'content' => 'Test content'
        ]);

        $tempFile = tempnam(sys_get_temp_dir(), 'test_') . '.json';
        file_put_contents($tempFile, json_encode($fileContent));

        $modelJob = new ModelAnalysisJob($jobId, 'claude-opus-4', $tempFile, $fileContent);
        
        $this->expectException(\Exception::class);
        $modelJob->handle();

        // Verify failure was stored in new architecture
        $this->assertDatabaseHas('model_results', [
            'job_id' => $jobId,
            'text_id' => '1',
            'model_key' => 'claude-opus-4',
            'provider' => 'anthropic',
            'status' => 'failed'
        ]);

        unlink($tempFile);
    }

    public function test_job_progress_tracking(): void
    {
        Log::shouldReceive('info')->andReturn(true);
        Log::shouldReceive('error')->andReturn(true);

        $jobId = Str::uuid()->toString();
        $fileContent = [
            [
                'id' => '1',
                'data' => ['content' => 'Test content 1'],
                'annotations' => []
            ],
            [
                'id' => '2',
                'data' => ['content' => 'Test content 2'],
                'annotations' => []
            ]
        ];

        $job = AnalysisJob::factory()->create([
            'job_id' => $jobId,
            'requested_models' => ['claude-opus-4', 'gpt-4o-latest'],
            'total_texts' => 2,
            'processed_texts' => 0
        ]);

        TextAnalysis::factory()->create([
            'job_id' => $jobId,
            'text_id' => '1',
            'content' => 'Test content 1'
        ]);

        TextAnalysis::factory()->create([
            'job_id' => $jobId,
            'text_id' => '2',
            'content' => 'Test content 2'
        ]);

        $tempFile = tempnam(sys_get_temp_dir(), 'test_') . '.json';
        file_put_contents($tempFile, json_encode($fileContent));

        // Process first model
        $modelJob1 = new ModelAnalysisJob($jobId, 'claude-opus-4', $tempFile, $fileContent);
        $modelJob1->handle();

        $job->refresh();
        $this->assertEquals(2, $job->processed_texts); // 1 model × 2 texts

        // Process second model
        $modelJob2 = new ModelAnalysisJob($jobId, 'gpt-4o-latest', $tempFile, $fileContent);
        $modelJob2->handle();

        $job->refresh();
        $this->assertEquals(4, $job->processed_texts); // 2 models × 2 texts
        $this->assertEquals(4, $job->total_texts);
        $this->assertEquals(AnalysisJob::STATUS_COMPLETED, $job->status);

        unlink($tempFile);
    }

    public function test_comparison_metrics_creation(): void
    {
        Log::shouldReceive('info')->andReturn(true);
        Log::shouldReceive('error')->andReturn(true);

        $mockMetricsService = $this->createMock(MetricsService::class);
        $mockMetricsService->expects($this->once())
                          ->method('calculateMetricsForText')
                          ->willReturn(new \App\Models\ComparisonMetric());

        $this->app->instance(MetricsService::class, $mockMetricsService);

        $jobId = Str::uuid()->toString();
        $fileContent = [
            [
                'id' => '1',
                'data' => ['content' => 'Test content'],
                'annotations' => []
            ]
        ];

        $job = AnalysisJob::factory()->create([
            'job_id' => $jobId,
            'requested_models' => ['claude-opus-4']
        ]);

        $textAnalysis = TextAnalysis::factory()->create([
            'job_id' => $jobId,
            'text_id' => '1',
            'content' => 'Test content',
            'expert_annotations' => [
                [
                    'type' => 'labels',
                    'value' => [
                        'start' => 0,
                        'end' => 10,
                        'text' => 'Test',
                        'labels' => ['propaganda']
                    ]
                ]
            ]
        ]);

        $tempFile = tempnam(sys_get_temp_dir(), 'test_') . '.json';
        file_put_contents($tempFile, json_encode($fileContent));

        $modelJob = new ModelAnalysisJob($jobId, 'claude-opus-4', $tempFile, $fileContent);
        $modelJob->handle();

        // MetricsService::calculateMetricsForText should have been called once
        // This is verified by the mock expectation above

        unlink($tempFile);
    }

    public function test_unsupported_provider_throws_exception(): void
    {
        Log::shouldReceive('info')->andReturn(true);
        Log::shouldReceive('error')->andReturn(true);

        $jobId = Str::uuid()->toString();
        $fileContent = [
            [
                'id' => '1',
                'data' => ['content' => 'Test content'],
                'annotations' => []
            ]
        ];

        $job = AnalysisJob::factory()->create([
            'job_id' => $jobId,
            'requested_models' => ['unsupported-model']
        ]);

        TextAnalysis::factory()->create([
            'job_id' => $jobId,
            'text_id' => '1',
            'content' => 'Test content'
        ]);

        $tempFile = tempnam(sys_get_temp_dir(), 'test_') . '.json';
        file_put_contents($tempFile, json_encode($fileContent));

        // Mock config to return unsupported provider
        config(['llm.models.unsupported-model' => ['provider' => 'unsupported']]);

        $modelJob = new ModelAnalysisJob($jobId, 'unsupported-model', $tempFile, $fileContent);
        
        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('Unsupported provider: unsupported');
        
        $modelJob->handle();

        unlink($tempFile);
    }

    public function test_failed_job_callback(): void
    {
        Log::shouldReceive('error')->andReturn(true);
        Log::shouldReceive('info')->andReturn(true);

        $jobId = Str::uuid()->toString();
        $tempFile = tempnam(sys_get_temp_dir(), 'test_') . '.json';
        file_put_contents($tempFile, '[]');
        $fileContent = [];

        $job = AnalysisJob::factory()->create([
            'job_id' => $jobId,
            'requested_models' => ['claude-opus-4']
        ]);

        $modelJob = new ModelAnalysisJob($jobId, 'claude-opus-4', $tempFile, $fileContent);
        $exception = new \Exception('Test failure');
        
        $modelJob->failed($exception);

        // Should update job progress even for failed models
        $job->refresh();
        // The failed callback should have been called

        if (file_exists($tempFile)) {
            unlink($tempFile);
        }
    }
}