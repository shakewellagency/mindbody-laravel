<?php

declare(strict_types=1);

namespace Shakewell\MindbodyLaravel\Services\Authentication;

use Carbon\Carbon;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Shakewell\MindbodyLaravel\Exceptions\AuthenticationException;
use Shakewell\MindbodyLaravel\Exceptions\MindbodyApiException;

/**
 * Manages API tokens for Mindbody authentication
 */
class TokenManager
{
    protected array $config;
    
    protected string $apiKey;
    
    protected ?string $siteId;
    
    protected string $baseUrl;
    
    protected string $cachePrefix;

    /**
     * Create a new token manager instance
     */
    public function __construct(array $config)
    {
        $this->config = $config;
        $this->apiKey = $config['api']['api_key'] ?? '';
        $this->siteId = $config['api']['site_id'] ?? null;
        $this->baseUrl = $config['api']['base_url'];
        $this->cachePrefix = $config['cache']['prefix'] ?? 'mindbody';

        if (empty($this->apiKey)) {
            throw AuthenticationException::missingApiKey();
        }
    }

    /**
     * Get or refresh user token for authenticated endpoints
     */
    public function getUserToken(string $username, string $password): string
    {
        $cacheKey = $this->getUserTokenCacheKey($username);
        
        // Try to get cached token first
        $cachedToken = Cache::get($cacheKey);
        if ($cachedToken && $this->isTokenValid($cachedToken)) {
            return $cachedToken['access_token'];
        }

        // Issue new token
        $tokenData = $this->issueUserToken($username, $password);
        
        // Cache the token (expires 5 minutes before actual expiry for safety)
        $ttl = max(($tokenData['expires_in'] ?? 3600) - 300, 300);
        Cache::put($cacheKey, $tokenData, $ttl);

        return $tokenData['access_token'];
    }

    /**
     * Issue a new user token
     */
    protected function issueUserToken(string $username, string $password): array
    {
        $this->logDebug('Issuing new user token', ['username' => $username]);

        $response = Http::withHeaders($this->getBaseHeaders())
            ->timeout($this->config['api']['timeout'])
            ->post("{$this->baseUrl}/usertoken/issue", [
                'Username' => $username,
                'Password' => $password,
            ]);

        if (! $response->successful()) {
            $this->logError('Failed to issue user token', [
                'username' => $username,
                'status' => $response->status(),
                'response' => $response->json(),
            ]);

            if ($response->status() === 401) {
                throw AuthenticationException::invalidCredentials($username);
            }

            throw MindbodyApiException::fromResponse($response);
        }

        $data = $response->json();
        $accessToken = $data['AccessToken'] ?? null;
        
        if (empty($accessToken)) {
            throw new AuthenticationException('No access token received from API', 500);
        }

        $tokenData = [
            'access_token' => $accessToken,
            'token_type' => $data['TokenType'] ?? 'Bearer',
            'expires_in' => $data['ExpiresIn'] ?? 3600,
            'issued_at' => Carbon::now()->timestamp,
        ];

        $this->logDebug('User token issued successfully', ['username' => $username]);

        return $tokenData;
    }

    /**
     * Revoke a user token
     */
    public function revokeUserToken(string $token): bool
    {
        $this->logDebug('Revoking user token');

        $response = Http::withHeaders([
            ...$this->getBaseHeaders(),
            'Authorization' => $token,
        ])->delete("{$this->baseUrl}/usertoken/revoke");

        $success = $response->successful();

        if ($success) {
            $this->logDebug('User token revoked successfully');
            // Remove from cache
            $this->clearUserTokenCache($token);
        } else {
            $this->logError('Failed to revoke user token', [
                'status' => $response->status(),
                'response' => $response->json(),
            ]);
        }

        return $success;
    }

    /**
     * Get cached user token if valid
     */
    public function getCachedUserToken(string $username): ?string
    {
        $cacheKey = $this->getUserTokenCacheKey($username);
        $tokenData = Cache::get($cacheKey);

        if ($tokenData && $this->isTokenValid($tokenData)) {
            return $tokenData['access_token'];
        }

        return null;
    }

    /**
     * Clear cached user token
     */
    public function clearUserTokenCache(string $username): void
    {
        $cacheKey = $this->getUserTokenCacheKey($username);
        Cache::forget($cacheKey);
    }

    /**
     * Get headers for API requests
     */
    public function getHeaders(?string $userToken = null): array
    {
        $headers = $this->getBaseHeaders();

        if ($userToken) {
            $headers['Authorization'] = $userToken;
        }

        return $headers;
    }

    /**
     * Get base headers for all requests
     */
    protected function getBaseHeaders(): array
    {
        $headers = [
            'Api-Key' => $this->apiKey,
            'Content-Type' => 'application/json',
            'Accept' => 'application/json',
        ];

        if ($this->siteId) {
            $headers['SiteId'] = $this->siteId;
        }

        return $headers;
    }

    /**
     * Check if token is still valid
     */
    protected function isTokenValid(array $tokenData): bool
    {
        if (! isset($tokenData['issued_at'], $tokenData['expires_in'])) {
            return false;
        }

        $issuedAt = $tokenData['issued_at'];
        $expiresIn = $tokenData['expires_in'];
        $expiresAt = $issuedAt + $expiresIn;

        // Consider token invalid if it expires within 5 minutes
        $gracePeriod = 300;
        
        return Carbon::now()->timestamp < ($expiresAt - $gracePeriod);
    }

    /**
     * Get cache key for user token
     */
    protected function getUserTokenCacheKey(string $username): string
    {
        return "{$this->cachePrefix}:user_token:" . md5($username);
    }

    /**
     * Clear cached token for a specific token value
     */
    protected function clearUserTokenCache(string $token): void
    {
        // Since we don't store reverse mapping, we'd need to clear all user tokens
        // or implement a more sophisticated cache invalidation strategy
        // For now, we'll rely on natural expiration
    }

    /**
     * Validate API key and site ID
     */
    public function validateConfiguration(): void
    {
        if (empty($this->apiKey)) {
            throw AuthenticationException::missingApiKey();
        }

        if (empty($this->siteId)) {
            throw AuthenticationException::missingSiteId();
        }
    }

    /**
     * Test API connectivity
     */
    public function testConnection(): bool
    {
        try {
            $response = Http::withHeaders($this->getBaseHeaders())
                ->timeout(10)
                ->get("{$this->baseUrl}/site/sites");

            return $response->successful();
        } catch (\Exception $e) {
            $this->logError('Connection test failed', ['error' => $e->getMessage()]);
            return false;
        }
    }

    /**
     * Log debug message
     */
    protected function logDebug(string $message, array $context = []): void
    {
        if ($this->config['logging']['enabled'] ?? false) {
            Log::channel($this->config['logging']['channel'] ?? 'stack')
                ->debug("[Mindbody Token Manager] {$message}", $context);
        }
    }

    /**
     * Log error message
     */
    protected function logError(string $message, array $context = []): void
    {
        if ($this->config['logging']['enabled'] ?? false) {
            Log::channel($this->config['logging']['channel'] ?? 'stack')
                ->error("[Mindbody Token Manager] {$message}", $context);
        }
    }
}