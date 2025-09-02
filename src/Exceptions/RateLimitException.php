<?php

declare(strict_types=1);

namespace Shakewell\MindbodyLaravel\Exceptions;

/**
 * Exception thrown when API rate limits are exceeded
 */
class RateLimitException extends MindbodyApiException
{
    protected ?int $retryAfter = null;
    
    protected ?int $remainingRequests = null;

    /**
     * Create a new rate limit exception
     */
    public static function limitExceeded(
        int $retryAfter = null,
        int $remainingRequests = null,
        string $limitType = 'requests'
    ): self {
        $message = "Rate limit exceeded for {$limitType}";
        
        if ($retryAfter !== null) {
            $message .= ". Retry after {$retryAfter} seconds";
        }

        $exception = new self($message, 429);
        $exception->retryAfter = $retryAfter;
        $exception->remainingRequests = $remainingRequests;

        $exception->setContext([
            'retry_after' => $retryAfter,
            'remaining_requests' => $remainingRequests,
            'limit_type' => $limitType,
        ]);

        return $exception;
    }

    /**
     * Create exception for daily limit exceeded
     */
    public static function dailyLimitExceeded(): self
    {
        return self::limitExceeded(null, 0, 'daily requests');
    }

    /**
     * Create exception for per-minute limit exceeded
     */
    public static function perMinuteLimitExceeded(int $retryAfter = 60): self
    {
        return self::limitExceeded($retryAfter, 0, 'requests per minute');
    }

    /**
     * Get the number of seconds to wait before retrying
     */
    public function getRetryAfter(): ?int
    {
        return $this->retryAfter;
    }

    /**
     * Get the number of remaining requests (if available)
     */
    public function getRemainingRequests(): ?int
    {
        return $this->remainingRequests;
    }

    /**
     * Check if we should retry after waiting
     */
    public function shouldRetry(): bool
    {
        return $this->retryAfter !== null && $this->retryAfter > 0;
    }
}