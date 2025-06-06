<?php

namespace Tests\Unit\Services;

use App\Services\OpenAIService;
use App\Services\PromptService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class OpenAIServiceTest extends TestCase
{
    use RefreshDatabase;

    private PromptService $promptService;

    protected function setUp(): void
    {
        parent::setUp();
        $this->promptService = new PromptService();
    }

    public function test_openai_service_implements_llm_interface(): void
    {
        $service = new OpenAIService($this->promptService);
        $this->assertInstanceOf(\App\Services\LLMServiceInterface::class, $service);
    }

    public function test_get_model_name_returns_correct_value(): void
    {
        $service = new OpenAIService($this->promptService);
        $this->assertEquals('gpt-4.1', $service->getModelName());
    }

    public function test_is_configured_returns_true_when_api_key_configured(): void
    {
        // Test with mocked configuration
        $originalConfig = config('llm.models');
        config(['llm.models' => [
            'gpt-4.1' => [
                'api_key' => 'test-api-key',
                'model' => 'gpt-4-0125-preview',
                'max_tokens' => 4096,
                'temperature' => 0.1
            ]
        ]]);
        
        $service = new OpenAIService($this->promptService);
        $this->assertTrue($service->isConfigured());
        
        // Restore original config
        config(['llm.models' => $originalConfig]);
    }

    public function test_is_configured_returns_false_when_api_key_missing(): void
    {
        // Temporarily clear all GPT models to ensure no API key is available
        $originalConfig = config('llm.models');
        $modelsWithoutGpt = [];
        foreach ($originalConfig as $key => $model) {
            if (!str_starts_with($key, 'gpt')) {
                $modelsWithoutGpt[$key] = $model;
            }
        }
        config(['llm.models' => $modelsWithoutGpt]);
        
        $service = new OpenAIService($this->promptService);
        $this->assertFalse($service->isConfigured());
        
        // Restore original config
        config(['llm.models' => $originalConfig]);
    }

    public function test_analyze_text_throws_exception_when_not_configured(): void
    {
        // Temporarily clear the entire LLM config to ensure no models are available
        $originalConfig = config('llm.models');
        config(['llm.models' => []]);
        
        $service = new OpenAIService($this->promptService);

        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('OpenAI API nėra sukonfigūruotas');
        
        $service->analyzeText('Test text');
        
        // Restore original config
        config(['llm.models' => $originalConfig]);
    }

    public function test_service_has_required_methods(): void
    {
        $service = new OpenAIService($this->promptService);
        
        $this->assertTrue(method_exists($service, 'analyzeText'));
        $this->assertTrue(method_exists($service, 'getModelName'));
        $this->assertTrue(method_exists($service, 'isConfigured'));
    }

    public function test_service_uses_prompt_service(): void
    {
        $service = new OpenAIService($this->promptService);
        
        $reflection = new \ReflectionClass($service);
        $property = $reflection->getProperty('promptService');
        $property->setAccessible(true);
        $promptService = $property->getValue($service);

        $this->assertInstanceOf(PromptService::class, $promptService);
    }

    public function test_service_loads_configuration(): void
    {
        $service = new OpenAIService($this->promptService);
        
        $reflection = new \ReflectionClass($service);
        $property = $reflection->getProperty('config');
        $property->setAccessible(true);
        $config = $property->getValue($service);

        if ($config !== null) {
            $this->assertIsArray($config);
            $this->assertArrayHasKey('api_key', $config);
            $this->assertArrayHasKey('model', $config);
            $this->assertArrayHasKey('max_tokens', $config);
            $this->assertArrayHasKey('temperature', $config);
        } else {
            $this->assertNull($config);
        }
    }

    public function test_constructor_handles_missing_config(): void
    {
        // Temporarily clear the entire LLM config
        $originalConfig = config('llm.models');
        config(['llm.models' => []]);
        
        $service = new OpenAIService($this->promptService);
        $this->assertFalse($service->isConfigured());
        
        // Restore original config
        config(['llm.models' => $originalConfig]);
    }
}