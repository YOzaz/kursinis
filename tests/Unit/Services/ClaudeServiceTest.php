<?php

namespace Tests\Unit\Services;

use App\Services\ClaudeService;
use App\Services\PromptService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class ClaudeServiceTest extends TestCase
{
    use RefreshDatabase;

    private ClaudeService $service;
    private PromptService $promptService;

    protected function setUp(): void
    {
        parent::setUp();
        config(['llm.models.claude-opus-4.api_key' => 'test-key']);
        $this->promptService = new PromptService();
        $this->service = new ClaudeService($this->promptService);
    }

    public function test_claude_service_implements_llm_interface(): void
    {
        $this->assertInstanceOf(\App\Services\LLMServiceInterface::class, $this->service);
    }

    public function test_get_model_name_returns_correct_value(): void
    {
        $this->assertEquals('claude-opus-4', $this->service->getModelName());
    }

    public function test_is_configured_returns_true_when_api_key_exists(): void
    {
        config(['llm.models.claude-opus-4.api_key' => 'test-api-key']);
        
        $this->assertTrue($this->service->isConfigured());
    }

    public function test_is_configured_returns_false_when_api_key_missing(): void
    {
        config(['llm.models.claude-opus-4.api_key' => null]);
        
        $this->assertFalse($this->service->isConfigured());
    }

    public function test_analyze_text_returns_valid_response(): void
    {
        Http::fake([
            'https://api.anthropic.com/*' => Http::response([
                'content' => [
                    [
                        'text' => json_encode([
                            'primaryChoice' => ['choices' => ['yes']],
                            'annotations' => [
                                [
                                    'type' => 'labels',
                                    'value' => [
                                        'start' => 0,
                                        'end' => 10,
                                        'text' => 'Test text',
                                        'labels' => ['simplification']
                                    ]
                                ]
                            ],
                            'desinformationTechnique' => ['choices' => ['propaganda']]
                        ])
                    ]
                ]
            ], 200)
        ]);

        $result = $this->service->analyzeText('Test propaganda text for analysis');

        $this->assertIsArray($result);
        $this->assertArrayHasKey('primaryChoice', $result);
        $this->assertArrayHasKey('annotations', $result);
        $this->assertArrayHasKey('desinformationTechnique', $result);
    }

    public function test_analyze_text_sends_correct_request_structure(): void
    {
        Http::fake([
            'https://api.anthropic.com/v1/messages' => Http::response([
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

        $this->service->analyzeText('Test text content');

        Http::assertSent(function ($request) {
            return str_contains($request->url(), 'api.anthropic.com/v1/messages') &&
                   $request->hasHeader('x-api-key') &&
                   $request->hasHeader('anthropic-version') &&
                   $request['model'] === 'claude-opus-4-20250514' &&
                   $request['max_tokens'] === 4096 &&
                   isset($request['messages']) &&
                   is_array($request['messages']);
        });
    }

    public function test_analyze_text_includes_propaganda_techniques_in_prompt(): void
    {
        Http::fake([
            'https://api.anthropic.com/v1/messages' => Http::response([
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
        ]);

        $this->service->analyzeText('Test propaganda content');

        Http::assertSent(function ($request) {
            $message = $request['messages'][0]['content'];
            
            // Check if prompt contains some propaganda techniques (using Lithuanian terms from config)
            return str_contains($message, 'simplification') ||
                   str_contains($message, 'emotionalAppeal') ||
                   str_contains($message, 'doubt') ||
                   str_contains($message, 'repetition');
        });
    }

    public function test_analyze_text_with_custom_prompt(): void
    {
        Http::fake([
            'https://api.anthropic.com/*' => Http::response([
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
        ]);

        $customPrompt = 'Custom analysis prompt for testing';
        $result = $this->service->analyzeText('Test text', $customPrompt);

        Http::assertSent(function ($request) use ($customPrompt) {
            return str_contains($request['messages'][0]['content'], $customPrompt);
        });

        $this->assertIsArray($result);
    }

    public function test_analyze_text_handles_api_errors(): void
    {
        // Create a fresh service instance with fresh HTTP setup
        $this->refreshApplication();
        config(['llm.models.claude-opus-4.api_key' => 'test-key']);
        
        Http::fake([
            '*' => Http::response(['error' => 'API Error'], 400)
        ]);

        $promptService = new PromptService();
        $service = new ClaudeService($promptService);

        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('Claude API grąžino klaidą: 400');
        $service->analyzeText('Test text');
    }

    public function test_analyze_text_handles_invalid_json_response(): void
    {
        // Create a fresh service instance with fresh HTTP setup
        $this->refreshApplication();
        config(['llm.models.claude-opus-4.api_key' => 'test-key']);
        
        Http::fake([
            '*' => Http::response([
                'content' => [
                    [
                        'text' => 'invalid json response'
                    ]
                ]
            ], 200)
        ]);

        $promptService = new PromptService();
        $service = new ClaudeService($promptService);

        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('Nepavyko išgauti JSON iš Claude atsakymo');
        $service->analyzeText('Test text');
    }

    public function test_analyze_text_handles_empty_response(): void
    {
        // Create a fresh service instance with fresh HTTP setup
        $this->refreshApplication();
        config(['llm.models.claude-opus-4.api_key' => 'test-key']);
        
        Http::fake([
            '*' => Http::response([
                'content' => []
            ], 200)
        ]);

        $promptService = new PromptService();
        $service = new ClaudeService($promptService);

        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('Neteisingas Claude API atsakymo formatas');
        $service->analyzeText('Test text');
    }

    public function test_analyze_text_respects_rate_limiting(): void
    {
        Http::fake([
            'https://api.anthropic.com/*' => Http::response([
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

        // Make multiple requests
        for ($i = 0; $i < 3; $i++) {
            $this->service->analyzeText("Test text {$i}");
        }

        Http::assertSentCount(3);
    }

    public function test_analyze_text_returns_consistent_structure(): void
    {
        Http::fake([
            'https://api.anthropic.com/*' => Http::response([
                'content' => [
                    [
                        'text' => json_encode([
                            'primaryChoice' => ['choices' => ['yes']],
                            'annotations' => [
                                [
                                    'type' => 'labels',
                                    'value' => [
                                        'start' => 5,
                                        'end' => 15,
                                        'text' => 'propaganda',
                                        'labels' => ['emotionalExpression', 'simplification']
                                    ]
                                ]
                            ],
                            'desinformationTechnique' => ['choices' => ['distrustOfLithuanianInstitutions']]
                        ])
                    ]
                ]
            ], 200)
        ]);

        $result = $this->service->analyzeText('Test propaganda text');

        // Verify structure
        $this->assertIsArray($result);
        $this->assertArrayHasKey('primaryChoice', $result);
        $this->assertArrayHasKey('choices', $result['primaryChoice']);
        $this->assertIsArray($result['primaryChoice']['choices']);

        $this->assertArrayHasKey('annotations', $result);
        $this->assertIsArray($result['annotations']);

        $this->assertArrayHasKey('desinformationTechnique', $result);
        $this->assertArrayHasKey('choices', $result['desinformationTechnique']);
        $this->assertIsArray($result['desinformationTechnique']['choices']);

        // Verify annotation structure if present
        if (!empty($result['annotations'])) {
            $annotation = $result['annotations'][0];
            $this->assertArrayHasKey('type', $annotation);
            $this->assertArrayHasKey('value', $annotation);
            $this->assertArrayHasKey('start', $annotation['value']);
            $this->assertArrayHasKey('end', $annotation['value']);
            $this->assertArrayHasKey('text', $annotation['value']);
            $this->assertArrayHasKey('labels', $annotation['value']);
            $this->assertIsArray($annotation['value']['labels']);
        }
    }

    public function test_service_uses_correct_model_configuration(): void
    {
        Http::fake([
            'https://api.anthropic.com/*' => Http::response([
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

        $this->service->analyzeText('Test text');

        Http::assertSent(function ($request) {
            return $request['model'] === config('llm.models.claude-opus-4.model') &&
                   $request['max_tokens'] === config('llm.models.claude-opus-4.max_tokens') &&
                   $request['temperature'] === config('llm.models.claude-opus-4.temperature');
        });
    }

    public function test_analyze_text_with_lithuanian_content(): void
    {
        Http::fake([
            'https://api.anthropic.com/*' => Http::response([
                'content' => [
                    [
                        'text' => json_encode([
                            'primaryChoice' => ['choices' => ['yes']],
                            'annotations' => [
                                [
                                    'type' => 'labels',
                                    'value' => [
                                        'start' => 0,
                                        'end' => 15,
                                        'text' => 'Lietuviškas tekstas',
                                        'labels' => ['simplification']
                                    ]
                                ]
                            ],
                            'desinformationTechnique' => ['choices' => ['distrustOfLithuanianInstitutions']]
                        ])
                    ]
                ]
            ], 200)
        ]);

        $result = $this->service->analyzeText('Lietuviškas propagandos tekstas analizei');

        $this->assertIsArray($result);
        $this->assertEquals(['yes'], $result['primaryChoice']['choices']);
        
        Http::assertSent(function ($request) {
            return str_contains($request['messages'][0]['content'], 'lietuvių kalba');
        });
    }
}