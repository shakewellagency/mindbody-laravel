<?php

declare(strict_types=1);

namespace Shakewell\MindbodyLaravel\Events;

use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;
use Shakewell\MindbodyLaravel\Exceptions\MindbodyApiException;

/**
 * Event fired when an API call fails
 */
class ApiCallFailed
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public MindbodyApiException $exception;
    
    public string $endpoint;
    
    public string $method;
    
    public array $parameters;

    /**
     * Create a new event instance
     */
    public function __construct(
        MindbodyApiException $exception,
        string $endpoint,
        string $method,
        array $parameters = []
    ) {
        $this->exception = $exception;
        $this->endpoint = $endpoint;
        $this->method = $method;
        $this->parameters = $parameters;
    }

    /**
     * Get the error message
     */
    public function getErrorMessage(): string
    {
        return $this->exception->getMessage();
    }

    /**
     * Get the HTTP status code
     */
    public function getStatusCode(): ?int
    {
        return $this->exception->getStatusCode();
    }

    /**
     * Check if this is a rate limit error
     */
    public function isRateLimitError(): bool
    {
        return $this->exception->isRateLimitError();
    }

    /**
     * Check if this is a client error (4xx)
     */
    public function isClientError(): bool
    {
        return $this->exception->isClientError();
    }

    /**
     * Check if this is a server error (5xx)
     */
    public function isServerError(): bool
    {
        return $this->exception->isServerError();
    }

    /**
     * Get the API error details
     */
    public function getApiError(): ?array
    {
        return $this->exception->getApiError();
    }
}