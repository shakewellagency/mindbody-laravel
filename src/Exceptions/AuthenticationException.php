<?php

declare(strict_types=1);

namespace Shakewell\MindbodyLaravel\Exceptions;

/**
 * Exception thrown when authentication with the Mindbody API fails
 */
class AuthenticationException extends MindbodyApiException
{
    /**
     * Create a new authentication exception
     */
    public static function invalidCredentials(string $username = ''): self
    {
        $message = $username 
            ? "Authentication failed for user: {$username}" 
            : 'Authentication failed: Invalid credentials';

        return new self($message, 401);
    }

    /**
     * Create exception for expired token
     */
    public static function tokenExpired(): self
    {
        return new self('Authentication token has expired', 401);
    }

    /**
     * Create exception for missing API key
     */
    public static function missingApiKey(): self
    {
        return new self('API key is required for authentication', 401);
    }

    /**
     * Create exception for invalid API key
     */
    public static function invalidApiKey(): self
    {
        return new self('Invalid API key provided', 401);
    }

    /**
     * Create exception for missing site ID
     */
    public static function missingSiteId(): self
    {
        return new self('Site ID is required for API requests', 400);
    }

    /**
     * Create exception for insufficient permissions
     */
    public static function insufficientPermissions(string $operation = ''): self
    {
        $message = $operation 
            ? "Insufficient permissions to perform operation: {$operation}"
            : 'Insufficient permissions for this operation';

        return new self($message, 403);
    }
}