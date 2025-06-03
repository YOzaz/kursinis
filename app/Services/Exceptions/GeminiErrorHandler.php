<?php

namespace App\Services\Exceptions;

use Exception;

/**
 * Gemini/Google AI-specific error handler.
 * 
 * Based on Gemini API Troubleshooting:
 * https://ai.google.dev/gemini-api/docs/troubleshooting
 */
class GeminiErrorHandler implements LLMErrorHandlerInterface
{
    public function handleException(Exception $exception): LLMException
    {
        $message = $exception->getMessage();
        $statusCode = $this->extractStatusCode($exception);
        $errorType = $this->extractErrorType($message);

        return new LLMException(
            message: $message,
            statusCode: $statusCode,
            errorType: $errorType,
            provider: $this->getProviderName(),
            isRetryable: $this->isRetryableError($statusCode),
            isQuotaRelated: $this->isQuotaError($statusCode),
            previous: $exception
        );
    }

    public function getProviderName(): string
    {
        return 'gemini';
    }

    public function isQuotaError(int $statusCode): bool
    {
        return match ($statusCode) {
            400, // FAILED_PRECONDITION - free tier not available, billing required
            429  // RESOURCE_EXHAUSTED - rate limit/quota exceeded
                => true,
            default => false,
        };
    }

    public function isRetryableError(int $statusCode): bool
    {
        return match ($statusCode) {
            429, // RESOURCE_EXHAUSTED - rate limit exceeded, retryable
            500, // Internal server error
            502, // Bad gateway
            503, // Service unavailable
            504, // Gateway timeout
            408, // Request timeout
            598, // Network read timeout error
            599  // Network connect timeout error
                => true,
            default => false,
        };
    }

    /**
     * Extract HTTP status code from various exception types.
     */
    private function extractStatusCode(Exception $exception): int
    {
        // Check if it's a Guzzle HTTP exception
        if (method_exists($exception, 'getResponse')) {
            $response = $exception->getResponse();
            if ($response && method_exists($response, 'getStatusCode')) {
                return $response->getStatusCode();
            }
        }

        // Check if exception code is a valid HTTP status
        if (method_exists($exception, 'getCode') && $exception->getCode() >= 400) {
            return $exception->getCode();
        }

        // Parse status code from message
        if (preg_match('/(\d{3})\s+/i', $exception->getMessage(), $matches)) {
            return (int) $matches[1];
        }

        // Default to 500 for unknown errors
        return 500;
    }

    /**
     * Extract error type from the exception message.
     */
    private function extractErrorType(string $message): string
    {
        // Look for Gemini specific error conditions
        $lowerMessage = strtolower($message);
        
        if (str_contains($lowerMessage, 'curl error 28') || 
            str_contains($lowerMessage, 'operation timed out') ||
            str_contains($lowerMessage, 'timeout')) {
            return 'timeout_error';
        }
        
        if (str_contains($lowerMessage, 'viršytas token limitas') ||
            str_contains($lowerMessage, 'max tokens') ||
            str_contains($lowerMessage, 'per ilgas')) {
            return 'max_tokens_error';
        }
        
        if (str_contains($lowerMessage, 'blokavo atsakymą dėl saugumo') ||
            str_contains($lowerMessage, 'safety')) {
            return 'safety_error';
        }
        
        if (str_contains($lowerMessage, 'failed_precondition') || 
            str_contains($lowerMessage, 'free tier is not available') ||
            str_contains($lowerMessage, 'enable billing')) {
            return 'FAILED_PRECONDITION';
        }

        if (str_contains($lowerMessage, 'resource_exhausted') || 
            str_contains($lowerMessage, 'exceeded the rate limit') ||
            str_contains($lowerMessage, 'too many requests')) {
            return 'RESOURCE_EXHAUSTED';
        }

        if (str_contains($lowerMessage, 'permission_denied') || 
            str_contains($lowerMessage, 'api key') ||
            str_contains($lowerMessage, 'required permissions')) {
            return 'PERMISSION_DENIED';
        }

        if (str_contains($lowerMessage, 'unauthenticated') || 
            str_contains($lowerMessage, 'authentication')) {
            return 'UNAUTHENTICATED';
        }

        return 'unknown_error';
    }
}