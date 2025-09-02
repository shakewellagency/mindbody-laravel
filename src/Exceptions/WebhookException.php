<?php

declare(strict_types=1);
namespace Shakewell\MindbodyLaravel\Exceptions;

/**
 * Exception thrown for general webhook-related errors.
 */
class WebhookException extends MindbodyException
{
    /**
     * Create exception for webhook subscription errors.
     */
    public static function subscriptionFailed(string $reason = ''): self
    {
        $message = $reason
            ? "Webhook subscription failed: {$reason}"
            : 'Failed to create webhook subscription';

        return new self($message, 500);
    }

    /**
     * Create exception for webhook unsubscription errors.
     */
    public static function unsubscriptionFailed(string $reason = ''): self
    {
        $message = $reason
            ? "Webhook unsubscription failed: {$reason}"
            : 'Failed to remove webhook subscription';

        return new self($message, 500);
    }

    /**
     * Create exception for webhook list retrieval errors.
     */
    public static function listFailed(string $reason = ''): self
    {
        $message = $reason
            ? "Failed to list webhook subscriptions: {$reason}"
            : 'Failed to retrieve webhook subscriptions';

        return new self($message, 500);
    }

    /**
     * Create exception for invalid webhook configuration.
     */
    public static function invalidConfiguration(string $reason = ''): self
    {
        $message = $reason
            ? "Invalid webhook configuration: {$reason}"
            : 'Webhook configuration is invalid';

        return new self($message, 500);
    }
}