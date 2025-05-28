<?php

namespace Tests\Unit\Services;

use Tests\TestCase;
use App\Services\OpenAIServiceNew;
use App\Services\PromptService;
use App\Http\Controllers\SettingsController;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\RequestException;
use GuzzleHttp\Psr7\Response;
use GuzzleHttp\Psr7\Request;
use Illuminate\Support\Facades\Log;
use Mockery;

class OpenAIServiceNewTest extends TestCase
{
    private OpenAIServiceNew $service;
    private PromptService $promptService;
    private Client $httpClient;

    protected function setUp(): void
    {
        parent::setUp();

        $this->promptService = Mockery::mock(PromptService::class);
        $this->httpClient = Mockery::mock(Client::class);

        // Mock model settings
        SettingsController::shouldReceive('getModelSettings')
            ->andReturn([
                'gpt-4' => [
                    'provider' => 'openai',
                    'model' => 'gpt-4-0125-preview',
                    'api_key' => 'test-api-key',
                    'base_url' => 'https://api.openai.com/v1/chat/completions',
                    'rate_limit' => 100,
                    'max_tokens' => 4096
                ],
                'gpt-4-turbo' => [
                    'provider' => 'openai',
                    'model' => 'gpt-4-turbo-preview',
                    'api_key' => 'test-api-key-2',
                    'base_url' => 'https://api.openai.com/v1/chat/completions',
                    'rate_limit' => 200
                ]
            ]);

        $this->service = new OpenAIServiceNew($this->promptService);
        
        // Inject mock HTTP client
        $reflection = new \ReflectionClass($this->service);
        $httpClientProperty = $reflection->getProperty('httpClient');
        $httpClientProperty->setAccessible(true);
        $httpClientProperty->setValue($this->service, $this->httpClient);
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    public function test_get_provider_name_returns_openai()
    {
        $reflection = new \ReflectionClass($this->service);
        $method = $reflection->getMethod('getProviderName');
        $method->setAccessible(true);
        
        $this->assertEquals('openai', $method->invoke($this->service));
    }

    public function test_analyze_succeeds_with_valid_response()
    {
        // Arrange
        $this->promptService
            ->shouldReceive('buildPrompt')
            ->with('test content', null)
            ->once()
            ->andReturn('Built prompt for test content');

        $responseBody = json_encode([
            'choices' => [
                [
                    'message' => [
                        'content' => 'Analysis result from OpenAI'
                    ]
                ]
            ]
        ]);

        $this->httpClient
            ->shouldReceive('post')
            ->once()
            ->andReturn(new Response(200, [], $responseBody));

        // Act
        $result = $this->service->analyze('test content');

        // Assert
        $this->assertEquals('Analysis result from OpenAI', $result);
    }

    public function test_analyze_with_custom_prompt()
    {
        // Arrange
        $this->promptService
            ->shouldReceive('buildPrompt')
            ->with('test content', 'custom prompt')
            ->once()
            ->andReturn('Custom built prompt');

        $responseBody = json_encode([
            'choices' => [
                [
                    'message' => [
                        'content' => 'Custom analysis result'
                    ]
                ]
            ]
        ]);

        $this->httpClient
            ->shouldReceive('post')
            ->once()
            ->andReturn(new Response(200, [], $responseBody));

        // Act
        $result = $this->service->analyze('test content', 'custom prompt');

        // Assert
        $this->assertEquals('Custom analysis result', $result);
    }

    public function test_analyze_handles_rate_limit_error()
    {
        // Arrange
        $this->promptService
            ->shouldReceive('buildPrompt')
            ->once()
            ->andReturn('Built prompt');

        $this->httpClient
            ->shouldReceive('post')
            ->once()
            ->andThrow(new RequestException(
                'Rate limit exceeded',
                new Request('POST', 'test'),
                new Response(429, [], '{"error": {"message": "Rate limit exceeded", "type": "rate_limit_exceeded"}}')
            ));

        Log::shouldReceive('error')->once();

        // Act & Assert
        $this->expectException(RequestException::class);
        $this->service->analyze('test content');
    }

    public function test_analyze_handles_empty_choices()
    {
        // Arrange
        $this->promptService
            ->shouldReceive('buildPrompt')
            ->once()
            ->andReturn('Built prompt');

        $responseBody = json_encode([
            'choices' => []
        ]);

        $this->httpClient
            ->shouldReceive('post')
            ->once()
            ->andReturn(new Response(200, [], $responseBody));

        Log::shouldReceive('warning')->once();

        // Act & Assert
        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('Empty response from OpenAI API');
        
        $this->service->analyze('test content');
    }

    public function test_build_request_payload_formats_correctly()
    {
        // Use reflection to access private method
        $reflection = new \ReflectionClass($this->service);
        $method = $reflection->getMethod('buildRequestPayload');
        $method->setAccessible(true);

        // Act
        $payload = $method->invoke($this->service, 'test content', 'test prompt');

        // Assert
        $this->assertArrayHasKey('model', $payload);
        $this->assertArrayHasKey('messages', $payload);
        $this->assertArrayHasKey('max_tokens', $payload);
        
        $this->assertEquals('gpt-4-0125-preview', $payload['model']);
        $this->assertEquals(4096, $payload['max_tokens']);
        $this->assertCount(1, $payload['messages']);
        $this->assertEquals('user', $payload['messages'][0]['role']);
        $this->assertEquals('test prompt', $payload['messages'][0]['content']);
    }

    public function test_set_model_works_correctly()
    {
        $result = $this->service->setModel('gpt-4-turbo');
        
        $this->assertTrue($result);
        $this->assertEquals('gpt-4-turbo', $this->service->getCurrentModel());
    }

    public function test_set_model_fails_for_invalid_model()
    {
        $result = $this->service->setModel('invalid-model');
        
        $this->assertFalse($result);
    }

    public function test_get_current_config_returns_valid_config()
    {
        $reflection = new \ReflectionClass($this->service);
        $method = $reflection->getMethod('getCurrentConfig');
        $method->setAccessible(true);

        $config = $method->invoke($this->service);

        $this->assertIsArray($config);
        $this->assertEquals('openai', $config['provider']);
        $this->assertEquals('gpt-4-0125-preview', $config['model']);
        $this->assertArrayHasKey('api_key', $config);
    }

    public function test_service_initialization_with_multiple_models()
    {
        $service = new OpenAIServiceNew($this->promptService);
        
        // Should initialize with first available model
        $currentModel = $service->getCurrentModel();
        $this->assertContains($currentModel, ['gpt-4', 'gpt-4-turbo']);
    }
}