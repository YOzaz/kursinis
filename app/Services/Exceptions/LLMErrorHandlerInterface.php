<?php

namespace App\Services\Exceptions;

use Exception;

/**
 * Interface for handling LLM service-specific errors.
 */
interface LLMErrorHandlerInterface
{
    /**
     * Convert a generic exception into an LLMException with proper classification.
     *
     * @param Exception $exception The original exception from the API call
     * @return LLMException The classified LLM exception
     */
    public function handleException(Exception $exception): LLMException;

    /**
     * Get the provider name for this error handler.
     *
     * @return string The provider name (e.g., 'openai', 'claude', 'gemini')
     */
    public function getProviderName(): string;

    /**
     * Check if a status code indicates a quota/billing related error.
     *
     * @param int $statusCode HTTP status code
     * @return bool True if quota/billing related
     */
    public function isQuotaError(int $statusCode): bool;

    /**
     * Check if a status code indicates a retryable error.
     *
     * @param int $statusCode HTTP status code
     * @param string $errorType Error type classification
     * @return bool True if the error is retryable
     */
    public function isRetryableError(int $statusCode, string $errorType = ''): bool;
}