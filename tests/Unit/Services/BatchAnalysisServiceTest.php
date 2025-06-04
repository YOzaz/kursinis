<?php

namespace Tests\Unit\Services;

use Tests\TestCase;
use App\Services\BatchAnalysisService;
use App\Services\PromptService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Config;

class BatchAnalysisServiceTest extends TestCase
{
    use RefreshDatabase;

    private BatchAnalysisService $batchService;
    private PromptService $promptService;

    protected function setUp(): void
    {
        parent::setUp();
        
        $this->promptService = $this->createMock(PromptService::class);
        $this->batchService = new BatchAnalysisService($this->promptService);
        
        // Mock config for testing
        Config::set('llm.models.claude-opus-4', [
            'api_key' => 'test-key',
            'base_url' => 'https://api.anthropic.com/v1/',
            'model' => 'claude-opus-4-20250514',
            'max_tokens' => 4096,
            'context_window' => 200000,
            'provider' => 'anthropic',
            'batch_size' => 3, // Small batch for testing
        ]);
    }

    public function test_calculate_optimal_batch_size()
    {
        $texts = [
            ['id' => '1', 'content' => str_repeat('test ', 100)], // ~400 chars
            ['id' => '2', 'content' => str_repeat('text ', 200)], // ~800 chars
            ['id' => '3', 'content' => str_repeat('data ', 150)], // ~600 chars
        ];

        $optimalSize = $this->batchService->calculateOptimalBatchSize($texts, 'claude-opus-4');
        
        // Should calculate based on average text length and context window
        $this->assertIsInt($optimalSize);
        $this->assertGreaterThan(0, $optimalSize);
        $this->assertLessThanOrEqual(3, $optimalSize); // Capped by configured batch_size
    }

    public function test_analyze_batch_success()
    {
        // Mock successful Claude API response
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

        $this->promptService->method('getSystemMessage')->willReturn('System message');

        $texts = [
            ['id' => '1', 'content' => 'Test text 1', 'annotations' => []],
            ['id' => '2', 'content' => 'Test text 2', 'annotations' => []]
        ];

        $results = $this->batchService->analyzeBatch($texts, 'claude-opus-4');

        $this->assertCount(2, $results);
        $this->assertArrayHasKey('1', $results);
        $this->assertArrayHasKey('2', $results);
        $this->assertEquals('yes', $results['1']['primaryChoice']['choices'][0]);
        $this->assertEquals('no', $results['2']['primaryChoice']['choices'][0]);
    }

    public function test_analyze_batch_with_api_error_falls_back()
    {
        // Mock failed batch API response, then successful individual responses
        Http::fake([
            'api.anthropic.com/v1/messages' => Http::sequence()
                ->push([], 500) // First batch request fails
                ->push([ // Individual fallback request 1
                    'content' => [
                        [
                            'text' => json_encode([
                                'primaryChoice' => ['choices' => ['yes']],
                                'annotations' => [],
                                'desinformationTechnique' => ['choices' => []]
                            ])
                        ]
                    ]
                ], 200)
                ->push([ // Individual fallback request 2
                    'content' => [
                        [
                            'text' => json_encode([
                                'primaryChoice' => ['choices' => ['no']],
                                'annotations' => [],
                                'desinformationTechnique' => ['choices' => []]
                            ])
                        ]
                    ]
                ], 200)
        ]);

        $this->promptService->method('getSystemMessage')->willReturn('System message');
        $this->promptService->method('generateAnalysisPrompt')->willReturn('Analysis prompt');
        $this->promptService->method('validateResponse')->willReturn(true);

        // Mock Claude service for fallback
        $claudeService = $this->createMock(\App\Services\ClaudeService::class);
        $claudeService->method('analyzeText')
            ->willReturnOnConsecutiveCalls(
                [
                    'primaryChoice' => ['choices' => ['yes']],
                    'annotations' => [],
                    'desinformationTechnique' => ['choices' => []]
                ],
                [
                    'primaryChoice' => ['choices' => ['no']],
                    'annotations' => [],
                    'desinformationTechnique' => ['choices' => []]
                ]
            );

        $this->app->instance(\App\Services\ClaudeService::class, $claudeService);

        $texts = [
            ['id' => '1', 'content' => 'Test text 1', 'annotations' => []],
            ['id' => '2', 'content' => 'Test text 2', 'annotations' => []]
        ];

        $results = $this->batchService->analyzeBatch($texts, 'claude-opus-4');

        // Should have results despite batch failure
        $this->assertCount(2, $results);
        $this->assertArrayHasKey('1', $results);
        $this->assertArrayHasKey('2', $results);
    }

    public function test_analyze_batch_handles_malformed_json_response()
    {
        // Mock API response with malformed JSON
        Http::fake([
            'api.anthropic.com/*' => Http::response([
                'content' => [
                    [
                        'text' => 'Here is the analysis: ```json [{"text_id": "1", "primaryChoice": {"choices": ["yes"]}, "annotations": []}] ```'
                    ]
                ]
            ], 200)
        ]);

        $this->promptService->method('getSystemMessage')->willReturn('System message');

        $texts = [
            ['id' => '1', 'content' => 'Test text 1', 'annotations' => []]
        ];

        $results = $this->batchService->analyzeBatch($texts, 'claude-opus-4');

        // Should extract JSON from markdown code blocks
        $this->assertCount(1, $results);
        $this->assertArrayHasKey('1', $results);
        $this->assertEquals('yes', $results['1']['primaryChoice']['choices'][0]);
    }

    public function test_analyze_batch_with_different_providers()
    {
        // Test OpenAI configuration
        Config::set('llm.models.gpt-4.1', [
            'api_key' => 'test-openai-key',
            'base_url' => 'https://api.openai.com/v1',
            'model' => 'gpt-4.1',
            'max_tokens' => 4096,
            'context_window' => 1000000,
            'provider' => 'openai',
            'batch_size' => 5,
        ]);

        Http::fake([
            'api.openai.com/*' => Http::response([
                'choices' => [
                    [
                        'message' => [
                            'content' => json_encode([
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
            ], 200)
        ]);

        $this->promptService->method('getSystemMessage')->willReturn('System message');

        $texts = [
            ['id' => '1', 'content' => 'Test text for OpenAI', 'annotations' => []]
        ];

        $results = $this->batchService->analyzeBatch($texts, 'gpt-4.1');

        $this->assertCount(1, $results);
        $this->assertArrayHasKey('1', $results);
    }

    public function test_analyze_batch_with_unsupported_provider()
    {
        Config::set('llm.models.unsupported-model', [
            'provider' => 'unsupported',
            'batch_size' => 10,
        ]);

        $texts = [
            ['id' => '1', 'content' => 'Test text', 'annotations' => []]
        ];

        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('Unsupported provider: unsupported');

        $this->batchService->analyzeBatch($texts, 'unsupported-model');
    }

    public function test_batch_processing_logs_performance_metrics()
    {
        Log::shouldReceive('info')
            ->with('Starting batch analysis', \Mockery::type('array'))
            ->once();

        Log::shouldReceive('info')
            ->with('Batch processed successfully', \Mockery::type('array'))
            ->once();

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
            ], 200)
        ]);

        $this->promptService->method('getSystemMessage')->willReturn('System message');

        $texts = [
            ['id' => '1', 'content' => 'Test text', 'annotations' => []]
        ];

        $this->batchService->analyzeBatch($texts, 'claude-opus-4');
    }

    public function test_empty_batch_handling()
    {
        $results = $this->batchService->analyzeBatch([], 'claude-opus-4');
        
        $this->assertEmpty($results);
    }

    public function test_single_text_batch()
    {
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
            ], 200)
        ]);

        $this->promptService->method('getSystemMessage')->willReturn('System message');

        $texts = [
            ['id' => '1', 'content' => 'Single test text', 'annotations' => []]
        ];

        $results = $this->batchService->analyzeBatch($texts, 'claude-opus-4');

        $this->assertCount(1, $results);
        $this->assertArrayHasKey('1', $results);
    }
}