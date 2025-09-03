<?php

declare(strict_types=1);

namespace Shakewell\MindbodyLaravel\Services;

use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Shakewell\MindbodyLaravel\Exceptions\MindbodyApiException;
use Shakewell\MindbodyLaravel\Exceptions\RateLimitException;
use Shakewell\MindbodyLaravel\Services\Api\AppointmentEndpoint;
use Shakewell\MindbodyLaravel\Services\Api\ClassEndpoint;
use Shakewell\MindbodyLaravel\Services\Api\ClientEndpoint;
use Shakewell\MindbodyLaravel\Services\Api\SaleEndpoint;
use Shakewell\MindbodyLaravel\Services\Api\SiteEndpoint;
use Shakewell\MindbodyLaravel\Services\Api\StaffEndpoint;
use Shakewell\MindbodyLaravel\Services\Authentication\TokenManager;

/**
 * Main client for interacting with the Mindbody Public API.
 */
class MindbodyClient
{
    // API Endpoints
    public readonly AppointmentEndpoint $appointment;

    public readonly ClassEndpoint $class;

    public readonly ClientEndpoint $client;

    public readonly SaleEndpoint $sale;

    public readonly SiteEndpoint $site;

    public readonly StaffEndpoint $staff;

    protected array $config;

    protected TokenManager $tokenManager;

    protected ?string $userToken = null;

    /**
     * Create a new Mindbody client instance.
     */
    public function __construct(array $config)
    {
        $this->config = $config;
        $this->tokenManager = new TokenManager($config);

        // Initialize endpoints
        $this->appointment = new AppointmentEndpoint($this);
        $this->class = new ClassEndpoint($this);
        $this->client = new ClientEndpoint($this);
        $this->sale = new SaleEndpoint($this);
        $this->site = new SiteEndpoint($this);
        $this->staff = new StaffEndpoint($this);

        // Auto-authenticate if credentials provided
        $this->autoAuthenticate();
    }

    /**
     * Authenticate with staff credentials.
     */
    public function authenticate(string $username, string $password): self
    {
        $this->userToken = $this->tokenManager->getUserToken($username, $password);

        $this->logDebug('Authenticated successfully', ['username' => $username]);

        return $this;
    }

    /**
     * Get the current user token.
     */
    public function getUserToken(): ?string
    {
        return $this->userToken;
    }

    /**
     * Clear the current user token.
     */
    public function clearUserToken(): self
    {
        $this->userToken = null;

        return $this;
    }

    /**
     * Make a GET request to the API.
     */
    public function get(string $endpoint, array $params = []): array
    {
        return $this->request('GET', $endpoint, ['query' => $params]);
    }

    /**
     * Make a POST request to the API.
     */
    public function post(string $endpoint, array $data = []): array
    {
        return $this->request('POST', $endpoint, ['json' => $data]);
    }

    /**
     * Make a PUT request to the API.
     */
    public function put(string $endpoint, array $data = []): array
    {
        return $this->request('PUT', $endpoint, ['json' => $data]);
    }

    /**
     * Make a DELETE request to the API.
     */
    public function delete(string $endpoint, array $params = []): array
    {
        $options = empty($params) ? [] : ['query' => $params];

        return $this->request('DELETE', $endpoint, $options);
    }

    /**
     * Make a request to the API with caching support.
     */
    public function request(string $method, string $endpoint, array $options = []): array
    {
        // Add caching for GET requests
        if ($method === 'GET' && $this->isCacheEnabled()) {
            $cacheKey = $this->getCacheKey($endpoint, $options['query'] ?? []);
            $ttl = $this->getCacheTtl($endpoint);

            return Cache::remember($cacheKey, $ttl, function () use ($method, $endpoint, $options) {
                return $this->executeRequest($method, $endpoint, $options);
            });
        }

        return $this->executeRequest($method, $endpoint, $options);
    }

    /**
     * Clear all cached data.
     */
    public function clearCache(?string $tag = null): bool
    {
        if (! $this->isCacheEnabled()) {
            return true;
        }

        $prefix = $this->config['cache']['prefix'] ?? 'mindbody';

        if ($tag) {
            // Clear specific tag if cache store supports it
            $tagKey = $this->config['cache']['tags'][$tag] ?? null;
            if ($tagKey) {
                return Cache::tags([$tagKey])->flush();
            }
        }

        // Fallback to clearing all mindbody cache keys
        // Note: This is not efficient for large caches
        return Cache::flush(); // In production, implement more selective clearing
    }

    /**
     * Get configuration value.
     */
    public function getConfig(?string $key = null): mixed
    {
        if ($key === null) {
            return $this->config;
        }

        return data_get($this->config, $key);
    }

    /**
     * Test API connectivity.
     */
    public function testConnection(): bool
    {
        return $this->tokenManager->testConnection();
    }

    /**
     * Execute the HTTP request.
     */
    protected function executeRequest(string $method, string $endpoint, array $options = []): array
    {
        $url = $this->buildUrl($endpoint);
        $headers = $this->tokenManager->getHeaders($this->userToken);

        $this->logRequest($method, $url, $options);

        $httpClient = $this->createHttpClient($headers);

        $response = $httpClient->send($method, $url, $options);

        $this->logResponse($response);

        if (! $response->successful()) {
            $this->handleErrorResponse($response);
        }

        return $response->json() ?? [];
    }

    /**
     * Create HTTP client with configured options.
     */
    protected function createHttpClient(array $headers): PendingRequest
    {
        $client = Http::withHeaders($headers)
            ->timeout($this->config['api']['timeout'])
            ->connectTimeout($this->config['api']['connect_timeout'] ?? 10);

        // Add retry logic
        if ($this->config['api']['retry_times'] > 0) {
            $client->retry(
                $this->config['api']['retry_times'],
                $this->config['api']['retry_delay'] ?? 1000,
                static function ($exception, $request) {
                    // Only retry on server errors or network issues
                    return $exception instanceof \Exception
                           && ! ($exception instanceof MindbodyApiException && $exception->isClientError());
                }
            );
        }

        return $client;
    }

    /**
     * Handle error responses.
     */
    protected function handleErrorResponse(Response $response): void
    {
        $this->logError('API request failed', [
            'status' => $response->status(),
            'body' => $response->json(),
        ]);

        // Handle rate limiting
        if ($response->status() === 429) {
            $retryAfter = $response->header('Retry-After');
            throw RateLimitException::limitExceeded($retryAfter ? (int) $retryAfter : null);
        }

        throw MindbodyApiException::fromResponse($response);
    }

    /**
     * Build the full URL for an endpoint.
     */
    protected function buildUrl(string $endpoint): string
    {
        return rtrim($this->config['api']['base_url'], '/').'/'.ltrim($endpoint, '/');
    }

    /**
     * Check if caching is enabled.
     */
    protected function isCacheEnabled(): bool
    {
        return $this->config['cache']['enabled'] ?? false;
    }

    /**
     * Generate cache key for request.
     */
    protected function getCacheKey(string $endpoint, array $params): string
    {
        $prefix = $this->config['cache']['prefix'] ?? 'mindbody';
        $key = md5($endpoint.serialize($params));

        if ($this->userToken) {
            $key .= ':'.md5($this->userToken);
        }

        return "{$prefix}:api:{$key}";
    }

    /**
     * Get cache TTL for endpoint.
     */
    protected function getCacheTtl(string $endpoint): int
    {
        // Extract the first part of the endpoint to determine data type
        $parts = explode('/', trim($endpoint, '/'));
        $dataType = $parts[0] ?? 'default';

        $strategies = $this->config['cache']['strategies'] ?? [];

        if (isset($strategies[$dataType]['ttl'])) {
            return $strategies[$dataType]['ttl'];
        }

        return $this->config['cache']['ttl'] ?? 3600;
    }

    /**
     * Auto-authenticate if credentials are configured.
     */
    protected function autoAuthenticate(): void
    {
        $username = $this->config['api']['staff_username'] ?? null;
        $password = $this->config['api']['staff_password'] ?? null;

        if ($username && $password) {
            try {
                $this->authenticate($username, $password);
            } catch (\Exception $e) {
                $this->logError('Auto-authentication failed', [
                    'username' => $username,
                    'error' => $e->getMessage(),
                ]);
                // Don't throw here, let individual requests handle authentication errors
            }
        }
    }

    /**
     * Log API request.
     */
    protected function logRequest(string $method, string $url, array $options): void
    {
        if (! ($this->config['logging']['enabled'] ?? false)) {
            return;
        }

        $shouldLog = $this->config['logging']['log_requests'] ?? false;

        if ($shouldLog) {
            $context = [
                'method' => $method,
                'url' => $url,
            ];

            if ($this->config['logging']['log_headers'] ?? false) {
                $context['options'] = $options;
            }

            Log::channel($this->config['logging']['channel'] ?? 'stack')
                ->debug('[Mindbody API Request]', $context);
        }
    }

    /**
     * Log API response.
     */
    protected function logResponse(Response $response): void
    {
        if (! ($this->config['logging']['enabled'] ?? false)) {
            return;
        }

        $shouldLog = $this->config['logging']['log_responses'] ?? false;

        if ($shouldLog) {
            $context = [
                'status' => $response->status(),
                'headers' => $response->headers(),
            ];

            if ($response->successful()) {
                Log::channel($this->config['logging']['channel'] ?? 'stack')
                    ->debug('[Mindbody API Response]', $context);
            } else {
                $context['body'] = $response->json();
                Log::channel($this->config['logging']['channel'] ?? 'stack')
                    ->warning('[Mindbody API Response]', $context);
            }
        }

        // Log slow requests
        if ($this->config['logging']['log_performance'] ?? false) {
            $threshold = $this->config['logging']['slow_request_threshold'] ?? 2000;
            $duration = $response->transferStats?->getTransferTime() * 1000 ?? 0;

            if ($duration > $threshold) {
                Log::channel($this->config['logging']['channel'] ?? 'stack')
                    ->warning('[Mindbody Slow Request]', [
                        'duration_ms' => $duration,
                        'url' => $response->effectiveUri(),
                    ]);
            }
        }
    }

    /**
     * Log debug message.
     */
    protected function logDebug(string $message, array $context = []): void
    {
        if ($this->config['logging']['enabled'] ?? false) {
            Log::channel($this->config['logging']['channel'] ?? 'stack')
                ->debug("[Mindbody Client] {$message}", $context);
        }
    }

    /**
     * Log error message.
     */
    protected function logError(string $message, array $context = []): void
    {
        if ($this->config['logging']['enabled'] ?? false) {
            Log::channel($this->config['logging']['channel'] ?? 'stack')
                ->error("[Mindbody Client] {$message}", $context);
        }
    }
}
