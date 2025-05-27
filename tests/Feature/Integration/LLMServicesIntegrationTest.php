<?php

namespace Tests\Feature\Integration;

use App\Services\ClaudeService;
use App\Services\GeminiService;
use App\Services\OpenAIService;
use App\Services\PromptService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;
use Illuminate\Http\Client\Factory as HttpFactory;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class LLMServicesIntegrationTest extends TestCase
{
    use RefreshDatabase, WithFaker;

    protected function setUp(): void
    {
        parent::setUp();
        
        // Mock HTTP responses for all tests
        Http::fake();
    }

    public function test_claude_service_analyzes_text_successfully(): void
    {
        // Mock successful Claude API response
        Http::fake([
            'api.anthropic.com/*' => Http::response([
                'content' => [
                    [
                        'text' => json_encode([
                            'primaryChoice' => ['choices' => ['yes']],
                            'annotations' => [
                                [
                                    'type' => 'labels',
                                    'value' => [
                                        'start' => 0,
                                        'end' => 20,
                                        'text' => 'test propaganda text',
                                        'labels' => ['emotional_appeal']
                                    ]
                                ]
                            ],
                            'desinformationTechnique' => [
                                'choices' => ['emotional_appeal']
                            ]
                        ])
                    ]
                ]
            ], 200)
        ]);

        $claudeService = app(ClaudeService::class);
        $testText = 'This is a test propaganda text for analysis.';
        
        $result = $claudeService->analyzeText($testText);

        $this->assertIsArray($result);
        $this->assertArrayHasKey('primaryChoice', $result);
        $this->assertArrayHasKey('annotations', $result);
        $this->assertArrayHasKey('desinformationTechnique', $result);
        $this->assertEquals(['yes'], $result['primaryChoice']['choices']);
    }

    public function test_claude_service_with_custom_prompt(): void
    {
        Http::fake([
            'api.anthropic.com/*' => Http::response([
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

        $claudeService = app(ClaudeService::class);
        $customPrompt = 'Custom analysis prompt for testing';
        $testText = 'Neutral text for analysis.';
        
        $result = $claudeService->analyzeText($testText, $customPrompt);

        $this->assertIsArray($result);
        $this->assertEquals(['no'], $result['primaryChoice']['choices']);
        $this->assertEmpty($result['annotations']);
        
        // Verify that the custom prompt was used
        Http::assertSent(function ($request) use ($customPrompt) {
            $body = $request->data();
            return isset($body['messages'][0]['content']) && 
                   strpos($body['messages'][0]['content'], $customPrompt) !== false;
        });
    }

    public function test_claude_service_handles_api_errors(): void
    {
        Http::fake([
            'api.anthropic.com/*' => Http::response(['error' => 'API Error'], 500)
        ]);

        $claudeService = app(ClaudeService::class);
        
        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('HTTP request failed');
        
        $claudeService->analyzeText('Test text');
    }

    public function test_claude_service_retries_on_failure(): void
    {
        Http::fake([
            'api.anthropic.com/*' => Http::sequence()
                ->push(['error' => 'Temporary error'], 500)
                ->push(['error' => 'Another error'], 500)
                ->push([
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

        $claudeService = app(ClaudeService::class);
        
        $result = $claudeService->analyzeText('Test text');
        
        $this->assertIsArray($result);
        $this->assertEquals(['yes'], $result['primaryChoice']['choices']);
        
        // Should have made 3 requests (2 failures + 1 success)
        Http::assertSentCount(3);
    }

    public function test_gemini_service_analyzes_text_successfully(): void
    {
        Http::fake([
            'generativelanguage.googleapis.com/*' => Http::response([
                'candidates' => [
                    [
                        'content' => [
                            'parts' => [
                                [
                                    'text' => json_encode([
                                        'primaryChoice' => ['choices' => ['yes']],
                                        'annotations' => [
                                            [
                                                'type' => 'labels',
                                                'value' => [
                                                    'start' => 5,
                                                    'end' => 25,
                                                    'text' => 'propaganda content',
                                                    'labels' => ['false_authority']
                                                ]
                                            ]
                                        ],
                                        'desinformationTechnique' => [
                                            'choices' => ['false_authority']
                                        ]
                                    ])
                                ]
                            ]
                        ]
                    ]
                ]
            ], 200)
        ]);

        $geminiService = app(GeminiService::class);
        $testText = 'Test propaganda content with false authority.';
        
        $result = $geminiService->analyzeText($testText);

        $this->assertIsArray($result);
        $this->assertArrayHasKey('primaryChoice', $result);
        $this->assertArrayHasKey('annotations', $result);
        $this->assertEquals(['yes'], $result['primaryChoice']['choices']);
        $this->assertCount(1, $result['annotations']);
    }

    public function test_openai_service_analyzes_text_successfully(): void
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

        $openAIService = app(OpenAIService::class);
        $testText = 'This is neutral, non-propaganda text.';
        
        $result = $openAIService->analyzeText($testText);

        $this->assertIsArray($result);
        $this->assertArrayHasKey('primaryChoice', $result);
        $this->assertEquals(['no'], $result['primaryChoice']['choices']);
        $this->assertEmpty($result['annotations']);
    }

    public function test_all_services_use_prompt_service_correctly(): void
    {
        // Mock responses for all services
        Http::fake([
            'api.anthropic.com/*' => Http::response([
                'content' => [['text' => '{"primaryChoice": {"choices": ["yes"]}, "annotations": [], "desinformationTechnique": {"choices": []}}']]
            ], 200),
            'generativelanguage.googleapis.com/*' => Http::response([
                'candidates' => [['content' => ['parts' => [['text' => '{"primaryChoice": {"choices": ["yes"]}, "annotations": [], "desinformationTechnique": {"choices": []}}']]]]]
            ], 200),
            'api.openai.com/*' => Http::response([
                'choices' => [['message' => ['content' => '{"primaryChoice": {"choices": ["yes"]}, "annotations": [], "desinformationTechnique": {"choices": []}}']]]
            ], 200)
        ]);

        $testText = 'Test text for prompt service integration';
        $customPrompt = 'Custom test prompt';

        // Test Claude with custom prompt
        $claudeService = app(ClaudeService::class);
        $claudeService->analyzeText($testText, $customPrompt);

        // Test Gemini with custom prompt
        $geminiService = app(GeminiService::class);
        $geminiService->analyzeText($testText, $customPrompt);

        // Test OpenAI with custom prompt
        $openAIService = app(OpenAIService::class);
        $openAIService->analyzeText($testText, $customPrompt);

        // Verify all services received requests
        Http::assertSentCount(3);
        
        // Verify custom prompt was used
        Http::assertSent(function ($request) use ($customPrompt) {
            $body = $request->data();
            $content = '';
            
            // Extract content based on service
            if (isset($body['messages'])) {
                $content = $body['messages'][0]['content'] ?? '';
            } elseif (isset($body['contents'])) {
                $content = $body['contents'][0]['parts'][0]['text'] ?? '';
            }
            
            return strpos($content, $customPrompt) !== false;
        });
    }

    public function test_services_handle_invalid_json_responses(): void
    {
        Http::fake([
            'api.anthropic.com/*' => Http::response([
                'content' => [['text' => 'Invalid JSON response']]
            ], 200)
        ]);

        $claudeService = app(ClaudeService::class);
        
        $this->expectException(\Exception::class);
        
        $claudeService->analyzeText('Test text');
    }

    public function test_services_configuration_validation(): void
    {
        // Test Claude service configuration
        $claudeService = app(ClaudeService::class);
        $this->assertTrue($claudeService->isConfigured());

        // Test Gemini service configuration
        $geminiService = app(GeminiService::class);
        $this->assertTrue($geminiService->isConfigured());

        // Test OpenAI service configuration
        $openAIService = app(OpenAIService::class);
        $this->assertTrue($openAIService->isConfigured());
    }

    public function test_services_handle_rate_limiting(): void
    {
        Http::fake([
            'api.anthropic.com/*' => Http::sequence()
                ->push(['error' => 'Rate limit exceeded'], 429)
                ->push([
                    'content' => [['text' => '{"primaryChoice": {"choices": ["yes"]}, "annotations": [], "desinformationTechnique": {"choices": []}}']]
                ], 200)
        ]);

        $claudeService = app(ClaudeService::class);
        
        $result = $claudeService->analyzeText('Test text');
        
        $this->assertIsArray($result);
        Http::assertSentCount(2);
    }

    public function test_prompt_service_generates_valid_prompts(): void
    {
        $promptService = app(PromptService::class);
        
        $testText = 'Test propaganda text';
        $prompt = $promptService->generateAnalysisPrompt($testText);
        
        $this->assertIsString($prompt);
        $this->assertStringContainsString($testText, $prompt);
        $this->assertStringContainsString('propagandos', $prompt);
        $this->assertStringContainsString('JSON', $prompt);
    }

    public function test_prompt_service_validates_responses(): void
    {
        $promptService = app(PromptService::class);
        
        // Valid response
        $validResponse = [
            'primaryChoice' => ['choices' => ['yes']],
            'annotations' => [
                [
                    'type' => 'labels',
                    'value' => [
                        'start' => 0,
                        'end' => 10,
                        'text' => 'test',
                        'labels' => ['test']
                    ]
                ]
            ],
            'desinformationTechnique' => ['choices' => ['test']]
        ];
        
        $this->assertTrue($promptService->validateResponse($validResponse));
        
        // Invalid response - missing keys
        $invalidResponse = [
            'primaryChoice' => ['choices' => ['yes']]
            // Missing annotations and desinformationTechnique
        ];
        
        $this->assertFalse($promptService->validateResponse($invalidResponse));
        
        // Invalid response - malformed annotations
        $malformedResponse = [
            'primaryChoice' => ['choices' => ['yes']],
            'annotations' => [
                [
                    'type' => 'labels'
                    // Missing value
                ]
            ],
            'desinformationTechnique' => ['choices' => ['test']]
        ];
        
        $this->assertFalse($promptService->validateResponse($malformedResponse));
    }

    public function test_integration_with_queue_jobs(): void
    {
        Http::fake([
            'api.anthropic.com/*' => Http::response([
                'content' => [['text' => '{"primaryChoice": {"choices": ["yes"]}, "annotations": [], "desinformationTechnique": {"choices": []}}']]
            ], 200)
        ]);

        // This would test the actual job integration, but we'll simulate it
        $claudeService = app(ClaudeService::class);
        $customPrompt = 'Job test prompt';
        
        $result = $claudeService->analyzeText('Job test text', $customPrompt);
        
        $this->assertIsArray($result);
        $this->assertEquals(['yes'], $result['primaryChoice']['choices']);
        
        // Verify the request was made with custom prompt
        Http::assertSent(function ($request) use ($customPrompt) {
            $body = $request->data();
            return isset($body['messages'][0]['content']) && 
                   strpos($body['messages'][0]['content'], $customPrompt) !== false;
        });
    }
}
