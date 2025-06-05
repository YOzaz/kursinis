<?php

namespace App\Services\Exceptions;

use Exception;

/**
 * Claude/Anthropic-specific error handler.
 * 
 * Based on Anthropic API Errors:
 * https://docs.anthropic.com/en/api/errors
 */
class ClaudeErrorHandler implements LLMErrorHandlerInterface
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
            isRetryable: $this->isRetryableError($statusCode, $errorType),
            isQuotaRelated: $this->isQuotaError($statusCode),
            previous: $exception
        );
    }

    public function getProviderName(): string
    {
        return 'claude';
    }

    public function isQuotaError(int $statusCode): bool
    {
        return match ($statusCode) {
            429, // Rate limit error - can indicate quota exceeded
            402  // Payment required (if Anthropic uses this)
                => true,
            default => false,
        };
    }

    public function isRetryableError(int $statusCode, string $errorType = ''): bool
    {
        return match ($statusCode) {
            429, // Rate limit error - retryable with backoff
            500, // Internal server error
            502, // Bad gateway  
            503, // Service unavailable
            504, // Gateway timeout
            529, // Overloaded error (as mentioned in docs)
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
        // Look for Anthropic specific error types
        if (preg_match('/"type":\s*"([^"]+)"/i', $message, $matches)) {
            return $matches[1];
        }

        // Classify based on message content
        $lowerMessage = strtolower($message);
        
        if (str_contains($lowerMessage, 'curl error 28') || 
            str_contains($lowerMessage, 'operation timed out') ||
            str_contains($lowerMessage, 'timeout')) {
            return 'timeout_error';
        }
        
        if (str_contains($lowerMessage, 'rate_limit_error') || 
            str_contains($lowerMessage, 'rate limit')) {
            return 'rate_limit_error';
        }

        if (str_contains($lowerMessage, 'authentication_error') || 
            str_contains($lowerMessage, 'unauthorized')) {
            return 'authentication_error';
        }

        if (str_contains($lowerMessage, 'permission_error') || 
            str_contains($lowerMessage, 'forbidden')) {
            return 'permission_error';
        }

        if (str_contains($lowerMessage, 'overloaded_error') || 
            str_contains($lowerMessage, 'overloaded')) {
            return 'overloaded_error';
        }

        return 'unknown_error';
    }
}