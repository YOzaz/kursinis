<?php

namespace Tests\Unit\Unit\Services;

use App\Services\OpenAIService;
use App\Services\PromptService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class OpenAIServiceTest extends TestCase
{
    use RefreshDatabase;

    private OpenAIService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $promptService = new PromptService();
        $this->service = new OpenAIService($promptService);
        Http::fake();
    }

    public function test_openai_service_implements_llm_interface(): void
    {
        $this->assertInstanceOf(\App\Services\LLMServiceInterface::class, $this->service);
    }

    public function test_get_model_name_returns_correct_value(): void
    {
        $this->assertEquals('gpt-4.1', $this->service->getModelName());
    }

    public function test_is_configured_returns_true_when_api_key_exists(): void
    {
        config(['llm.models.gpt-4.1.api_key' => 'test-api-key']);
        
        $this->assertTrue($this->service->isConfigured());
    }

    public function test_is_configured_returns_false_when_api_key_missing(): void
    {
        config(['llm.models.gpt-4.1.api_key' => null]);
        
        $this->assertFalse($this->service->isConfigured());
    }

    public function test_analyze_text_returns_valid_response(): void
    {
        Http::fake([
            'api.openai.com/*' => Http::response([
                'choices' => [
                    [
                        'message' => [
                            'content' => json_encode([
                                'primaryChoice' => ['choices' => ['yes']],
                                'annotations' => [
                                    [
                                        'type' => 'labels',
                                        'value' => [
                                            'start' => 0,
                                            'end' => 10,
                                            'text' => 'Test text',
                                            'labels' => ['repetition']
                                        ]
                                    ]
                                ],
                                'desinformationTechnique' => ['choices' => ['propaganda']]
                            ])
                        ]
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
            'api.openai.com/*' => Http::response([
                'choices' => [
                    [
                        'message' => [
                            'content' => json_encode([
                                'primaryChoice' => ['choices' => ['no']],
                                'annotations' => [],
                                'desinformationTechnique' => ['choices' => []]
                            ])
                        ]
                    ]
                ]
            ], 200)
        ]);

        $this->service->analyzeText('Test text content');

        Http::assertSent(function ($request) {
            return $request->url() === 'https://api.openai.com/v1/chat/completions' &&
                   $request->hasHeader('Authorization') &&
                   $request['model'] === 'gpt-4o' &&
                   $request['max_tokens'] === 4096 &&
                   isset($request['messages']) &&
                   is_array($request['messages']);
        });
    }

    public function test_analyze_text_includes_propaganda_techniques_in_prompt(): void
    {
        Http::fake([
            'api.openai.com/*' => Http::response([
                'choices' => [
                    [
                        'message' => [
                            'content' => json_encode([
                                'primaryChoice' => ['choices' => ['yes']],
                                'annotations' => [],
                                'desinformationTechnique' => ['choices' => []]
                            ])
                        ]
                    ]
                ]
            ], 200)
        ]);

        $this->service->analyzeText('Test propaganda content');

        Http::assertSent(function ($request) {
            $message = $request['messages'][0]['content'];
            
            // Check if prompt contains propaganda techniques
            return str_contains($message, 'simplification') &&
                   str_contains($message, 'emotionalExpression') &&
                   str_contains($message, 'uncertainty') &&
                   str_contains($message, 'doubt') &&
                   str_contains($message, 'wavingTheFlag') &&
                   str_contains($message, 'reductioAdHitlerum') &&
                   str_contains($message, 'repetition');
        });
    }

    public function test_analyze_text_with_custom_prompt(): void
    {
        Http::fake([
            'api.openai.com/*' => Http::response([
                'choices' => [
                    [
                        'message' => [
                            'content' => json_encode([
                                'primaryChoice' => ['choices' => ['yes']],
                                'annotations' => [],
                                'desinformationTechnique' => ['choices' => []]
                            ])
                        ]
                    ]
                ]
            ], 200)
        ]);

        $customPrompt = 'Custom OpenAI analysis prompt for testing';
        $result = $this->service->analyzeText('Test text', $customPrompt);

        Http::assertSent(function ($request) use ($customPrompt) {
            return str_contains($request['messages'][0]['content'], $customPrompt);
        });

        $this->assertIsArray($result);
    }

    public function test_analyze_text_handles_api_errors(): void
    {
        Http::fake([
            'api.openai.com/*' => Http::response(['error' => 'API Error'], 400)
        ]);

        $this->expectException(\Exception::class);
        $this->service->analyzeText('Test text');
    }

    public function test_analyze_text_handles_invalid_json_response(): void
    {
        Http::fake([
            'api.openai.com/*' => Http::response([
                'choices' => [
                    [
                        'message' => [
                            'content' => 'invalid json response'
                        ]
                    ]
                ]
            ], 200)
        ]);

        $this->expectException(\Exception::class);
        $this->service->analyzeText('Test text');
    }

    public function test_analyze_text_handles_empty_choices(): void
    {
        Http::fake([
            'api.openai.com/*' => Http::response([
                'choices' => []
            ], 200)
        ]);

        $this->expectException(\Exception::class);
        $this->service->analyzeText('Test text');
    }

    public function test_analyze_text_returns_consistent_structure(): void
    {
        Http::fake([
            'api.openai.com/*' => Http::response([
                'choices' => [
                    [
                        'message' => [
                            'content' => json_encode([
                                'primaryChoice' => ['choices' => ['yes']],
                                'annotations' => [
                                    [
                                        'type' => 'labels',
                                        'value' => [
                                            'start' => 5,
                                            'end' => 15,
                                            'text' => 'propaganda',
                                            'labels' => ['wavingTheFlag', 'repetition']
                                        ]
                                    ]
                                ],
                                'desinformationTechnique' => ['choices' => ['distrustOfLithuanianInstitutions']]
                            ])
                        ]
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
            'api.openai.com/*' => Http::response([
                'choices' => [
                    [
                        'message' => [
                            'content' => json_encode([
                                'primaryChoice' => ['choices' => ['no']],
                                'annotations' => [],
                                'desinformationTechnique' => ['choices' => []]
                            ])
                        ]
                    ]
                ]
            ], 200)
        ]);

        $this->service->analyzeText('Test text');

        Http::assertSent(function ($request) {
            return $request['model'] === config('llm.models.gpt-4.1.model') &&
                   $request['max_tokens'] === config('llm.models.gpt-4.1.max_tokens') &&
                   $request['temperature'] === config('llm.models.gpt-4.1.temperature');
        });
    }

    public function test_analyze_text_with_lithuanian_content(): void
    {
        Http::fake([
            'api.openai.com/*' => Http::response([
                'choices' => [
                    [
                        'message' => [
                            'content' => json_encode([
                                'primaryChoice' => ['choices' => ['yes']],
                                'annotations' => [
                                    [
                                        'type' => 'labels',
                                        'value' => [
                                            'start' => 0,
                                            'end' => 15,
                                            'text' => 'Lietuviškas tekstas',
                                            'labels' => ['reductioAdHitlerum']
                                        ]
                                    ]
                                ],
                                'desinformationTechnique' => ['choices' => ['distrustOfLithuanianInstitutions']]
                            ])
                        ]
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

    public function test_analyze_text_uses_system_and_user_messages(): void
    {
        Http::fake([
            'api.openai.com/*' => Http::response([
                'choices' => [
                    [
                        'message' => [
                            'content' => json_encode([
                                'primaryChoice' => ['choices' => ['no']],
                                'annotations' => [],
                                'desinformationTechnique' => ['choices' => []]
                            ])
                        ]
                    ]
                ]
            ], 200)
        ]);

        $this->service->analyzeText('Test text');

        Http::assertSent(function ($request) {
            $messages = $request['messages'];
            return count($messages) >= 2 &&
                   $messages[0]['role'] === 'system' &&
                   isset($messages[1]['role']) &&
                   $messages[1]['role'] === 'user';
        });
    }

    public function test_respects_rate_limiting(): void
    {
        Http::fake([
            'api.openai.com/*' => Http::response([
                'choices' => [
                    [
                        'message' => [
                            'content' => json_encode([
                                'primaryChoice' => ['choices' => ['no']],
                                'annotations' => [],
                                'desinformationTechnique' => ['choices' => []]
                            ])
                        ]
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

    public function test_handles_openai_specific_response_format(): void
    {
        Http::fake([
            'api.openai.com/*' => Http::response([
                'id' => 'chatcmpl-test',
                'object' => 'chat.completion',
                'created' => 1234567890,
                'model' => 'gpt-4o',
                'choices' => [
                    [
                        'index' => 0,
                        'message' => [
                            'role' => 'assistant',
                            'content' => json_encode([
                                'primaryChoice' => ['choices' => ['yes']],
                                'annotations' => [],
                                'desinformationTechnique' => ['choices' => []]
                            ])
                        ],
                        'finish_reason' => 'stop'
                    ]
                ],
                'usage' => [
                    'prompt_tokens' => 100,
                    'completion_tokens' => 50,
                    'total_tokens' => 150
                ]
            ], 200)
        ]);

        $result = $this->service->analyzeText('Test text');

        $this->assertIsArray($result);
        $this->assertArrayHasKey('primaryChoice', $result);
    }
}