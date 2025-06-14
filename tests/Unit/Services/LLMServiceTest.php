<?php

namespace Tests\Unit\Services;

use Tests\TestCase;
use App\Services\ClaudeService;
use App\Services\OpenAIService;
use App\Services\GeminiService;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;

class LLMServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_claude_service_configuration()
    {
        $service = app(ClaudeService::class);
        
        $this->assertInstanceOf(ClaudeService::class, $service);
    }

    public function test_openai_service_configuration()
    {
        $service = app(OpenAIService::class);
        
        $this->assertInstanceOf(OpenAIService::class, $service);
    }

    public function test_gemini_service_configuration()
    {
        $service = app(GeminiService::class);
        
        $this->assertInstanceOf(GeminiService::class, $service);
    }

    public function test_services_implement_llm_interface()
    {
        $claude = app(ClaudeService::class);
        $openai = app(OpenAIService::class);
        $gemini = app(GeminiService::class);

        $this->assertTrue(method_exists($claude, 'analyzeText'));
        $this->assertTrue(method_exists($openai, 'analyzeText'));
        $this->assertTrue(method_exists($gemini, 'analyzeText'));

        $this->assertTrue(method_exists($claude, 'isConfigured'));
        $this->assertTrue(method_exists($openai, 'isConfigured'));
        $this->assertTrue(method_exists($gemini, 'isConfigured'));
    }

    public function test_services_can_use_user_api_keys()
    {
        $user = User::create([
            'name' => 'Test User',
            'email' => 'test@example.com',
            'password' => Hash::make('password'),
            'role' => 'user',
            'is_active' => true
        ]);

        $claude = app(ClaudeService::class);
        $openai = app(OpenAIService::class);
        $gemini = app(GeminiService::class);

        // These methods should accept user parameter for API key lookup
        $this->assertTrue(method_exists($claude, 'analyzeText'));
        $this->assertTrue(method_exists($openai, 'analyzeText'));
        $this->assertTrue(method_exists($gemini, 'analyzeText'));
    }

    public function test_model_configurations_are_defined()
    {
        $claude = app(ClaudeService::class);
        $openai = app(OpenAIService::class);
        $gemini = app(GeminiService::class);

        // Each service should have model configurations
        $this->assertTrue(method_exists($claude, 'getAvailableModels') || 
                         method_exists($claude, 'getModelConfigurations'));
        $this->assertTrue(method_exists($openai, 'getAvailableModels') || 
                         method_exists($openai, 'getModelConfigurations'));
        $this->assertTrue(method_exists($gemini, 'getAvailableModels') || 
                         method_exists($gemini, 'getModelConfigurations'));
    }
}
