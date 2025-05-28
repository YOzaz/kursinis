<?php

namespace Tests\Unit\Services;

use Tests\TestCase;
use App\Services\AbstractLLMService;
use App\Services\PromptService;
use App\Http\Controllers\SettingsController;
use Illuminate\Support\Facades\Log;
use Mockery;

class AbstractLLMServiceTest extends TestCase
{
    private TestableAbstractLLMService $service;
    private PromptService $promptService;

    protected function setUp(): void
    {
        parent::setUp();
        
        $this->promptService = Mockery::mock(PromptService::class);
        $this->service = new TestableAbstractLLMService($this->promptService);
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    public function test_constructor_loads_models_correctly()
    {
        // Mock model settings
        SettingsController::shouldReceive('getModelSettings')
            ->once()
            ->andReturn([
                'test-model-1' => [
                    'provider' => 'test-provider',
                    'model' => 'test-model-1',
                    'api_key' => 'test-key',
                    'rate_limit' => 50
                ],
                'other-model' => [
                    'provider' => 'other-provider',
                    'model' => 'other-model'
                ]
            ]);

        $service = new TestableAbstractLLMService($this->promptService);
        
        $models = $service->getModels();
        $this->assertCount(1, $models);
        $this->assertArrayHasKey('test-model-1', $models);
        $this->assertEquals('test-provider', $models['test-model-1']['provider']);
    }

    public function test_get_default_model_key_returns_first_available()
    {
        $this->service->setModels([
            'model-1' => ['provider' => 'test-provider'],
            'model-2' => ['provider' => 'test-provider']
        ]);

        $defaultKey = $this->service->getDefaultModelKey();
        $this->assertEquals('model-1', $defaultKey);
    }

    public function test_set_current_model_key_sets_correctly()
    {
        $this->service->setModels([
            'test-model' => ['provider' => 'test-provider']
        ]);

        $this->service->setCurrentModelKey('test-model');
        $this->assertEquals('test-model', $this->service->getCurrentModelKey());
    }

    public function test_set_current_model_key_throws_exception_for_invalid_model()
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Model key "invalid-model" not found');

        $this->service->setCurrentModelKey('invalid-model');
    }

    public function test_get_current_model_config_returns_correct_config()
    {
        $config = [
            'provider' => 'test-provider',
            'model' => 'test-model',
            'api_key' => 'test-key'
        ];

        $this->service->setModels(['test-model' => $config]);
        $this->service->setCurrentModelKey('test-model');

        $currentConfig = $this->service->getCurrentModelConfig();
        $this->assertEquals($config, $currentConfig);
    }

    public function test_get_current_model_config_throws_exception_when_no_model_set()
    {
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('No current model set');

        $this->service->getCurrentModelConfig();
    }

    public function test_analyze_with_retry_succeeds_on_first_attempt()
    {
        $this->service->setModels(['test-model' => ['provider' => 'test-provider']]);
        $this->service->setCurrentModelKey('test-model');

        $this->service->setMockResponse('success result');

        $result = $this->service->analyzeWithRetry('test content', 'test prompt', 3);
        $this->assertEquals('success result', $result);
    }

    public function test_analyze_with_retry_succeeds_after_failure()
    {
        $this->service->setModels(['test-model' => ['provider' => 'test-provider']]);
        $this->service->setCurrentModelKey('test-model');

        $this->service->setMockResponses([
            new \Exception('First failure'),
            'success result'
        ]);

        Log::shouldReceive('warning')->once();

        $result = $this->service->analyzeWithRetry('test content', 'test prompt', 3);
        $this->assertEquals('success result', $result);
    }

    public function test_analyze_with_retry_fails_after_max_attempts()
    {
        $this->service->setModels(['test-model' => ['provider' => 'test-provider']]);
        $this->service->setCurrentModelKey('test-model');

        $this->service->setMockResponses([
            new \Exception('First failure'),
            new \Exception('Second failure'),
            new \Exception('Third failure')
        ]);

        Log::shouldReceive('warning')->times(3);
        Log::shouldReceive('error')->once();

        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('Third failure');

        $this->service->analyzeWithRetry('test content', 'test prompt', 3);
    }

    public function test_validate_model_config_passes_valid_config()
    {
        $config = [
            'provider' => 'test-provider',
            'model' => 'test-model',
            'api_key' => 'test-key'
        ];

        $this->service->validateModelConfig($config, 'test-model');
        // Should not throw exception
        $this->assertTrue(true);
    }

    public function test_validate_model_config_throws_for_missing_api_key()
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('API key not configured for model test-model');

        $config = [
            'provider' => 'test-provider',
            'model' => 'test-model'
        ];

        $this->service->validateModelConfig($config, 'test-model');
    }

    public function test_validate_model_config_throws_for_empty_api_key()
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('API key not configured for model test-model');

        $config = [
            'provider' => 'test-provider',
            'model' => 'test-model',
            'api_key' => ''
        ];

        $this->service->validateModelConfig($config, 'test-model');
    }
}

/**
 * Testable implementation of AbstractLLMService for testing
 */
class TestableAbstractLLMService extends AbstractLLMService
{
    private array $mockResponses = [];
    private int $responseIndex = 0;

    protected function getProviderName(): string
    {
        return 'test-provider';
    }

    public function analyze(string $content, string $prompt = null): string
    {
        if (!empty($this->mockResponses)) {
            $response = $this->mockResponses[$this->responseIndex % count($this->mockResponses)];
            $this->responseIndex++;
            
            if ($response instanceof \Exception) {
                throw $response;
            }
            
            return $response;
        }
        
        return 'default test response';
    }

    protected function performAnalysis(string $text, ?string $customPrompt = null): array
    {
        // Mock implementation for testing
        $result = $this->analyze($text, $customPrompt);
        return ['result' => $result];
    }

    // Test helper methods
    public function setModels(array $models): void
    {
        $this->models = $models;
    }

    public function getModels(): array
    {
        return $this->models;
    }

    public function setMockResponse(string $response): void
    {
        $this->mockResponses = [$response];
        $this->responseIndex = 0;
    }

    public function setMockResponses(array $responses): void
    {
        $this->mockResponses = $responses;
        $this->responseIndex = 0;
    }

    public function getCurrentModelKey(): string
    {
        return $this->currentModelKey ?? '';
    }

    // Expose protected methods for testing
    public function getDefaultModelKey(): string
    {
        return parent::getDefaultModelKey();
    }

    public function setCurrentModelKey(string $modelKey): void
    {
        parent::setCurrentModelKey($modelKey);
    }

    public function getCurrentModelConfig(): array
    {
        return parent::getCurrentModelConfig();
    }

    public function analyzeWithRetry(string $content, string $prompt, int $maxAttempts): string
    {
        return parent::analyzeWithRetry($content, $prompt, $maxAttempts);
    }

    public function validateModelConfig(array $config, string $modelKey): void
    {
        parent::validateModelConfig($config, $modelKey);
    }
}