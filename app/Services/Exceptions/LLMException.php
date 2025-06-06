<?php

namespace App\Services\Exceptions;

use Exception;

/**
 * Base exception for LLM service errors.
 */
class LLMException extends Exception
{
    protected int $statusCode;
    protected string $errorType;
    protected string $provider;
    protected bool $isRetryable;
    protected bool $isQuotaRelated;

    public function __construct(
        string $message,
        int $statusCode,
        string $errorType,
        string $provider,
        bool $isRetryable = false,
        bool $isQuotaRelated = false,
        ?Exception $previous = null
    ) {
        $this->statusCode = $statusCode;
        $this->errorType = $errorType;
        $this->provider = $provider;
        $this->isRetryable = $isRetryable;
        $this->isQuotaRelated = $isQuotaRelated;

        parent::__construct($message, $statusCode, $previous);
    }

    public function getStatusCode(): int
    {
        return $this->statusCode;
    }

    public function getErrorType(): string
    {
        return $this->errorType;
    }

    public function getProvider(): string
    {
        return $this->provider;
    }

    public function isRetryable(): bool
    {
        return $this->isRetryable;
    }

    public function isQuotaRelated(): bool
    {
        return $this->isQuotaRelated;
    }

    /**
     * Determine if this error should cause the entire batch to fail
     * or just mark this individual analysis as failed.
     */
    public function shouldFailBatch(): bool
    {
        // If the error is retryable or quota-related, don't fail the entire batch
        if ($this->isRetryable || $this->isQuotaRelated) {
            return false;
        }
        
        // These errors should not fail the entire batch, just skip this model
        $skipableErrors = [
            'timeout_error',
            'rate_limit_error', 
            'overloaded_error',
            'max_tokens_error',
            'safety_error'
        ];
        
        return !in_array($this->errorType, $skipableErrors);
    }
}