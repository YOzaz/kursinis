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
        // Test that the service can be constructed
        $this->assertInstanceOf(TestableAbstractLLMService::class, $this->service);
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

    public function test_set_model_sets_correctly()
    {
        $this->service->setModels([
            'test-model' => ['provider' => 'test-provider']
        ]);

        $result = $this->service->setModel('test-model');
        $this->assertTrue($result);
        $this->assertEquals('test-model', $this->service->getModelName());
    }

    public function test_set_model_returns_false_for_invalid_model()
    {
        $result = $this->service->setModel('invalid-model');
        $this->assertFalse($result);
    }

    public function test_get_current_config_returns_correct_config()
    {
        $config = [
            'provider' => 'test-provider',
            'model' => 'test-model',
            'api_key' => 'test-key'
        ];

        $this->service->setModels(['test-model' => $config]);
        $this->service->setModel('test-model');

        $currentConfig = $this->service->getCurrentConfig();
        $this->assertEquals($config, $currentConfig);
    }

    public function test_get_current_config_returns_null_when_no_model_set()
    {
        $currentConfig = $this->service->getCurrentConfig();
        $this->assertNull($currentConfig);
    }

    public function test_analyze_text_succeeds_on_first_attempt()
    {
        $this->service->setModels(['test-model' => ['provider' => 'test-provider', 'api_key' => 'test-key']]);
        $this->service->setModel('test-model');

        $this->service->setMockResponse(['result' => 'success result']);

        $result = $this->service->analyzeText('test content', 'test prompt');
        $this->assertEquals(['result' => 'success result'], $result);
    }

    public function test_analyze_text_succeeds_after_failure()
    {
        $this->service->setModels(['test-model' => ['provider' => 'test-provider', 'api_key' => 'test-key']]);
        $this->service->setModel('test-model');

        $this->service->setMockResponses([
            new \Exception('First failure'),
            ['result' => 'success result']
        ]);

        Log::shouldReceive('warning')->once();
        Log::shouldReceive('info')->once();

        $result = $this->service->analyzeText('test content', 'test prompt');
        $this->assertEquals(['result' => 'success result'], $result);
    }

    public function test_analyze_text_fails_after_max_attempts()
    {
        $this->service->setModels(['test-model' => ['provider' => 'test-provider', 'api_key' => 'test-key']]);
        $this->service->setModel('test-model');

        $this->service->setMockResponses([
            new \Exception('First failure'),
            new \Exception('Second failure'),
            new \Exception('Third failure')
        ]);

        Log::shouldReceive('warning')->times(3);

        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('test-provider API neprieinamas po 3 bandymų: Third failure');

        $this->service->analyzeText('test content', 'test prompt');
    }

    public function test_is_configured_returns_true_for_valid_config()
    {
        $config = [
            'provider' => 'test-provider',
            'model' => 'test-model',
            'api_key' => 'test-key'
        ];

        $this->service->setModels(['test-model' => $config]);
        $this->service->setModel('test-model');
        
        $this->assertTrue($this->service->isConfigured());
    }

    public function test_is_configured_returns_false_for_missing_api_key()
    {
        $config = [
            'provider' => 'test-provider',
            'model' => 'test-model'
        ];

        $this->service->setModels(['test-model' => $config]);
        $this->service->setModel('test-model');
        
        $this->assertFalse($this->service->isConfigured());
    }

    public function test_is_configured_returns_false_for_empty_api_key()
    {
        $config = [
            'provider' => 'test-provider',
            'model' => 'test-model',
            'api_key' => ''
        ];

        $this->service->setModels(['test-model' => $config]);
        $this->service->setModel('test-model');
        
        $this->assertFalse($this->service->isConfigured());
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
        if (!empty($this->mockResponses)) {
            $response = $this->mockResponses[$this->responseIndex % count($this->mockResponses)];
            $this->responseIndex++;
            
            if ($response instanceof \Exception) {
                throw $response;
            }
            
            return $response;
        }
        
        return ['result' => 'default test response'];
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

    public function setMockResponse($response): void
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

    public function getCurrentConfig(): ?array
    {
        return parent::getCurrentConfig();
    }
}