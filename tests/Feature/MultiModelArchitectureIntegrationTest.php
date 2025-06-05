<?php

namespace Tests\Feature;

use Tests\TestCase;
use App\Jobs\BatchAnalysisJobV4;
use App\Models\AnalysisJob;
use App\Models\TextAnalysis;
use App\Models\ModelResult;
use App\Models\ComparisonMetric;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Str;

class MultiModelArchitectureIntegrationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        
        // Mock HTTP responses for all providers
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

        // Mock logging to avoid cluttering test output
        Log::shouldReceive('info')->andReturn(true);
        Log::shouldReceive('error')->andReturn(true);
        Log::shouldReceive('warning')->andReturn(true);
    }

    public function test_multiple_claude_models_analysis(): void
    {
        Queue::fake();

        $jobId = Str::uuid()->toString();
        $fileContent = [
            [
                'id' => '1',
                'data' => ['content' => 'This is propaganda content'],
                'annotations' => [
                    [
                        'type' => 'labels',
                        'value' => [
                            'start' => 0,
                            'end' => 10,
                            'text' => 'This is',
                            'labels' => ['simplification']
                        ]
                    ]
                ]
            ],
            [
                'id' => '2',
                'data' => ['content' => 'This is neutral content'],
                'annotations' => []
            ]
        ];
        $models = ['claude-opus-4', 'claude-sonnet-4'];

        // Create analysis job with requested models
        AnalysisJob::create([
            'job_id' => $jobId,
            'status' => AnalysisJob::STATUS_PENDING,
            'total_texts' => count($fileContent),
            'processed_texts' => 0,
            'name' => 'Multi-Claude Test',
            'requested_models' => $models,
        ]);

        // Create and handle the V4 job which should create ModelAnalysisJobs
        $batchJob = new BatchAnalysisJobV4($jobId, $fileContent, $models);
        $batchJob->handle();

        // Verify ModelAnalysisJobs were queued for both Claude models
        Queue::assertPushed(\App\Jobs\ModelAnalysisJob::class, 2);
        
        Queue::assertPushed(\App\Jobs\ModelAnalysisJob::class, function ($job) use ($jobId) {
            return $job->jobId === $jobId && $job->modelKey === 'claude-opus-4';
        });

        Queue::assertPushed(\App\Jobs\ModelAnalysisJob::class, function ($job) use ($jobId) {
            return $job->jobId === $jobId && $job->modelKey === 'claude-sonnet-4';
        });

        // Verify TextAnalysis records were created
        $this->assertDatabaseCount('text_analysis', 2);
        
        $this->assertDatabaseHas('text_analysis', [
            'job_id' => $jobId,
            'text_id' => '1',
            'content' => 'This is propaganda content'
        ]);

        $this->assertDatabaseHas('text_analysis', [
            'job_id' => $jobId,
            'text_id' => '2',
            'content' => 'This is neutral content'
        ]);
    }

    public function test_full_multi_model_workflow_with_all_providers(): void
    {
        Queue::fake();

        $jobId = Str::uuid()->toString();
        $fileContent = [
            [
                'id' => '1',
                'data' => ['content' => 'Test content for all models'],
                'annotations' => []
            ]
        ];
        $models = ['claude-opus-4', 'claude-sonnet-4', 'gpt-4o-latest', 'gpt-4.1', 'gemini-2.5-pro', 'gemini-2.5-flash'];

        AnalysisJob::create([
            'job_id' => $jobId,
            'status' => AnalysisJob::STATUS_PENDING,
            'total_texts' => count($fileContent),
            'processed_texts' => 0,
            'name' => 'All Models Test',
            'requested_models' => $models,
        ]);

        $batchJob = new BatchAnalysisJobV4($jobId, $fileContent, $models);
        $batchJob->handle();

        // Should create one ModelAnalysisJob for each model
        Queue::assertPushed(\App\Jobs\ModelAnalysisJob::class, 6);

        // Verify each model was queued
        foreach ($models as $modelKey) {
            Queue::assertPushed(\App\Jobs\ModelAnalysisJob::class, function ($job) use ($jobId, $modelKey) {
                return $job->jobId === $jobId && $job->modelKey === $modelKey;
            });
        }
    }

    public function test_model_result_storage_and_retrieval(): void
    {
        $jobId = Str::uuid()->toString();

        $job = AnalysisJob::factory()->create([
            'job_id' => $jobId,
            'requested_models' => ['claude-opus-4', 'claude-sonnet-4']
        ]);

        $textAnalysis = TextAnalysis::factory()->create([
            'job_id' => $jobId,
            'text_id' => 'test-123',
            'content' => 'Test content'
        ]);

        // Store results for both Claude models using new architecture
        $textAnalysis->storeModelResult(
            'claude-opus-4',
            ['primaryChoice' => ['choices' => ['yes']], 'annotations' => []],
            'claude-3-opus-20240229',
            15000
        );

        $textAnalysis->storeModelResult(
            'claude-sonnet-4',
            ['primaryChoice' => ['choices' => ['no']], 'annotations' => []],
            'claude-3-sonnet-20240229',
            8000
        );

        // Verify both results were stored
        $this->assertDatabaseCount('model_results', 2);

        $this->assertDatabaseHas('model_results', [
            'job_id' => $jobId,
            'text_id' => 'test-123',
            'model_key' => 'claude-opus-4',
            'provider' => 'anthropic',
            'actual_model_name' => 'claude-3-opus-20240229',
            'execution_time_ms' => 15000,
            'status' => 'completed'
        ]);

        $this->assertDatabaseHas('model_results', [
            'job_id' => $jobId,
            'text_id' => 'test-123',
            'model_key' => 'claude-sonnet-4',
            'provider' => 'anthropic',
            'actual_model_name' => 'claude-3-sonnet-20240229',
            'execution_time_ms' => 8000,
            'status' => 'completed'
        ]);

        // Test retrieval via TextAnalysis methods
        $attemptedModels = $textAnalysis->getAllAttemptedModels();
        $this->assertCount(2, $attemptedModels);
        $this->assertArrayHasKey('claude-opus-4', $attemptedModels);
        $this->assertArrayHasKey('claude-sonnet-4', $attemptedModels);

        $annotations = $textAnalysis->getAllModelAnnotations();
        $this->assertCount(2, $annotations);
        $this->assertArrayHasKey('claude-opus-4', $annotations);
        $this->assertArrayHasKey('claude-sonnet-4', $annotations);
    }

    public function test_backward_compatibility_with_legacy_data(): void
    {
        $jobId = Str::uuid()->toString();

        $job = AnalysisJob::factory()->create([
            'job_id' => $jobId
        ]);

        // Create analysis with legacy data only (no model_results)
        $textAnalysis = TextAnalysis::factory()->create([
            'job_id' => $jobId,
            'text_id' => 'legacy-test',
            'content' => 'Legacy test content',
            'claude_annotations' => ['primaryChoice' => ['choices' => ['yes']]],
            'claude_actual_model' => 'claude-3-opus-20240229',
            'gpt_annotations' => ['primaryChoice' => ['choices' => ['no']]],
            'gpt_actual_model' => 'gpt-4o-2024-05-13',
            'gemini_annotations' => null,
            'gemini_error' => 'Rate limit exceeded',
            'gemini_model_name' => 'gemini-2.5-pro'
        ]);

        // Should fallback to legacy structure
        $attemptedModels = $textAnalysis->getAllAttemptedModels();
        
        $this->assertArrayHasKey('claude-opus-4', $attemptedModels);
        $this->assertEquals('success', $attemptedModels['claude-opus-4']['status']);
        
        $this->assertArrayHasKey('gpt-4o-latest', $attemptedModels);
        $this->assertEquals('success', $attemptedModels['gpt-4o-latest']['status']);
        
        $this->assertArrayHasKey('gemini-2.5-pro', $attemptedModels);
        $this->assertEquals('failed', $attemptedModels['gemini-2.5-pro']['status']);
        $this->assertEquals('Rate limit exceeded', $attemptedModels['gemini-2.5-pro']['error']);
    }

    public function test_mixed_architecture_data_handling(): void
    {
        $jobId = Str::uuid()->toString();

        $job = AnalysisJob::factory()->create([
            'job_id' => $jobId,
            'requested_models' => ['claude-opus-4', 'claude-sonnet-4', 'gpt-4o-latest']
        ]);

        // Create analysis with mixed legacy and new data
        $textAnalysis = TextAnalysis::factory()->create([
            'job_id' => $jobId,
            'text_id' => 'mixed-test',
            'content' => 'Mixed architecture test',
            // Legacy data for one model
            'gpt_annotations' => ['primaryChoice' => ['choices' => ['yes']]],
            'gpt_actual_model' => 'gpt-4o-2024-05-13',
            'gpt_model_name' => 'gpt-4o-latest'
        ]);

        // New architecture data for Claude models
        $textAnalysis->storeModelResult(
            'claude-opus-4',
            ['primaryChoice' => ['choices' => ['no']], 'annotations' => []],
            'claude-3-opus-20240229',
            12000
        );

        $textAnalysis->storeModelResult(
            'claude-sonnet-4',
            ['primaryChoice' => ['choices' => ['yes']], 'annotations' => []],
            'claude-3-sonnet-20240229',
            7000
        );

        // Also store GPT result in new architecture to test mixed scenario
        $textAnalysis->storeModelResult(
            'gpt-4o-latest',
            ['primaryChoice' => ['choices' => ['yes']], 'annotations' => []],
            'gpt-4o-2024-05-13',
            9000
        );

        // Should use new architecture data when available
        $attemptedModels = $textAnalysis->getAllAttemptedModels();
        
        $this->assertCount(3, $attemptedModels);
        
        // New architecture models
        $this->assertArrayHasKey('claude-opus-4', $attemptedModels);
        $this->assertEquals('success', $attemptedModels['claude-opus-4']['status']);
        $this->assertEquals(12000, $attemptedModels['claude-opus-4']['execution_time_ms']);
        
        $this->assertArrayHasKey('claude-sonnet-4', $attemptedModels);
        $this->assertEquals('success', $attemptedModels['claude-sonnet-4']['status']);
        $this->assertEquals(7000, $attemptedModels['claude-sonnet-4']['execution_time_ms']);
        
        // GPT model data (from new architecture)
        $this->assertArrayHasKey('gpt-4o-latest', $attemptedModels);
        $this->assertEquals('success', $attemptedModels['gpt-4o-latest']['status']);
        $this->assertEquals(9000, $attemptedModels['gpt-4o-latest']['execution_time_ms']);
    }

    public function test_model_result_failure_handling(): void
    {
        $jobId = Str::uuid()->toString();

        $job = AnalysisJob::factory()->create([
            'job_id' => $jobId,
            'requested_models' => ['claude-opus-4', 'gpt-4o-latest']
        ]);

        $textAnalysis = TextAnalysis::factory()->create([
            'job_id' => $jobId,
            'text_id' => 'failure-test',
            'content' => 'Failure test content'
        ]);

        // Store successful result
        $textAnalysis->storeModelResult(
            'claude-opus-4',
            ['primaryChoice' => ['choices' => ['yes']], 'annotations' => []],
            'claude-3-opus-20240229',
            10000
        );

        // Store failed result
        $textAnalysis->storeModelResult(
            'gpt-4o-latest',
            [],
            null,
            null,
            'API rate limit exceeded'
        );

        $this->assertDatabaseHas('model_results', [
            'job_id' => $jobId,
            'text_id' => 'failure-test',
            'model_key' => 'claude-opus-4',
            'status' => 'completed'
        ]);

        $this->assertDatabaseHas('model_results', [
            'job_id' => $jobId,
            'text_id' => 'failure-test',
            'model_key' => 'gpt-4o-latest',
            'status' => 'failed',
            'error_message' => 'API rate limit exceeded'
        ]);

        $attemptedModels = $textAnalysis->getAllAttemptedModels();
        $this->assertEquals('success', $attemptedModels['claude-opus-4']['status']);
        $this->assertEquals('failed', $attemptedModels['gpt-4o-latest']['status']);
        $this->assertEquals('API rate limit exceeded', $attemptedModels['gpt-4o-latest']['error']);
    }

    public function test_model_results_relationship(): void
    {
        $jobId = Str::uuid()->toString();

        $job = AnalysisJob::factory()->create([
            'job_id' => $jobId,
            'requested_models' => ['claude-opus-4', 'claude-sonnet-4']
        ]);

        $textAnalysis = TextAnalysis::factory()->create([
            'job_id' => $jobId,
            'text_id' => 'relationship-test',
            'content' => 'Relationship test content'
        ]);

        // Store multiple model results
        $textAnalysis->storeModelResult('claude-opus-4', ['test' => 'data1']);
        $textAnalysis->storeModelResult('claude-sonnet-4', ['test' => 'data2']);

        // Test relationship
        $modelResults = $textAnalysis->modelResults;
        $this->assertCount(2, $modelResults);
        
        $modelKeys = $modelResults->pluck('model_key')->toArray();
        $this->assertContains('claude-opus-4', $modelKeys);
        $this->assertContains('claude-sonnet-4', $modelKeys);

        // Test AnalysisJob relationship
        $jobModelResults = $job->modelResults;
        $this->assertCount(2, $jobModelResults);
    }

    public function test_unique_constraint_enforcement(): void
    {
        $jobId = Str::uuid()->toString();

        $job = AnalysisJob::factory()->create([
            'job_id' => $jobId
        ]);

        $textAnalysis = TextAnalysis::factory()->create([
            'job_id' => $jobId,
            'text_id' => 'unique-test'
        ]);

        // First creation should succeed
        $result1 = $textAnalysis->storeModelResult(
            'claude-opus-4',
            ['test' => 'data1'],
            'claude-3-opus-20240229'
        );

        $this->assertNotNull($result1);

        // Second creation with same key should update, not create new
        $result2 = $textAnalysis->storeModelResult(
            'claude-opus-4',
            ['test' => 'updated_data'],
            'claude-3-opus-20240229',
            15000
        );

        $this->assertEquals($result1->id, $result2->id);
        $this->assertEquals(['test' => 'updated_data'], $result2->annotations);
        $this->assertEquals(15000, $result2->execution_time_ms);

        // Should still be only one record
        $this->assertDatabaseCount('model_results', 1);
    }

    public function test_provider_determination_accuracy(): void
    {
        $jobId = Str::uuid()->toString();

        $job = AnalysisJob::factory()->create(['job_id' => $jobId]);
        $textAnalysis = TextAnalysis::factory()->create([
            'job_id' => $jobId,
            'text_id' => 'provider-test'
        ]);

        $testCases = [
            ['claude-opus-4', 'anthropic'],
            ['claude-sonnet-4', 'anthropic'],
            ['gpt-4o-latest', 'openai'],
            ['gpt-4.1', 'openai'],
            ['gemini-2.5-pro', 'google'],
            ['gemini-2.5-flash', 'google']
        ];

        foreach ($testCases as [$modelKey, $expectedProvider]) {
            $result = $textAnalysis->storeModelResult($modelKey, ['test' => 'data']);
            $this->assertEquals($expectedProvider, $result->provider);
        }
    }
}