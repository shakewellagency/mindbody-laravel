<?php

declare(strict_types=1);
namespace Shakewell\MindbodyLaravel\Exceptions;

use Illuminate\Http\Client\Response;

/**
 * Exception thrown when the Mindbody API returns an error response.
 */
class MindbodyApiException extends MindbodyException
{
    protected ?Response $response = null;

    protected ?array $apiError = null;

    /**
     * Create a new API exception from a response.
     */
    public static function fromResponse(Response $response): self
    {
        $data = $response->json();
        $apiError = $data['Error'] ?? null;

        $message = $apiError['Message'] ?? 'Unknown API error';
        $code = $apiError['Code'] ?? $response->status();

        $exception = new static($message, $code);
        $exception->response = $response;
        $exception->apiError = $apiError;

        $exception->setContext([
            'status_code' => $response->status(),
            'response_body' => $data,
            'api_error' => $apiError,
        ]);

        return $exception;
    }

    /**
     * Get the HTTP response that caused this exception.
     */
    public function getResponse(): ?Response
    {
        return $this->response;
    }

    /**
     * Get the API error details.
     */
    public function getApiError(): ?array
    {
        return $this->apiError;
    }

    /**
     * Get the HTTP status code.
     */
    public function getStatusCode(): ?int
    {
        return $this->response?->status();
    }

    /**
     * Check if this is a specific API error code.
     */
    public function isApiError(string $errorCode): bool
    {
        return ($this->apiError['Code'] ?? null) === $errorCode;
    }

    /**
     * Check if this is a client error (4xx).
     */
    public function isClientError(): bool
    {
        $status = $this->getStatusCode();

        return null !== $status && $status >= 400 && $status < 500;
    }

    /**
     * Check if this is a server error (5xx).
     */
    public function isServerError(): bool
    {
        $status = $this->getStatusCode();

        return null !== $status && $status >= 500;
    }

    /**
     * Check if this is a rate limit error.
     */
    public function isRateLimitError(): bool
    {
        return 429 === $this->getStatusCode() || $this->isApiError('TooManyRequests');
    }
}
