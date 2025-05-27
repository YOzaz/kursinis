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
        $this->assertStringContainsString('RISEN', $prompt);
        $this->assertStringContainsString('propaganda', $prompt);
        $this->assertStringContainsString('JSON', $prompt);
    }

    public function test_generates_gemini_prompt_with_text(): void
    {
        $text = "Test propaganda text content";
        $prompt = $this->service->generateGeminiPrompt($text);

        $this->assertIsString($prompt);
        $this->assertStringContainsString($text, $prompt);
        $this->assertStringContainsString('RISEN', $prompt);
        $this->assertStringContainsString('propaganda', $prompt);
        $this->assertStringContainsString('JSON', $prompt);
    }

    public function test_generates_openai_prompt_with_text(): void
    {
        $text = "Test propaganda text content";
        $prompt = $this->service->generateOpenAIPrompt($text);

        $this->assertIsString($prompt);
        $this->assertStringContainsString($text, $prompt);
        $this->assertStringContainsString('RISEN', $prompt);
        $this->assertStringContainsString('propaganda', $prompt);
        $this->assertStringContainsString('JSON', $prompt);
    }

    public function test_prompts_contain_required_json_structure(): void
    {
        $text = "Test text";
        
        $claudePrompt = $this->service->generateClaudePrompt($text);
        $geminiPrompt = $this->service->generateGeminiPrompt($text);
        $openaiPrompt = $this->service->generateOpenAIPrompt($text);

        // Check for required JSON structure elements
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

        // Should contain Lithuanian context or instructions
        $this->assertStringContainsString('lietuvių', $claudePrompt);
        $this->assertStringContainsString('lietuvių', $geminiPrompt);
        $this->assertStringContainsString('lietuvių', $openaiPrompt);
    }

    public function test_prompts_are_consistent_in_structure(): void
    {
        $text = "Consistent test text";
        
        $claudePrompt = $this->service->generateClaudePrompt($text);
        $geminiPrompt = $this->service->generateGeminiPrompt($text);
        $openaiPrompt = $this->service->generateOpenAIPrompt($text);

        // All prompts should have similar structure elements
        $commonElements = ['primaryChoice', 'choices', 'propaganda'];
        
        foreach ($commonElements as $element) {
            $this->assertStringContainsString($element, $claudePrompt);
            $this->assertStringContainsString($element, $geminiPrompt);
            $this->assertStringContainsString($element, $openaiPrompt);
        }
    }
}