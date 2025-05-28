<?php

namespace Tests\Unit\Services;

use Tests\TestCase;
use App\Services\GeminiServiceNew;
use App\Services\PromptService;
use App\Http\Controllers\SettingsController;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\RequestException;
use GuzzleHttp\Psr7\Response;
use GuzzleHttp\Psr7\Request;
use Illuminate\Support\Facades\Log;
use Mockery;

class GeminiServiceNewTest extends TestCase
{
    private GeminiServiceNew $service;
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
                'gemini-pro' => [
                    'provider' => 'google',
                    'model' => 'gemini-1.5-pro',
                    'api_key' => 'test-api-key',
                    'base_url' => 'https://generativelanguage.googleapis.com/v1beta/models',
                    'rate_limit' => 60,
                    'max_tokens' => 8192
                ]
            ]);

        $this->service = new GeminiServiceNew($this->promptService);
        
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

    public function test_get_provider_name_returns_google()
    {
        $reflection = new \ReflectionClass($this->service);
        $method = $reflection->getMethod('getProviderName');
        $method->setAccessible(true);
        
        $this->assertEquals('google', $method->invoke($this->service));
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
            'candidates' => [
                [
                    'content' => [
                        'parts' => [
                            ['text' => 'Analysis result from Gemini']
                        ]
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
        $this->assertEquals('Analysis result from Gemini', $result);
    }

    public function test_analyze_handles_api_error()
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
                new Response(429, [], '{"error": {"message": "Quota exceeded"}}')
            ));

        Log::shouldReceive('error')->once();

        // Act & Assert
        $this->expectException(RequestException::class);
        $this->service->analyze('test content');
    }

    public function test_analyze_handles_empty_candidates()
    {
        // Arrange
        $this->promptService
            ->shouldReceive('buildPrompt')
            ->once()
            ->andReturn('Built prompt');

        $responseBody = json_encode([
            'candidates' => []
        ]);

        $this->httpClient
            ->shouldReceive('post')
            ->once()
            ->andReturn(new Response(200, [], $responseBody));

        Log::shouldReceive('warning')->once();

        // Act & Assert
        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('Empty response from Gemini API');
        
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
        $this->assertArrayHasKey('contents', $payload);
        $this->assertArrayHasKey('generationConfig', $payload);
        
        $this->assertCount(1, $payload['contents']);
        $this->assertEquals('user', $payload['contents'][0]['role']);
        $this->assertEquals('test prompt', $payload['contents'][0]['parts'][0]['text']);
        $this->assertEquals(8192, $payload['generationConfig']['maxOutputTokens']);
    }

    public function test_set_model_works_correctly()
    {
        $result = $this->service->setModel('gemini-pro');
        
        $this->assertTrue($result);
        $this->assertEquals('gemini-pro', $this->service->getCurrentModel());
    }

    public function test_get_current_config_returns_valid_config()
    {
        $reflection = new \ReflectionClass($this->service);
        $method = $reflection->getMethod('getCurrentConfig');
        $method->setAccessible(true);

        $config = $method->invoke($this->service);

        $this->assertIsArray($config);
        $this->assertEquals('google', $config['provider']);
        $this->assertEquals('gemini-1.5-pro', $config['model']);
    }
}