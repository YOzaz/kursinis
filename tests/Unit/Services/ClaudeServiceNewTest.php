<?php

namespace Tests\Unit\Services;

use Tests\TestCase;
use App\Services\ClaudeServiceNew;
use App\Services\PromptService;
use App\Http\Controllers\SettingsController;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\RequestException;
use GuzzleHttp\Psr7\Response;
use GuzzleHttp\Psr7\Request;
use Illuminate\Support\Facades\Log;
use Mockery;

class ClaudeServiceNewTest extends TestCase
{
    private ClaudeServiceNew $service;
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
                'claude-opus-4' => [
                    'provider' => 'anthropic',
                    'model' => 'claude-3-opus-20240229',
                    'api_key' => 'test-api-key',
                    'base_url' => 'https://api.anthropic.com/v1/messages',
                    'rate_limit' => 50,
                    'max_tokens' => 4096
                ],
                'claude-sonnet' => [
                    'provider' => 'anthropic',
                    'model' => 'claude-3-sonnet-20240229',
                    'api_key' => 'test-api-key-2',
                    'base_url' => 'https://api.anthropic.com/v1/messages',
                    'rate_limit' => 100
                ]
            ]);

        $this->service = new ClaudeServiceNew($this->promptService);
        
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

    public function test_constructor_sets_default_model()
    {
        $service = new ClaudeServiceNew($this->promptService);
        
        // Should set the first available model as default
        $this->assertNotEmpty($service->getCurrentModel());
    }

    public function test_get_provider_name_returns_anthropic()
    {
        $reflection = new \ReflectionClass($this->service);
        $method = $reflection->getMethod('getProviderName');
        $method->setAccessible(true);
        
        $this->assertEquals('anthropic', $method->invoke($this->service));
    }

    public function test_set_model_switches_model_successfully()
    {
        $result = $this->service->setModel('claude-sonnet');
        
        $this->assertTrue($result);
        $this->assertEquals('claude-sonnet', $this->service->getCurrentModel());
    }

    public function test_set_model_fails_for_invalid_model()
    {
        $result = $this->service->setModel('invalid-model');
        
        $this->assertFalse($result);
    }

    public function test_analyze_with_default_prompt_succeeds()
    {
        // Arrange
        $this->promptService
            ->shouldReceive('buildPrompt')
            ->with('test content', null)
            ->once()
            ->andReturn('Built prompt for test content');

        $responseBody = json_encode([
            'content' => [
                ['text' => 'Analysis result from Claude']
            ]
        ]);

        $this->httpClient
            ->shouldReceive('post')
            ->once()
            ->andReturn(new Response(200, [], $responseBody));

        // Act
        $result = $this->service->analyze('test content');

        // Assert
        $this->assertEquals('Analysis result from Claude', $result);
    }

    public function test_analyze_with_custom_prompt_succeeds()
    {
        // Arrange
        $this->promptService
            ->shouldReceive('buildPrompt')
            ->with('test content', 'custom prompt')
            ->once()
            ->andReturn('Built custom prompt for test content');

        $responseBody = json_encode([
            'content' => [
                ['text' => 'Custom analysis result']
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

    public function test_analyze_handles_api_error_gracefully()
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
                'API Error',
                new Request('POST', 'test'),
                new Response(400, [], '{"error": {"message": "API rate limit exceeded"}}')
            ));

        Log::shouldReceive('error')->once();

        // Act & Assert
        $this->expectException(RequestException::class);
        $this->service->analyze('test content');
    }

    public function test_analyze_handles_malformed_response()
    {
        // Arrange
        $this->promptService
            ->shouldReceive('buildPrompt')
            ->once()
            ->andReturn('Built prompt');

        $this->httpClient
            ->shouldReceive('post')
            ->once()
            ->andReturn(new Response(200, [], 'invalid json'));

        Log::shouldReceive('error')->once();

        // Act & Assert
        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('Invalid response format from Claude API');
        
        $this->service->analyze('test content');
    }

    public function test_analyze_handles_empty_content_response()
    {
        // Arrange
        $this->promptService
            ->shouldReceive('buildPrompt')
            ->once()
            ->andReturn('Built prompt');

        $responseBody = json_encode([
            'content' => []
        ]);

        $this->httpClient
            ->shouldReceive('post')
            ->once()
            ->andReturn(new Response(200, [], $responseBody));

        Log::shouldReceive('warning')->once();

        // Act & Assert
        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('Empty response from Claude API');
        
        $this->service->analyze('test content');
    }

    public function test_build_request_payload_formats_correctly()
    {
        // Arrange
        $content = 'test content';
        $prompt = 'test prompt';

        // Use reflection to access private method
        $reflection = new \ReflectionClass($this->service);
        $method = $reflection->getMethod('buildRequestPayload');
        $method->setAccessible(true);

        // Act
        $payload = $method->invoke($this->service, $content, $prompt);

        // Assert
        $this->assertArrayHasKey('model', $payload);
        $this->assertArrayHasKey('max_tokens', $payload);
        $this->assertArrayHasKey('messages', $payload);
        
        $this->assertEquals('claude-3-opus-20240229', $payload['model']);
        $this->assertEquals(4096, $payload['max_tokens']);
        $this->assertCount(1, $payload['messages']);
        $this->assertEquals('user', $payload['messages'][0]['role']);
        $this->assertEquals($prompt, $payload['messages'][0]['content']);
    }

    public function test_get_current_config_returns_model_config()
    {
        // Use reflection to access private method
        $reflection = new \ReflectionClass($this->service);
        $method = $reflection->getMethod('getCurrentConfig');
        $method->setAccessible(true);

        // Act
        $config = $method->invoke($this->service);

        // Assert
        $this->assertIsArray($config);
        $this->assertArrayHasKey('provider', $config);
        $this->assertArrayHasKey('model', $config);
        $this->assertArrayHasKey('api_key', $config);
        $this->assertEquals('anthropic', $config['provider']);
    }

    public function test_get_current_model_returns_model_key()
    {
        $model = $this->service->getCurrentModel();
        
        $this->assertIsString($model);
        $this->assertContains($model, ['claude-opus-4', 'claude-sonnet']);
    }

    public function test_service_validates_api_key_on_initialization()
    {
        // Test with empty API key
        SettingsController::shouldReceive('getModelSettings')
            ->once()
            ->andReturn([
                'claude-test' => [
                    'provider' => 'anthropic',
                    'model' => 'claude-3-opus',
                    'api_key' => '', // Empty API key
                    'base_url' => 'https://api.anthropic.com/v1/messages'
                ]
            ]);

        $this->expectException(\InvalidArgumentException::class);
        
        new ClaudeServiceNew($this->promptService);
    }
}