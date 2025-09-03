<?php

declare(strict_types=1);

namespace Shakewell\MindbodyLaravel\Exceptions;

use Exception;

/**
 * Base exception class for all Mindbody-related exceptions.
 */
class MindbodyException extends \Exception
{
    /**
     * Additional context data.
     */
    protected array $context = [];

    /**
     * Create a new Mindbody exception instance.
     */
    public function __construct(
        string $message = '',
        string|int $code = 0,
        ?\Exception $previous = null,
        array $context = []
    ) {
        // Convert string codes to hash integers for Exception compatibility
        $intCode = is_string($code) ? crc32($code) : $code;
        parent::__construct($message, $intCode, $previous);
        $this->context = $context;

        // Store the original code for API error comparison
        if (is_string($code)) {
            $this->context['original_code'] = $code;
        }
    }

    /**
     * Get the exception context.
     */
    public function getContext(): array
    {
        return $this->context;
    }

    /**
     * Set additional context.
     */
    public function setContext(array $context): self
    {
        $this->context = $context;

        return $this;
    }

    /**
     * Add context data.
     */
    public function addContext(string $key, mixed $value): self
    {
        $this->context[$key] = $value;

        return $this;
    }
}
