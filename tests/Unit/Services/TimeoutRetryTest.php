<?php

namespace Tests\Unit\Services;

use Tests\TestCase;
use App\Services\ClaudeService;
use App\Services\PromptService;
use App\Services\Exceptions\LLMException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Config;
use Exception;

class TimeoutRetryTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        
        // Configure retry settings for tests
        Config::set('llm.error_handling.max_retries_per_model', 2);
        Config::set('llm.error_handling.retry_delay_seconds', 1);
        Config::set('llm.error_handling.exponential_backoff', true);
        Config::set('llm.error_handling.timeout_seconds', 30);
    }

    public function test_claude_service_retries_on_timeout()
    {
        // Mock HTTP timeout responses
        Http::fake([
            'api.anthropic.com/*' => Http::sequence()
                ->push(null, 599) // First attempt: connection timeout
                ->push(null, 504) // Second attempt: gateway timeout
                ->push([
                    'content' => [
                        ['text' => '```json
                        {
                            "annotations": [
                                {
                                    "type": "labels",
                                    "value": {"labels": ["emotionalExpression"]},
                                    "start": 0,
                                    "end": 10
                                }
                            ]
                        }
                        ```']
                    ]
                ], 200) // Third attempt: success
        ]);

        $promptService = app(PromptService::class);
        $claudeService = new ClaudeService($promptService);
        
        // Set valid model for test
        $claudeService->setModel('claude-opus-4');

        $result = $claudeService->analyzeText('Test timeout text');

        // Should succeed after retries
        $this->assertIsArray($result);
        $this->assertArrayHasKey('annotations', $result);
    }

    public function test_timeout_error_is_classified_correctly()
    {
        Http::fake([
            'api.anthropic.com/*' => function () {
                throw new Exception('cURL error 28: Operation timed out after 60000 milliseconds with 0 bytes received');
            }
        ]);

        $promptService = app(PromptService::class);
        $claudeService = new ClaudeService($promptService);
        $claudeService->setModel('claude-opus-4');

        try {
            $claudeService->analyzeText('Test timeout');
            $this->fail('Expected LLMException to be thrown');
        } catch (LLMException $e) {
            $this->assertEquals('timeout_error', $e->getErrorType());
            $this->assertFalse($e->shouldFailBatch());
            $this->assertEquals('claude', $e->getProvider());
        }
    }

    public function test_exponential_backoff_configuration()
    {
        $this->assertEquals(2, config('llm.error_handling.max_retries_per_model'));
        $this->assertEquals(1, config('llm.error_handling.retry_delay_seconds'));
        $this->assertTrue(config('llm.error_handling.exponential_backoff'));
        $this->assertEquals(30, config('llm.error_handling.timeout_seconds'));
    }

    public function test_timeout_error_does_not_fail_batch()
    {
        Http::fake([
            'api.anthropic.com/*' => function () {
                throw new Exception('cURL error 28: Operation timed out after 60000 milliseconds');
            }
        ]);

        $promptService = app(PromptService::class);
        $claudeService = new ClaudeService($promptService);
        $claudeService->setModel('claude-opus-4');

        try {
            $claudeService->analyzeText('Test');
        } catch (LLMException $e) {
            // Timeout errors should not fail the entire batch
            $this->assertFalse($e->shouldFailBatch());
            $this->assertEquals('timeout_error', $e->getErrorType());
        }
    }
}