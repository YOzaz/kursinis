<?php

namespace Tests\Unit\Services;

use App\Services\GeminiService;
use App\Services\PromptService;
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
        $promptService = new PromptService();
        $this->service = new GeminiService($promptService);
        // Note: HTTP::fake() is already called in parent TestCase
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













}