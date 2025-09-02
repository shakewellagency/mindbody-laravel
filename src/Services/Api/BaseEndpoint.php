<?php

declare(strict_types=1);
namespace Shakewell\MindbodyLaravel\Services\Api;

use Carbon\Carbon;
use Shakewell\MindbodyLaravel\Services\MindbodyClient;

/**
 * Base class for all API endpoints.
 */
abstract class BaseEndpoint
{
    protected MindbodyClient $client;

    protected string $endpoint;

    protected array $defaultParams = [];

    /**
     * Create a new endpoint instance.
     */
    public function __construct(MindbodyClient $client)
    {
        $this->client = $client;
    }

    /**
     * Get all records with automatic pagination.
     */
    protected function getAll(string $endpoint, array $params = [], int $limit = 100): array
    {
        if (false === $this->client->getConfig('features.auto_pagination')) {
            return $this->client->get($endpoint, array_merge($this->defaultParams, $params));
        }

        return $this->paginate($endpoint, $params, $limit);
    }

    /**
     * Paginate through all results automatically.
     */
    protected function paginate(string $endpoint, array $params = [], int $limit = 100): array
    {
        $allResults = [];
        $offset = 0;
        $hasMore = true;

        while ($hasMore) {
            $requestParams = array_merge($this->defaultParams, $params, [
                'limit' => $limit,
                'offset' => $offset,
            ]);

            $response = $this->client->get($endpoint, $requestParams);
            $results = $this->extractResultsFromResponse($response);

            if (empty($results)) {
                break;
            }

            $allResults = array_merge($allResults, $results);

            // Check pagination info
            $pagination = $response['PaginationResponse'] ?? null;
            if ($pagination) {
                $totalResults = $pagination['TotalResults'] ?? 0;
                $hasMore = ($offset + $limit) < $totalResults;
            } else {
                // Fallback: assume no more results if we got fewer than requested
                $hasMore = \count($results) >= $limit;
            }

            $offset += $limit;

            // Safety check to prevent infinite loops
            if ($offset > 50000) {
                break;
            }
        }

        return $allResults;
    }

    /**
     * Extract results from API response
     * Override in child classes for endpoint-specific result extraction.
     */
    protected function extractResultsFromResponse(array $response): array
    {
        // Default behavior - child classes should override this
        $keys = array_keys($response);

        // Look for common result keys
        $resultKeys = ['Results', 'Data', ucfirst($this->endpoint)];

        foreach ($resultKeys as $key) {
            if (isset($response[$key])) {
                return $response[$key];
            }
        }

        // If no standard key found, return the response as-is
        return $response;
    }

    /**
     * Build endpoint URL.
     */
    protected function buildEndpoint(string $path): string
    {
        return trim($this->endpoint, '/').'/'.trim($path, '/');
    }

    /**
     * Validate required parameters.
     */
    protected function validateRequired(array $params, array $required): void
    {
        $missing = array_diff($required, array_keys($params));

        if (! empty($missing)) {
            throw new \InvalidArgumentException(
                'Missing required parameters: '.implode(', ', $missing)
            );
        }
    }

    /**
     * Format date parameter for API.
     *
     * @param mixed $date
     */
    protected function formatDate($date): string
    {
        if ($date instanceof Carbon) {
            return $date->toIso8601String();
        }

        if (\is_string($date)) {
            return Carbon::parse($date)->toIso8601String();
        }

        throw new \InvalidArgumentException('Date must be a Carbon instance or valid date string');
    }

    /**
     * Format date parameter for API (date only, no time).
     *
     * @param mixed $date
     */
    protected function formatDateOnly($date): string
    {
        if ($date instanceof Carbon) {
            return $date->format('Y-m-d');
        }

        if (\is_string($date)) {
            return Carbon::parse($date)->format('Y-m-d');
        }

        throw new \InvalidArgumentException('Date must be a Carbon instance or valid date string');
    }

    /**
     * Clean and prepare parameters.
     */
    protected function prepareParams(array $params): array
    {
        // Remove null values
        $params = array_filter($params, static fn ($value) => null !== $value);

        // Convert boolean values to strings as expected by API
        array_walk_recursive($params, static function (&$value) {
            if (\is_bool($value)) {
                $value = $value ? 'true' : 'false';
            }
        });

        return $params;
    }

    /**
     * Apply default parameters.
     */
    protected function applyDefaults(array $params): array
    {
        return array_merge($this->defaultParams, $params);
    }

    /**
     * Transform single record data for consistent output.
     */
    protected function transformRecord(array $record): array
    {
        if (! $this->client->getConfig('features.response_transformation')) {
            return $record;
        }

        // Add consistent ID field if missing
        if (! isset($record['Id']) && isset($record['ID'])) {
            $record['Id'] = $record['ID'];
        }

        // Convert date strings to Carbon instances if configured
        $dateFields = $this->getDateFields();
        foreach ($dateFields as $field) {
            if (isset($record[$field]) && \is_string($record[$field])) {
                try {
                    $record[$field.'_parsed'] = Carbon::parse($record[$field]);
                } catch (\Exception $e) {
                    // Keep original value if parsing fails
                }
            }
        }

        return $record;
    }

    /**
     * Transform multiple records.
     */
    protected function transformRecords(array $records): array
    {
        if (! $this->client->getConfig('features.response_transformation')) {
            return $records;
        }

        return array_map([$this, 'transformRecord'], $records);
    }

    /**
     * Get date fields that should be transformed
     * Override in child classes to specify endpoint-specific date fields.
     */
    protected function getDateFields(): array
    {
        return [
            'CreationDate',
            'LastModifiedDateTime',
            'StartDateTime',
            'EndDateTime',
            'Date',
            'ExpirationDate',
        ];
    }

    /**
     * Validate data against endpoint requirements.
     */
    protected function validateData(array $data, array $rules = []): array
    {
        if (! $this->client->getConfig('features.data_validation')) {
            return $data;
        }

        foreach ($rules as $field => $rule) {
            if (isset($data[$field])) {
                $data[$field] = $this->applyValidationRule($data[$field], $rule);
            }
        }

        return $data;
    }

    /**
     * Apply a single validation rule.
     *
     * @param mixed $value
     * @param mixed $rule
     */
    protected function applyValidationRule($value, $rule)
    {
        if (\is_callable($rule)) {
            return $rule($value);
        }

        if (\is_array($rule)) {
            $type = $rule['type'] ?? null;
            $options = $rule['options'] ?? [];

            switch ($type) {
                case 'email':
                    if (! filter_var($value, FILTER_VALIDATE_EMAIL)) {
                        throw new \InvalidArgumentException("Invalid email format: {$value}");
                    }
                    break;

                case 'phone':
                    // Basic phone validation - can be enhanced
                    $cleaned = preg_replace('/[^\d]/', '', $value);
                    if (\strlen($cleaned) < 10) {
                        throw new \InvalidArgumentException("Invalid phone number: {$value}");
                    }
                    $value = $cleaned;
                    break;

                case 'length':
                    $min = $options['min'] ?? 0;
                    $max = $options['max'] ?? PHP_INT_MAX;
                    $length = \strlen($value);
                    if ($length < $min || $length > $max) {
                        throw new \InvalidArgumentException(
                            "Value length must be between {$min} and {$max}, got {$length}"
                        );
                    }
                    break;
            }
        }

        return $value;
    }

    /**
     * Handle bulk operations if supported.
     */
    protected function bulk(string $operation, array $items, int $batchSize = 50): array
    {
        if (! $this->client->getConfig('features.bulk_operations')) {
            throw new \BadMethodCallException('Bulk operations are not enabled');
        }

        $results = [];
        $batches = array_chunk($items, $batchSize);

        foreach ($batches as $batch) {
            $batchResults = $this->processBulkBatch($operation, $batch);
            $results = array_merge($results, $batchResults);
        }

        return $results;
    }

    /**
     * Process a single bulk batch
     * Override in child classes that support bulk operations.
     */
    protected function processBulkBatch(string $operation, array $batch): array
    {
        throw new \BadMethodCallException('Bulk operations not implemented for this endpoint');
    }

    /**
     * Get endpoint-specific cache tags.
     */
    protected function getCacheTags(): array
    {
        $tags = $this->client->getConfig('cache.tags') ?? [];

        return isset($tags[$this->endpoint]) ? [$tags[$this->endpoint]] : [];
    }

    /**
     * Clear endpoint-specific cache.
     */
    protected function clearCache(): bool
    {
        return $this->client->clearCache($this->endpoint);
    }
}
