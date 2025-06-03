<?php

namespace App\Services\Exceptions;

use Exception;

/**
 * OpenAI-specific error handler.
 * 
 * Based on OpenAI API Error Codes:
 * https://platform.openai.com/docs/guides/error-codes/api-errors
 */
class OpenAIErrorHandler implements LLMErrorHandlerInterface
{
    public function handleException(Exception $exception): LLMException
    {
        $message = $exception->getMessage();
        $statusCode = $this->extractStatusCode($exception);
        $errorType = $this->extractErrorType($message);

        $isQuotaRelated = $this->isQuotaError($statusCode) || $this->isQuotaErrorMessage($message);
        $isRetryable = $this->isRetryableError($statusCode) || $this->isRateLimitButNotQuota($errorType, $message);
        
        return new LLMException(
            message: $message,
            statusCode: $statusCode,
            errorType: $errorType,
            provider: $this->getProviderName(),
            isRetryable: $isRetryable,
            isQuotaRelated: $isQuotaRelated,
            previous: $exception
        );
    }

    public function getProviderName(): string
    {
        return 'openai';
    }

    public function isQuotaError(int $statusCode): bool
    {
        return match ($statusCode) {
            402, // Payment required
            429  // Rate limit exceeded (can also be quota exceeded)
                => true,
            default => false,
        };
    }

    public function isRetryableError(int $statusCode): bool
    {
        return match ($statusCode) {
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

        // Check if it's an OpenAI library exception with status code
        if (method_exists($exception, 'getCode') && $exception->getCode() > 0) {
            return $exception->getCode();
        }

        // Parse status code from message if available
        if (preg_match('/error\s+code[:\s]+(\d{3})/i', $exception->getMessage(), $matches)) {
            return (int) $matches[1];
        }

        // Parse HTTP status from message
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
        // Look for OpenAI specific error types in message
        if (preg_match('/"type":\s*"([^"]+)"/i', $message, $matches)) {
            return $matches[1];
        }

        if (preg_match('/"code":\s*"([^"]+)"/i', $message, $matches)) {
            return $matches[1];
        }
        
        // Try to parse JSON response body if present
        if (preg_match('/\{.*"error".*\}/s', $message, $jsonMatches)) {
            $errorData = json_decode($jsonMatches[0], true);
            if (isset($errorData['error']['type'])) {
                return $errorData['error']['type'];
            }
            if (isset($errorData['error']['code'])) {
                return $errorData['error']['code'];
            }
        }

        // Classify based on message content
        $lowerMessage = strtolower($message);
        
        if (str_contains($lowerMessage, 'curl error 28') || 
            str_contains($lowerMessage, 'operation timed out') ||
            str_contains($lowerMessage, 'timeout')) {
            return 'timeout_error';
        }
        
        if (str_contains($lowerMessage, 'insufficient_quota') || 
            str_contains($lowerMessage, 'quota')) {
            return 'insufficient_quota';
        }

        if (str_contains($lowerMessage, 'rate limit')) {
            return 'rate_limit_exceeded';
        }

        if (str_contains($lowerMessage, 'unauthorized') || 
            str_contains($lowerMessage, 'authentication') ||
            str_contains($lowerMessage, 'invalid api key')) {
            return 'authentication_error';
        }

        if (str_contains($lowerMessage, 'permission') || 
            str_contains($lowerMessage, 'forbidden')) {
            return 'permission_error';
        }

        return 'unknown_error';
    }

    /**
     * Check if the error message indicates a quota issue, regardless of status code.
     */
    private function isQuotaErrorMessage(string $message): bool
    {
        $quotaIndicators = [
            'exceeded your current quota',
            'insufficient_quota',
            'billing details',
            'insufficient credits',
            'quota exceeded'
        ];

        $lowerMessage = strtolower($message);
        
        foreach ($quotaIndicators as $indicator) {
            if (str_contains($lowerMessage, $indicator)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Check if this is a rate limit error but not a quota error.
     */
    private function isRateLimitButNotQuota(string $errorType, string $message): bool
    {
        return $errorType === 'rate_limit_exceeded' || 
               (str_contains(strtolower($message), 'rate limit') && 
                !$this->isQuotaErrorMessage($message));
    }
}