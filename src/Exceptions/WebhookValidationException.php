<?php

declare(strict_types=1);

namespace Shakewell\MindbodyLaravel\Exceptions;

/**
 * Exception thrown when webhook validation fails.
 */
class WebhookValidationException extends MindbodyException
{
    /**
     * Create exception for missing webhook signature.
     */
    public static function missingSignature(): self
    {
        return new self('Webhook signature is missing from request headers', 400);
    }

    /**
     * Create exception for invalid webhook signature.
     */
    public static function invalidSignature(): self
    {
        return new self('Webhook signature validation failed', 400);
    }

    /**
     * Create exception for missing signature key configuration.
     */
    public static function missingSignatureKey(): self
    {
        return new self('Webhook signature key is not configured', 500);
    }

    /**
     * Create exception for invalid webhook payload.
     */
    public static function invalidPayload(string $reason = ''): self
    {
        $message = $reason
            ? "Invalid webhook payload: {$reason}"
            : 'Invalid webhook payload received';

        return new self($message, 400);
    }

    /**
     * Create exception for unsupported webhook event.
     */
    public static function unsupportedEvent(string $eventType): self
    {
        return new self("Unsupported webhook event type: {$eventType}", 400);
    }

    /**
     * Create exception for webhook processing failure.
     */
    public static function processingFailed(string $reason = ''): self
    {
        $message = $reason
            ? "Webhook processing failed: {$reason}"
            : 'Failed to process webhook event';

        return new self($message, 500);
    }
}
