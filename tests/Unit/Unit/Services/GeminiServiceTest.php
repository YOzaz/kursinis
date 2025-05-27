<?php

namespace Tests\Unit\Unit\Services;

use App\Services\GeminiService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class GeminiServiceTest extends TestCase
{
    use RefreshDatabase;

    private GeminiService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new GeminiService();
        Http::fake();
    }

    public function test_gemini_service_implements_llm_interface(): void
    {
        $this->assertInstanceOf(\App\Services\LLMServiceInterface::class, $this->service);
    }

    public function test_get_model_name_returns_correct_value(): void
    {
        $this->assertEquals('gemini-2.5-pro', $this->service->getModelName());
    }

    public function test_is_configured_returns_true_when_api_key_exists(): void
    {
        config(['llm.models.gemini-2.5-pro.api_key' => 'test-api-key']);
        
        $this->assertTrue($this->service->isConfigured());
    }

    public function test_is_configured_returns_false_when_api_key_missing(): void
    {
        config(['llm.models.gemini-2.5-pro.api_key' => null]);
        
        $this->assertFalse($this->service->isConfigured());
    }

    public function test_analyze_text_returns_valid_response(): void
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
                                                    'start' => 0,
                                                    'end' => 10,
                                                    'text' => 'Test text',
                                                    'labels' => ['emotionalExpression']
                                                ]
                                            ]
                                        ],
                                        'desinformationTechnique' => ['choices' => ['propaganda']]
                                    ])
                                ]
                            ]
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
            'generativelanguage.googleapis.com/*' => Http::response([
                'candidates' => [
                    [
                        'content' => [
                            'parts' => [
                                [
                                    'text' => json_encode([
                                        'primaryChoice' => ['choices' => ['no']],
                                        'annotations' => [],
                                        'desinformationTechnique' => ['choices' => []]
                                    ])
                                ]
                            ]
                        ]
                    ]
                ]
            ], 200)
        ]);

        $this->service->analyzeText('Test text content');

        Http::assertSent(function ($request) {
            return str_contains($request->url(), 'generativelanguage.googleapis.com') &&
                   str_contains($request->url(), 'generateContent') &&
                   isset($request['contents']) &&
                   is_array($request['contents']) &&
                   isset($request['generationConfig']);
        });
    }

    public function test_analyze_text_includes_propaganda_techniques_in_prompt(): void
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
                                        'annotations' => [],
                                        'desinformationTechnique' => ['choices' => []]
                                    ])
                                ]
                            ]
                        ]
                    ]
                ]
            ], 200)
        ]);

        $this->service->analyzeText('Test propaganda content');

        Http::assertSent(function ($request) {
            $content = $request['contents'][0]['parts'][0]['text'];
            
            // Check if prompt contains propaganda techniques
            return str_contains($content, 'simplification') &&
                   str_contains($content, 'emotionalExpression') &&
                   str_contains($content, 'uncertainty') &&
                   str_contains($content, 'doubt') &&
                   str_contains($content, 'wavingTheFlag') &&
                   str_contains($content, 'reductioAdHitlerum') &&
                   str_contains($content, 'repetition');
        });
    }

    public function test_analyze_text_with_custom_prompt(): void
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
                                        'annotations' => [],
                                        'desinformationTechnique' => ['choices' => []]
                                    ])
                                ]
                            ]
                        ]
                    ]
                ]
            ], 200)
        ]);

        $customPrompt = 'Custom Gemini analysis prompt for testing';
        $result = $this->service->analyzeText('Test text', $customPrompt);

        Http::assertSent(function ($request) use ($customPrompt) {
            return str_contains($request['contents'][0]['parts'][0]['text'], $customPrompt);
        });

        $this->assertIsArray($result);
    }

    public function test_analyze_text_handles_api_errors(): void
    {
        Http::fake([
            'generativelanguage.googleapis.com/*' => Http::response(['error' => 'API Error'], 400)
        ]);

        $this->expectException(\Exception::class);
        $this->service->analyzeText('Test text');
    }

    public function test_analyze_text_handles_invalid_json_response(): void
    {
        Http::fake([
            'generativelanguage.googleapis.com/*' => Http::response([
                'candidates' => [
                    [
                        'content' => [
                            'parts' => [
                                [
                                    'text' => 'invalid json response'
                                ]
                            ]
                        ]
                    ]
                ]
            ], 200)
        ]);

        $this->expectException(\Exception::class);
        $this->service->analyzeText('Test text');
    }

    public function test_analyze_text_handles_empty_candidates(): void
    {
        Http::fake([
            'generativelanguage.googleapis.com/*' => Http::response([
                'candidates' => []
            ], 200)
        ]);

        $this->expectException(\Exception::class);
        $this->service->analyzeText('Test text');
    }

    public function test_analyze_text_returns_consistent_structure(): void
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
                                                    'end' => 15,
                                                    'text' => 'propaganda',
                                                    'labels' => ['emotionalExpression', 'uncertainty']
                                                ]
                                            ]
                                        ],
                                        'desinformationTechnique' => ['choices' => ['distrustOfLithuanianInstitutions']]
                                    ])
                                ]
                            ]
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
            'generativelanguage.googleapis.com/*' => Http::response([
                'candidates' => [
                    [
                        'content' => [
                            'parts' => [
                                [
                                    'text' => json_encode([
                                        'primaryChoice' => ['choices' => ['no']],
                                        'annotations' => [],
                                        'desinformationTechnique' => ['choices' => []]
                                    ])
                                ]
                            ]
                        ]
                    ]
                ]
            ], 200)
        ]);

        $this->service->analyzeText('Test text');

        Http::assertSent(function ($request) {
            return isset($request['generationConfig']) &&
                   $request['generationConfig']['maxOutputTokens'] === config('llm.models.gemini-2.5-pro.max_tokens') &&
                   $request['generationConfig']['temperature'] === config('llm.models.gemini-2.5-pro.temperature');
        });
    }

    public function test_analyze_text_with_lithuanian_content(): void
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
                                                    'start' => 0,
                                                    'end' => 15,
                                                    'text' => 'Lietuviškas tekstas',
                                                    'labels' => ['doubt']
                                                ]
                                            ]
                                        ],
                                        'desinformationTechnique' => ['choices' => ['distrustOfLithuanianInstitutions']]
                                    ])
                                ]
                            ]
                        ]
                    ]
                ]
            ], 200)
        ]);

        $result = $this->service->analyzeText('Lietuviškas propagandos tekstas analizei');

        $this->assertIsArray($result);
        $this->assertEquals(['yes'], $result['primaryChoice']['choices']);
        
        Http::assertSent(function ($request) {
            return str_contains($request['contents'][0]['parts'][0]['text'], 'lietuvių kalba');
        });
    }

    public function test_analyze_text_handles_safety_settings(): void
    {
        Http::fake([
            'generativelanguage.googleapis.com/*' => Http::response([
                'candidates' => [
                    [
                        'content' => [
                            'parts' => [
                                [
                                    'text' => json_encode([
                                        'primaryChoice' => ['choices' => ['no']],
                                        'annotations' => [],
                                        'desinformationTechnique' => ['choices' => []]
                                    ])
                                ]
                            ]
                        ]
                    ]
                ]
            ], 200)
        ]);

        $this->service->analyzeText('Test text with potentially sensitive content');

        Http::assertSent(function ($request) {
            return isset($request['safetySettings']) &&
                   is_array($request['safetySettings']);
        });
    }

    public function test_respects_rate_limiting(): void
    {
        Http::fake([
            'generativelanguage.googleapis.com/*' => Http::response([
                'candidates' => [
                    [
                        'content' => [
                            'parts' => [
                                [
                                    'text' => json_encode([
                                        'primaryChoice' => ['choices' => ['no']],
                                        'annotations' => [],
                                        'desinformationTechnique' => ['choices' => []]
                                    ])
                                ]
                            ]
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
}