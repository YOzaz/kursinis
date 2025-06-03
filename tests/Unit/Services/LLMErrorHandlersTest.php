<?php

namespace Tests\Unit\Services;

use App\Services\Exceptions\LLMException;
use App\Services\Exceptions\OpenAIErrorHandler;
use App\Services\Exceptions\ClaudeErrorHandler;
use App\Services\Exceptions\GeminiErrorHandler;
use Exception;
use GuzzleHttp\Exception\ClientException;
use GuzzleHttp\Psr7\Request;
use GuzzleHttp\Psr7\Response;
use Tests\TestCase;

class LLMErrorHandlersTest extends TestCase
{
    public function test_openai_quota_exceeded_error_detection(): void
    {
        $handler = new OpenAIErrorHandler();
        
        // Test various quota exceeded scenarios
        $quotaExceptions = [
            new Exception('You exceeded your current quota, please check your plan and billing details.', 429),
            new Exception('Error code: 429 - insufficient_quota', 429),
            new Exception('HTTP 402 Payment Required', 402),
        ];
        
        foreach ($quotaExceptions as $exception) {
            $llmException = $handler->handleException($exception);
            
            $this->assertInstanceOf(LLMException::class, $llmException);
            $this->assertTrue($llmException->isQuotaRelated(), 
                "Should detect quota error for: " . $exception->getMessage());
            $this->assertFalse($llmException->shouldFailBatch(), 
                "Quota errors should not fail entire batch");
            $this->assertEquals('openai', $llmException->getProvider());
        }
    }

    public function test_openai_rate_limit_error_detection(): void
    {
        $handler = new OpenAIErrorHandler();
        
        $rateLimitException = new Exception('Rate limit exceeded. Please wait before making more requests.', 429);
        $llmException = $handler->handleException($rateLimitException);
        
        $this->assertTrue($llmException->isRetryable());
        $this->assertEquals('rate_limit_exceeded', $llmException->getErrorType());
        $this->assertEquals(429, $llmException->getStatusCode());
        $this->assertFalse($llmException->shouldFailBatch());
    }

    public function test_openai_authentication_error_detection(): void
    {
        $handler = new OpenAIErrorHandler();
        
        $authException = new Exception('Invalid API key provided', 401);
        $llmException = $handler->handleException($authException);
        
        $this->assertFalse($llmException->isRetryable());
        $this->assertFalse($llmException->isQuotaRelated());
        $this->assertEquals('authentication_error', $llmException->getErrorType());
        $this->assertEquals(401, $llmException->getStatusCode());
        $this->assertTrue($llmException->shouldFailBatch());
    }

    public function test_claude_rate_limit_error_detection(): void
    {
        $handler = new ClaudeErrorHandler();
        
        $rateLimitException = new Exception('rate_limit_error: Request rate too high', 429);
        $llmException = $handler->handleException($rateLimitException);
        
        $this->assertTrue($llmException->isRetryable());
        $this->assertTrue($llmException->isQuotaRelated());
        $this->assertEquals('rate_limit_error', $llmException->getErrorType());
        $this->assertEquals(429, $llmException->getStatusCode());
        $this->assertFalse($llmException->shouldFailBatch());
        $this->assertEquals('claude', $llmException->getProvider());
    }

    public function test_claude_overloaded_error_detection(): void
    {
        $handler = new ClaudeErrorHandler();
        
        $overloadedException = new Exception('overloaded_error: Service temporarily overloaded', 529);
        $llmException = $handler->handleException($overloadedException);
        
        $this->assertTrue($llmException->isRetryable());
        $this->assertEquals('overloaded_error', $llmException->getErrorType());
        $this->assertEquals(529, $llmException->getStatusCode());
        $this->assertFalse($llmException->shouldFailBatch());
    }

    public function test_gemini_quota_exceeded_error_detection(): void
    {
        $handler = new GeminiErrorHandler();
        
        // Gemini free tier billing error
        $billingException = new Exception('Gemini API free tier is not available in your country. Please enable billing on your project in Google AI Studio.', 400);
        $llmException = $handler->handleException($billingException);
        
        $this->assertTrue($llmException->isQuotaRelated());
        $this->assertEquals('FAILED_PRECONDITION', $llmException->getErrorType());
        $this->assertEquals(400, $llmException->getStatusCode());
        $this->assertFalse($llmException->shouldFailBatch());
        $this->assertEquals('gemini', $llmException->getProvider());
    }

    public function test_gemini_rate_limit_error_detection(): void
    {
        $handler = new GeminiErrorHandler();
        
        $rateLimitException = new Exception('You\'ve exceeded the rate limit and are sending too many requests per minute with the free tier Gemini API.', 429);
        $llmException = $handler->handleException($rateLimitException);
        
        $this->assertTrue($llmException->isRetryable());
        $this->assertTrue($llmException->isQuotaRelated());
        $this->assertEquals('RESOURCE_EXHAUSTED', $llmException->getErrorType());
        $this->assertEquals(429, $llmException->getStatusCode());
        $this->assertFalse($llmException->shouldFailBatch());
    }

    public function test_gemini_permission_denied_error_detection(): void
    {
        $handler = new GeminiErrorHandler();
        
        $permissionException = new Exception('Your API key doesn\'t have the required permissions.', 403);
        $llmException = $handler->handleException($permissionException);
        
        $this->assertFalse($llmException->isRetryable());
        $this->assertFalse($llmException->isQuotaRelated());
        $this->assertEquals('PERMISSION_DENIED', $llmException->getErrorType());
        $this->assertEquals(403, $llmException->getStatusCode());
        $this->assertTrue($llmException->shouldFailBatch());
    }

    public function test_guzzle_http_exception_handling(): void
    {
        $handler = new OpenAIErrorHandler();
        
        // Create exception with JSON error response in message
        $errorResponseBody = json_encode([
            'error' => [
                'message' => 'You exceeded your current quota',
                'type' => 'insufficient_quota',
                'code' => 'insufficient_quota'
            ]
        ]);
        
        $guzzleException = new Exception("HTTP 429 Too Many Requests: $errorResponseBody", 429);
        $llmException = $handler->handleException($guzzleException);
        
        $this->assertEquals(429, $llmException->getStatusCode());
        $this->assertTrue($llmException->isQuotaRelated());
        $this->assertEquals('insufficient_quota', $llmException->getErrorType());
    }

    public function test_status_code_extraction_from_message(): void
    {
        $handler = new OpenAIErrorHandler();
        
        // Test various message formats
        $messageFormats = [
            'Error code: 401 - Unauthorized' => 401,
            'HTTP 403 Forbidden' => 403,
            '429 Too Many Requests' => 429,
            'Status: 502 Bad Gateway' => 502
        ];
        
        foreach ($messageFormats as $message => $expectedCode) {
            $exception = new Exception($message);
            $llmException = $handler->handleException($exception);
            
            $this->assertEquals($expectedCode, $llmException->getStatusCode(),
                "Failed to extract status code from: $message");
        }
    }

    public function test_unknown_error_defaults(): void
    {
        $handler = new OpenAIErrorHandler();
        
        $unknownException = new Exception('Some unknown error occurred', 0); // Code 0, not 500
        $llmException = $handler->handleException($unknownException);
        
        $this->assertEquals(500, $llmException->getStatusCode()); // Should default to 500
        $this->assertEquals('unknown_error', $llmException->getErrorType());
        $this->assertFalse($llmException->isQuotaRelated());
        $this->assertTrue($llmException->isRetryable()); // 500 should be retryable
        $this->assertFalse($llmException->shouldFailBatch()); // Since it's retryable
    }

    public function test_server_error_is_retryable(): void
    {
        $handlers = [
            new OpenAIErrorHandler(),
            new ClaudeErrorHandler(),
            new GeminiErrorHandler()
        ];
        
        $serverErrorCodes = [500, 502, 503, 504];
        
        foreach ($handlers as $handler) {
            foreach ($serverErrorCodes as $statusCode) {
                $exception = new Exception("Server Error", $statusCode);
                $llmException = $handler->handleException($exception);
                
                $this->assertTrue($llmException->isRetryable(),
                    "Status $statusCode should be retryable for " . $handler->getProviderName());
                $this->assertFalse($llmException->shouldFailBatch(),
                    "Status $statusCode should not fail batch for " . $handler->getProviderName());
            }
        }
    }
}