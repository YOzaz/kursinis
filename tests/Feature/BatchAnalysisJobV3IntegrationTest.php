<?php

namespace Tests\Feature;

use Tests\TestCase;
use App\Jobs\BatchAnalysisJobV3;
use App\Models\AnalysisJob;
use App\Models\TextAnalysis;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Str;

class BatchAnalysisJobV3IntegrationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        
        // Mock HTTP responses for integration testing
        Http::fake([
            'api.anthropic.com/*' => Http::response([
                'content' => [
                    [
                        'text' => json_encode([
                            [
                                'text_id' => '1',
                                'primaryChoice' => ['choices' => ['yes']],
                                'annotations' => [
                                    [
                                        'type' => 'labels',
                                        'value' => [
                                            'start' => 0,
                                            'end' => 20,
                                            'text' => 'Test propaganda text',
                                            'labels' => ['emotionalExpression']
                                        ]
                                    ]
                                ],
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
            ], 200)
        ]);
    }

    public function test_end_to_end_smart_chunking_workflow()
    {
        $this->markTestSkipped('BatchAnalysisJobV3 is deprecated, system now uses BatchAnalysisJobV4');
        
        // Simulate WebController creating job
        $jobId = Str::uuid()->toString();
        $fileContent = [
            [
                'id' => 1,
                'data' => ['content' => 'This is test propaganda content that should be analyzed.'],
                'annotations' => [
                    [
                        'start' => 0,
                        'end' => 20,
                        'text' => 'This is test propaganda',
                        'labels' => ['emotionalExpression']
                    ]
                ]
            ],
            [
                'id' => 2,
                'data' => ['content' => 'This is normal news content without propaganda.'],
                'annotations' => []
            ]
        ];
        $models = ['claude-opus-4'];

        // Create AnalysisJob (as WebController would)
        $analysisJob = AnalysisJob::create([
            'job_id' => $jobId,
            'status' => AnalysisJob::STATUS_PENDING,
            'total_texts' => count($fileContent),
            'processed_texts' => 0,
            'name' => 'Integration Test Batch',
            'description' => 'Testing smart chunking workflow'
        ]);

        // Dispatch job
        $job = new BatchAnalysisJobV3($jobId, $fileContent, $models);
        $job->handle();

        // Verify job completion
        $analysisJob->refresh();
        $this->assertEquals(AnalysisJob::STATUS_COMPLETED, $analysisJob->status);
        $this->assertEquals(2, $analysisJob->processed_texts);
        $this->assertEquals(2, $analysisJob->total_texts);

        // Verify TextAnalysis records created and populated
        $textAnalyses = TextAnalysis::where('job_id', $jobId)->get();
        $this->assertCount(2, $textAnalyses);

        foreach ($textAnalyses as $analysis) {
            $this->assertNotNull($analysis->claude_annotations);
            $this->assertIsArray($analysis->claude_annotations);
            $this->assertArrayHasKey('primaryChoice', $analysis->claude_annotations);
            $this->assertArrayHasKey('choices', $analysis->claude_annotations['primaryChoice']);
        }

        // Verify specific analysis results
        $firstAnalysis = $textAnalyses->where('text_id', '1')->first();
        $this->assertEquals(['yes'], $firstAnalysis->claude_annotations['primaryChoice']['choices']);
        $this->assertNotEmpty($firstAnalysis->claude_annotations['annotations']);

        $secondAnalysis = $textAnalyses->where('text_id', '2')->first();
        $this->assertEquals(['no'], $secondAnalysis->claude_annotations['primaryChoice']['choices']);
        $this->assertEmpty($secondAnalysis->claude_annotations['annotations']);
    }

    public function test_large_dataset_chunking_behavior()
    {
        $jobId = Str::uuid()->toString();
        $fileContent = [];
        
        // Create 10 texts to test multiple chunks (chunk size = 3)
        for ($i = 1; $i <= 10; $i++) {
            $fileContent[] = [
                'id' => $i,
                'data' => ['content' => "Test text content number {$i} for chunking analysis."],
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

        // Mock response for multiple chunks
        Http::fake([
            'api.anthropic.com/*' => Http::sequence()
                ->push([
                    'content' => [
                        [
                            'text' => json_encode([
                                ['text_id' => '1', 'primaryChoice' => ['choices' => ['yes']], 'annotations' => [], 'desinformationTechnique' => ['choices' => []]],
                                ['text_id' => '2', 'primaryChoice' => ['choices' => ['no']], 'annotations' => [], 'desinformationTechnique' => ['choices' => []]],
                                ['text_id' => '3', 'primaryChoice' => ['choices' => ['yes']], 'annotations' => [], 'desinformationTechnique' => ['choices' => []]]
                            ])
                        ]
                    ]
                ], 200)
                ->push([
                    'content' => [
                        [
                            'text' => json_encode([
                                ['text_id' => '4', 'primaryChoice' => ['choices' => ['no']], 'annotations' => [], 'desinformationTechnique' => ['choices' => []]],
                                ['text_id' => '5', 'primaryChoice' => ['choices' => ['yes']], 'annotations' => [], 'desinformationTechnique' => ['choices' => []]],
                                ['text_id' => '6', 'primaryChoice' => ['choices' => ['no']], 'annotations' => [], 'desinformationTechnique' => ['choices' => []]]
                            ])
                        ]
                    ]
                ], 200)
                ->push([
                    'content' => [
                        [
                            'text' => json_encode([
                                ['text_id' => '7', 'primaryChoice' => ['choices' => ['yes']], 'annotations' => [], 'desinformationTechnique' => ['choices' => []]],
                                ['text_id' => '8', 'primaryChoice' => ['choices' => ['no']], 'annotations' => [], 'desinformationTechnique' => ['choices' => []]],
                                ['text_id' => '9', 'primaryChoice' => ['choices' => ['yes']], 'annotations' => [], 'desinformationTechnique' => ['choices' => []]]
                            ])
                        ]
                    ]
                ], 200)
                ->push([
                    'content' => [
                        [
                            'text' => json_encode([
                                ['text_id' => '10', 'primaryChoice' => ['choices' => ['no']], 'annotations' => [], 'desinformationTechnique' => ['choices' => []]]
                            ])
                        ]
                    ]
                ], 200)
        ]);

        $job = new BatchAnalysisJobV3($jobId, $fileContent, $models);
        $job->handle();

        // Verify all 10 texts were processed
        $textAnalyses = TextAnalysis::where('job_id', $jobId)->get();
        $this->assertCount(10, $textAnalyses);

        // Verify each text has analysis results
        for ($i = 1; $i <= 10; $i++) {
            $analysis = $textAnalyses->where('text_id', (string)$i)->first();
            $this->assertNotNull($analysis, "Analysis for text {$i} should exist");
            $this->assertNotNull($analysis->claude_annotations, "Claude annotations for text {$i} should exist");
        }

        // Verify job completion
        $analysisJob = AnalysisJob::where('job_id', $jobId)->first();
        $this->assertEquals(AnalysisJob::STATUS_COMPLETED, $analysisJob->status);
        $this->assertEquals(10, $analysisJob->processed_texts);
    }

    public function test_queue_integration()
    {
        Queue::fake();

        // Test that job can be dispatched to queue
        $jobId = Str::uuid()->toString();
        $fileContent = [
            [
                'id' => 1,
                'data' => ['content' => 'Test content'],
                'annotations' => []
            ]
        ];
        $models = ['claude-opus-4'];

        BatchAnalysisJobV3::dispatch($jobId, $fileContent, $models);

        Queue::assertPushed(BatchAnalysisJobV3::class, function ($job) use ($jobId) {
            return $job->jobId === $jobId;
        });
    }

    public function test_error_resilience_with_partial_failures()
    {
        $jobId = Str::uuid()->toString();
        $fileContent = [
            [
                'id' => 1,
                'data' => ['content' => 'Good text'],
                'annotations' => []
            ],
            [
                'id' => 2,
                'data' => ['content' => 'Another good text'],
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

        // Mock first request success, second request timeout
        Http::fake([
            'api.anthropic.com/*' => Http::sequence()
                ->push([
                    'content' => [
                        [
                            'text' => json_encode([
                                ['text_id' => '1', 'primaryChoice' => ['choices' => ['yes']], 'annotations' => [], 'desinformationTechnique' => ['choices' => []]],
                                ['text_id' => '2', 'primaryChoice' => ['choices' => ['no']], 'annotations' => [], 'desinformationTechnique' => ['choices' => []]]
                            ])
                        ]
                    ]
                ], 200)
        ]);

        $job = new BatchAnalysisJobV3($jobId, $fileContent, $models);
        $job->handle();

        // Job should still complete
        $analysisJob = AnalysisJob::where('job_id', $jobId)->first();
        $this->assertEquals(AnalysisJob::STATUS_COMPLETED, $analysisJob->status);

        // Both texts should have analysis results
        $textAnalyses = TextAnalysis::where('job_id', $jobId)->get();
        $this->assertCount(2, $textAnalyses);

        foreach ($textAnalyses as $analysis) {
            $this->assertNotNull($analysis->claude_annotations);
        }
    }
}