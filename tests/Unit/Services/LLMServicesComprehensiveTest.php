<?php

namespace Tests\Unit\Services;

use App\Services\ClaudeService;
use App\Services\GeminiService;
use App\Services\OpenAIService;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class LLMServicesComprehensiveTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        
        // Set up test configuration
        config([
            'llm.models.claude-opus-4' => [
                'api_key' => 'test-claude-key',
                'base_url' => 'https://api.anthropic.com/v1/',
                'model' => 'claude-opus-4-20250514',
                'max_tokens' => 4096,
                'temperature' => 0.05,
                'provider' => 'anthropic'
            ],
            'llm.models.gemini-2.5-pro' => [
                'api_key' => 'test-gemini-key',
                'base_url' => 'https://generativelanguage.googleapis.com/',
                'model' => 'gemini-2.5-pro-experimental',
                'max_tokens' => 4096,
                'temperature' => 0.05,
                'provider' => 'google'
            ],
            'llm.models.gpt-4.1' => [
                'api_key' => 'test-openai-key',
                'base_url' => 'https://api.openai.com/v1',
                'model' => 'gpt-4.1',
                'max_tokens' => 4096,
                'temperature' => 0.05,
                'provider' => 'openai'
            ]
        ]);
    }

    public function test_claude_service_analyzes_text_successfully()
    {
        Http::fake([
            'api.anthropic.com/*' => Http::response([
                'content' => [
                    [
                        'type' => 'text',
                        'text' => json_encode([
                            [
                                'type' => 'labels',
                                'value' => [
                                    'start' => 0,
                                    'end' => 10,
                                    'text' => 'Test text',
                                    'labels' => ['emotionalExpression']
                                ]
                            ]
                        ])
                    ]
                ],
                'usage' => [
                    'input_tokens' => 100,
                    'output_tokens' => 50
                ]
            ], 200)
        ]);

        $service = $this->app->make(ClaudeService::class);
        $result = $service->analyzeText('Test propaganda text', 'claude-opus-4', 'Test prompt');

        $this->assertIsArray($result);
        $this->assertCount(1, $result);
        $this->assertEquals('labels', $result[0]['type']);
        $this->assertEquals('emotionalExpression', $result[0]['value']['labels'][0]);
        
        Http::assertSent(function ($request) {
            return str_contains($request->url(), 'api.anthropic.com') &&
                   $request->header('Authorization')[0] === 'Bearer test-claude-key' &&
                   $request->header('anthropic-version')[0] === '2023-06-01';
        });
    }

    public function test_gemini_service_analyzes_text_successfully()
    {
        Http::fake([
            'generativelanguage.googleapis.com/*' => Http::response([
                'candidates' => [
                    [
                        'content' => [
                            'parts' => [
                                [
                                    'text' => json_encode([
                                        [
                                            'type' => 'labels',
                                            'value' => [
                                                'start' => 0,
                                                'end' => 10,
                                                'text' => 'Test text',
                                                'labels' => ['simplification']
                                            ]
                                        ]
                                    ])
                                ]
                            ]
                        ]
                    ]
                ],
                'usageMetadata' => [
                    'promptTokenCount' => 100,
                    'candidatesTokenCount' => 50
                ]
            ], 200)
        ]);

        $service = $this->app->make(GeminiService::class);
        $result = $service->analyzeText('Test propaganda text', 'gemini-2.5-pro', 'Test prompt');

        $this->assertIsArray($result);
        $this->assertCount(1, $result);
        $this->assertEquals('labels', $result[0]['type']);
        $this->assertEquals('simplification', $result[0]['value']['labels'][0]);
        
        Http::assertSent(function ($request) {
            return str_contains($request->url(), 'generativelanguage.googleapis.com') &&
                   str_contains($request->url(), 'key=test-gemini-key');
        });
    }

    public function test_openai_service_analyzes_text_successfully()
    {
        Http::fake([
            'api.openai.com/*' => Http::response([
                'choices' => [
                    [
                        'message' => [
                            'content' => json_encode([
                                [
                                    'type' => 'labels',
                                    'value' => [
                                        'start' => 0,
                                        'end' => 10,
                                        'text' => 'Test text',
                                        'labels' => ['appealToAuthority']
                                    ]
                                ]
                            ])
                        ]
                    ]
                ],
                'usage' => [
                    'prompt_tokens' => 100,
                    'completion_tokens' => 50
                ]
            ], 200)
        ]);

        $service = $this->app->make(OpenAIService::class);
        $result = $service->analyzeText('Test propaganda text', 'gpt-4.1', 'Test prompt');

        $this->assertIsArray($result);
        $this->assertCount(1, $result);
        $this->assertEquals('labels', $result[0]['type']);
        $this->assertEquals('appealToAuthority', $result[0]['value']['labels'][0]);
        
        Http::assertSent(function ($request) {
            return str_contains($request->url(), 'api.openai.com') &&
                   $request->header('Authorization')[0] === 'Bearer test-openai-key';
        });
    }

    public function test_services_handle_api_errors_gracefully()
    {
        Http::fake([
            '*' => Http::response([], 500)
        ]);

        $claudeService = $this->app->make(ClaudeService::class);
        $geminiService = $this->app->make(GeminiService::class);
        $openaiService = $this->app->make(OpenAIService::class);

        $this->expectException(RequestException::class);
        $claudeService->analyzeText('Test text', 'claude-opus-4', 'Test prompt');

        $this->expectException(RequestException::class);
        $geminiService->analyzeText('Test text', 'gemini-2.5-pro', 'Test prompt');

        $this->expectException(RequestException::class);
        $openaiService->analyzeText('Test text', 'gpt-4.1', 'Test prompt');
    }

    public function test_services_handle_invalid_json_responses()
    {
        Http::fake([
            'api.anthropic.com/*' => Http::response([
                'content' => [
                    [
                        'type' => 'text',
                        'text' => 'Invalid JSON response'
                    ]
                ]
            ], 200),
            'generativelanguage.googleapis.com/*' => Http::response([
                'candidates' => [
                    [
                        'content' => [
                            'parts' => [
                                [
                                    'text' => 'Invalid JSON response'
                                ]
                            ]
                        ]
                    ]
                ]
            ], 200),
            'api.openai.com/*' => Http::response([
                'choices' => [
                    [
                        'message' => [
                            'content' => 'Invalid JSON response'
                        ]
                    ]
                ]
            ], 200)
        ]);

        $claudeService = $this->app->make(ClaudeService::class);
        $geminiService = $this->app->make(GeminiService::class);
        $openaiService = $this->app->make(OpenAIService::class);

        // Services should handle invalid JSON gracefully and return empty array or throw specific exception
        $claudeResult = $claudeService->analyzeText('Test text', 'claude-opus-4', 'Test prompt');
        $geminiResult = $geminiService->analyzeText('Test text', 'gemini-2.5-pro', 'Test prompt');
        $openaiResult = $openaiService->analyzeText('Test text', 'gpt-4.1', 'Test prompt');

        // The exact behavior depends on implementation, but should not crash
        $this->assertTrue(is_array($claudeResult) || is_null($claudeResult));
        $this->assertTrue(is_array($geminiResult) || is_null($geminiResult));
        $this->assertTrue(is_array($openaiResult) || is_null($openaiResult));
    }

    public function test_services_use_correct_model_configurations()
    {
        Http::fake();

        $claudeService = $this->app->make(ClaudeService::class);
        $geminiService = $this->app->make(GeminiService::class);
        $openaiService = $this->app->make(OpenAIService::class);

        // Test that services use the correct configuration
        try {
            $claudeService->analyzeText('Test text', 'claude-opus-4', 'Test prompt');
        } catch (\Exception $e) {
            // Expected to fail, but should use correct config
        }

        try {
            $geminiService->analyzeText('Test text', 'gemini-2.5-pro', 'Test prompt');
        } catch (\Exception $e) {
            // Expected to fail, but should use correct config
        }

        try {
            $openaiService->analyzeText('Test text', 'gpt-4.1', 'Test prompt');
        } catch (\Exception $e) {
            // Expected to fail, but should use correct config
        }

        // Verify correct API endpoints were called
        Http::assertSent(function ($request) {
            return str_contains($request->url(), 'api.anthropic.com');
        });

        Http::assertSent(function ($request) {
            return str_contains($request->url(), 'generativelanguage.googleapis.com');
        });

        Http::assertSent(function ($request) {
            return str_contains($request->url(), 'api.openai.com');
        });
    }

    public function test_services_implement_llm_service_interface()
    {
        $claudeService = $this->app->make(ClaudeService::class);
        $geminiService = $this->app->make(GeminiService::class);
        $openaiService = $this->app->make(OpenAIService::class);

        $this->assertInstanceOf(\App\Services\LLMServiceInterface::class, $claudeService);
        $this->assertInstanceOf(\App\Services\LLMServiceInterface::class, $geminiService);
        $this->assertInstanceOf(\App\Services\LLMServiceInterface::class, $openaiService);

        // Check that all required methods exist
        $requiredMethods = ['analyzeText'];
        
        foreach ($requiredMethods as $method) {
            $this->assertTrue(method_exists($claudeService, $method));
            $this->assertTrue(method_exists($geminiService, $method));
            $this->assertTrue(method_exists($openaiService, $method));
        }
    }

    public function test_services_handle_custom_prompts()
    {
        Http::fake([
            '*' => Http::response([
                'content' => [['type' => 'text', 'text' => '[]']],
                'choices' => [['message' => ['content' => '[]']]],
                'candidates' => [['content' => ['parts' => [['text' => '[]']]]]]
            ], 200)
        ]);

        $customPrompt = 'Custom propaganda analysis prompt with specific instructions';

        $claudeService = $this->app->make(ClaudeService::class);
        $geminiService = $this->app->make(GeminiService::class);
        $openaiService = $this->app->make(OpenAIService::class);

        $claudeService->analyzeText('Test text', 'claude-opus-4', $customPrompt);
        $geminiService->analyzeText('Test text', 'gemini-2.5-pro', $customPrompt);
        $openaiService->analyzeText('Test text', 'gpt-4.1', $customPrompt);

        // Verify custom prompt was sent in requests
        Http::assertSent(function ($request) use ($customPrompt) {
            $body = $request->body();
            return str_contains($body, $customPrompt) || str_contains(json_encode($body), $customPrompt);
        });
    }
}