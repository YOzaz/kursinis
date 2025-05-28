<?php

namespace Tests\Unit\Services;

use Tests\TestCase;
use App\Services\PromptService;

class PromptServiceTest extends TestCase
{
    private PromptService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new PromptService();
    }

    public function test_can_instantiate_service(): void
    {
        $this->assertInstanceOf(PromptService::class, $this->service);
    }

    public function test_generates_claude_prompt_with_text(): void
    {
        $text = "Test propaganda text content";
        $prompt = $this->service->generateClaudePrompt($text);

        $this->assertIsString($prompt);
        $this->assertStringContainsString($text, $prompt);
        $this->assertStringContainsString('ATSPARA', $prompt);
        $this->assertStringContainsString('propaganda', $prompt);
        $this->assertStringContainsString('JSON', $prompt);
    }

    public function test_generates_gemini_prompt_with_text(): void
    {
        $text = "Test propaganda text content";
        $prompt = $this->service->generateGeminiPrompt($text);

        $this->assertIsString($prompt);
        $this->assertStringContainsString($text, $prompt);
        $this->assertStringContainsString('ATSPARA', $prompt);
        $this->assertStringContainsString('propaganda', $prompt);
        $this->assertStringContainsString('JSON', $prompt);
    }

    public function test_generates_openai_prompt_with_text(): void
    {
        $text = "Test propaganda text content";
        $prompt = $this->service->generateOpenAIPrompt($text);

        $this->assertIsString($prompt);
        $this->assertStringContainsString($text, $prompt);
        $this->assertStringContainsString('ATSPARA', $prompt);
        $this->assertStringContainsString('propaganda', $prompt);
        $this->assertStringContainsString('JSON', $prompt);
    }

    public function test_get_system_message_returns_valid_content(): void
    {
        $message = $this->service->getSystemMessage();

        $this->assertStringContainsString('ATSPARA propagandos analizės sistema', $message);
        $this->assertStringContainsString('JSON formatą', $message);
        $this->assertStringContainsString('objektyvius kriterijus', $message);
        $this->assertNotEmpty($message);
    }

    public function test_validate_response_accepts_valid_structure(): void
    {
        $validResponse = [
            'primaryChoice' => [
                'choices' => ['yes']
            ],
            'annotations' => [
                [
                    'type' => 'labels',
                    'value' => [
                        'start' => 0,
                        'end' => 10,
                        'text' => 'sample text',
                        'labels' => ['propaganda_technique']
                    ]
                ]
            ],
            'desinformationTechnique' => [
                'choices' => ['some_narrative']
            ]
        ];

        $this->assertTrue($this->service->validateResponse($validResponse));
    }

    public function test_validate_response_rejects_missing_primary_choice(): void
    {
        $invalidResponse = [
            'annotations' => [],
            'desinformationTechnique' => ['choices' => []]
        ];

        $this->assertFalse($this->service->validateResponse($invalidResponse));
    }

    public function test_validate_response_rejects_missing_annotations(): void
    {
        $invalidResponse = [
            'primaryChoice' => ['choices' => ['no']],
            'desinformationTechnique' => ['choices' => []]
        ];

        $this->assertFalse($this->service->validateResponse($invalidResponse));
    }

    public function test_validate_response_rejects_missing_desinformation_technique(): void
    {
        $invalidResponse = [
            'primaryChoice' => ['choices' => ['no']],
            'annotations' => []
        ];

        $this->assertFalse($this->service->validateResponse($invalidResponse));
    }

    public function test_validate_response_rejects_invalid_primary_choice_structure(): void
    {
        $invalidResponse = [
            'primaryChoice' => 'invalid_structure',
            'annotations' => [],
            'desinformationTechnique' => ['choices' => []]
        ];

        $this->assertFalse($this->service->validateResponse($invalidResponse));
    }

    public function test_validate_response_rejects_invalid_annotation_structure(): void
    {
        $invalidResponse = [
            'primaryChoice' => ['choices' => ['yes']],
            'annotations' => [
                [
                    'type' => 'labels',
                    'value' => [
                        'start' => 'invalid_start',
                        'end' => 10,
                        'text' => 'sample text',
                        'labels' => ['technique']
                    ]
                ]
            ],
            'desinformationTechnique' => ['choices' => []]
        ];

        $this->assertFalse($this->service->validateResponse($invalidResponse));
    }

    public function test_validate_response_accepts_empty_annotations(): void
    {
        $validResponse = [
            'primaryChoice' => ['choices' => ['no']],
            'annotations' => [],
            'desinformationTechnique' => ['choices' => []]
        ];

        $this->assertTrue($this->service->validateResponse($validResponse));
    }

    public function test_generate_analysis_prompt_with_custom_prompt(): void
    {
        $text = 'Test tekstas';
        $customPrompt = 'Custom instrukcijos analizei';
        
        $result = $this->service->generateAnalysisPrompt($text, $customPrompt);

        $this->assertStringContainsString($customPrompt, $result);
        $this->assertStringContainsString($text, $result);
        $this->assertStringContainsString('Analizuojamas tekstas:', $result);
    }

    public function test_generate_analysis_prompt_without_custom_prompt(): void
    {
        $text = 'Test tekstas';
        
        $result = $this->service->generateAnalysisPrompt($text);

        $this->assertStringContainsString('ATSPARA projekto propagandos analizės ekspertas', $result);
        $this->assertStringContainsString($text, $result);
        $this->assertStringContainsString('Analizuojamas tekstas:', $result);
    }

    public function test_prompts_contain_required_json_structure(): void
    {
        $text = "Test text";
        
        $claudePrompt = $this->service->generateClaudePrompt($text);
        $geminiPrompt = $this->service->generateGeminiPrompt($text);
        $openaiPrompt = $this->service->generateOpenAIPrompt($text);

        $requiredElements = ['primaryChoice', 'annotations', 'desinformationTechnique'];
        
        foreach ($requiredElements as $element) {
            $this->assertStringContainsString($element, $claudePrompt);
            $this->assertStringContainsString($element, $geminiPrompt);
            $this->assertStringContainsString($element, $openaiPrompt);
        }
    }

    public function test_prompts_handle_empty_text(): void
    {
        $claudePrompt = $this->service->generateClaudePrompt('');
        $geminiPrompt = $this->service->generateGeminiPrompt('');
        $openaiPrompt = $this->service->generateOpenAIPrompt('');

        $this->assertIsString($claudePrompt);
        $this->assertIsString($geminiPrompt);
        $this->assertIsString($openaiPrompt);
        $this->assertNotEmpty($claudePrompt);
        $this->assertNotEmpty($geminiPrompt);
        $this->assertNotEmpty($openaiPrompt);
    }

    public function test_prompts_handle_special_characters(): void
    {
        $text = "Text with \"quotes\" and 'apostrophes' and \n newlines";
        
        $claudePrompt = $this->service->generateClaudePrompt($text);
        $geminiPrompt = $this->service->generateGeminiPrompt($text);
        $openaiPrompt = $this->service->generateOpenAIPrompt($text);

        $this->assertStringContainsString($text, $claudePrompt);
        $this->assertStringContainsString($text, $geminiPrompt);
        $this->assertStringContainsString($text, $openaiPrompt);
    }

    public function test_prompts_contain_lithuanian_context(): void
    {
        $text = "Lietuvos tekstas";
        
        $claudePrompt = $this->service->generateClaudePrompt($text);
        $geminiPrompt = $this->service->generateGeminiPrompt($text);
        $openaiPrompt = $this->service->generateOpenAIPrompt($text);

        $this->assertStringContainsString('lietuvių', $claudePrompt);
        $this->assertStringContainsString('lietuvių', $geminiPrompt);
        $this->assertStringContainsString('lietuvių', $openaiPrompt);
    }

    public function test_validate_response_validates_labels_array_structure(): void
    {
        $invalidResponse = [
            'primaryChoice' => ['choices' => ['yes']],
            'annotations' => [
                [
                    'type' => 'labels',
                    'value' => [
                        'start' => 0,
                        'end' => 10,
                        'text' => 'sample text',
                        'labels' => 'not_an_array'
                    ]
                ]
            ],
            'desinformationTechnique' => ['choices' => []]
        ];

        $this->assertFalse($this->service->validateResponse($invalidResponse));
    }
}