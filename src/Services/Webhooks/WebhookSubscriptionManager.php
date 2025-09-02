<?php

declare(strict_types=1);

namespace Shakewell\MindbodyLaravel\Services\Webhooks;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Shakewell\MindbodyLaravel\Exceptions\MindbodyApiException;

/**
 * Manages webhook subscriptions with the Mindbody Webhooks API
 */
class WebhookSubscriptionManager
{
    protected array $config;
    
    protected string $apiKey;
    
    protected string $baseUrl;
    
    protected ?string $webhookUrl;

    public function __construct(array $config)
    {
        $this->config = $config;
        $this->apiKey = $config['webhooks']['api_key'] ?? '';
        $this->baseUrl = $config['webhooks']['base_url'];
        $this->webhookUrl = $config['webhooks']['webhook_url'] ?? null;

        if (empty($this->apiKey)) {
            throw new \InvalidArgumentException('Webhook API key is required');
        }
    }

    /**
     * Create a webhook subscription
     */
    public function subscribe(string $eventType, ?string $webhookUrl = null): array
    {
        $url = $webhookUrl ?? $this->webhookUrl;
        
        if (!$url) {
            throw new \InvalidArgumentException('Webhook URL is required');
        }

        $this->logInfo('Creating webhook subscription', [
            'event_type' => $eventType,
            'webhook_url' => $url,
        ]);

        $response = Http::withHeaders($this->getHeaders())
            ->timeout($this->config['api']['timeout'] ?? 30)
            ->post("{$this->baseUrl}/subscriptions", [
                'EventType' => $eventType,
                'WebhookUrl' => $url,
                'IsActive' => true,
            ]);

        if (!$response->successful()) {
            $this->logError('Failed to create webhook subscription', [
                'event_type' => $eventType,
                'status' => $response->status(),
                'response' => $response->json(),
            ]);

            throw MindbodyApiException::fromResponse($response);
        }

        $result = $response->json();
        
        $this->logInfo('Webhook subscription created successfully', [
            'event_type' => $eventType,
            'subscription_id' => $result['SubscriptionId'] ?? 'unknown',
        ]);

        // Clear subscription cache
        $this->clearSubscriptionCache();

        return $result;
    }

    /**
     * List all webhook subscriptions
     */
    public function list(bool $useCache = true): array
    {
        if ($useCache) {
            return Cache::remember(
                $this->getSubscriptionCacheKey(),
                300, // 5 minutes
                fn() => $this->fetchSubscriptions()
            );
        }

        return $this->fetchSubscriptions();
    }

    /**
     * Fetch subscriptions from API
     */
    protected function fetchSubscriptions(): array
    {
        $this->logInfo('Fetching webhook subscriptions');

        $response = Http::withHeaders($this->getHeaders())
            ->timeout($this->config['api']['timeout'] ?? 30)
            ->get("{$this->baseUrl}/subscriptions");

        if (!$response->successful()) {
            $this->logError('Failed to list webhook subscriptions', [
                'status' => $response->status(),
                'response' => $response->json(),
            ]);

            throw MindbodyApiException::fromResponse($response);
        }

        return $response->json('Subscriptions', []);
    }

    /**
     * Get a specific subscription by ID
     */
    public function get(string $subscriptionId): array
    {
        $this->logInfo('Fetching webhook subscription', ['subscription_id' => $subscriptionId]);

        $response = Http::withHeaders($this->getHeaders())
            ->timeout($this->config['api']['timeout'] ?? 30)
            ->get("{$this->baseUrl}/subscriptions/{$subscriptionId}");

        if (!$response->successful()) {
            $this->logError('Failed to get webhook subscription', [
                'subscription_id' => $subscriptionId,
                'status' => $response->status(),
                'response' => $response->json(),
            ]);

            throw MindbodyApiException::fromResponse($response);
        }

        return $response->json();
    }

    /**
     * Update a webhook subscription
     */
    public function update(string $subscriptionId, array $data): array
    {
        $this->logInfo('Updating webhook subscription', [
            'subscription_id' => $subscriptionId,
            'data' => $data,
        ]);

        $response = Http::withHeaders($this->getHeaders())
            ->timeout($this->config['api']['timeout'] ?? 30)
            ->put("{$this->baseUrl}/subscriptions/{$subscriptionId}", $data);

        if (!$response->successful()) {
            $this->logError('Failed to update webhook subscription', [
                'subscription_id' => $subscriptionId,
                'status' => $response->status(),
                'response' => $response->json(),
            ]);

            throw MindbodyApiException::fromResponse($response);
        }

        $result = $response->json();

        // Clear subscription cache
        $this->clearSubscriptionCache();

        return $result;
    }

    /**
     * Delete a webhook subscription
     */
    public function delete(string $subscriptionId): bool
    {
        $this->logInfo('Deleting webhook subscription', ['subscription_id' => $subscriptionId]);

        $response = Http::withHeaders($this->getHeaders())
            ->timeout($this->config['api']['timeout'] ?? 30)
            ->delete("{$this->baseUrl}/subscriptions/{$subscriptionId}");

        $success = $response->successful();

        if ($success) {
            $this->logInfo('Webhook subscription deleted successfully', [
                'subscription_id' => $subscriptionId,
            ]);
            
            // Clear subscription cache
            $this->clearSubscriptionCache();
        } else {
            $this->logError('Failed to delete webhook subscription', [
                'subscription_id' => $subscriptionId,
                'status' => $response->status(),
                'response' => $response->json(),
            ]);
        }

        return $success;
    }

    /**
     * Subscribe to all configured events
     */
    public function subscribeToAll(?string $webhookUrl = null): array
    {
        $events = $this->config['webhooks']['events'] ?? [];
        $results = [];

        $this->logInfo('Subscribing to all configured events', [
            'event_count' => count($events),
            'webhook_url' => $webhookUrl ?? $this->webhookUrl,
        ]);

        foreach ($events as $eventType) {
            try {
                $results[$eventType] = $this->subscribe($eventType, $webhookUrl);
                $results[$eventType]['success'] = true;
            } catch (\Exception $e) {
                $results[$eventType] = [
                    'success' => false,
                    'error' => $e->getMessage(),
                ];

                $this->logError('Failed to subscribe to event', [
                    'event_type' => $eventType,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        $successCount = count(array_filter($results, fn($r) => $r['success'] ?? false));
        $failureCount = count($results) - $successCount;

        $this->logInfo('Bulk subscription completed', [
            'total' => count($results),
            'success' => $successCount,
            'failed' => $failureCount,
        ]);

        return $results;
    }

    /**
     * Unsubscribe from all subscriptions
     */
    public function unsubscribeFromAll(): array
    {
        $subscriptions = $this->list(false);
        $results = [];

        $this->logInfo('Unsubscribing from all subscriptions', [
            'subscription_count' => count($subscriptions),
        ]);

        foreach ($subscriptions as $subscription) {
            $subscriptionId = $subscription['SubscriptionId'] ?? null;
            
            if (!$subscriptionId) {
                continue;
            }

            try {
                $success = $this->delete($subscriptionId);
                $results[$subscriptionId] = [
                    'success' => $success,
                    'event_type' => $subscription['EventType'] ?? 'unknown',
                ];
            } catch (\Exception $e) {
                $results[$subscriptionId] = [
                    'success' => false,
                    'error' => $e->getMessage(),
                    'event_type' => $subscription['EventType'] ?? 'unknown',
                ];
            }
        }

        return $results;
    }

    /**
     * Get subscription status for all configured events
     */
    public function getSubscriptionStatus(): array
    {
        $configuredEvents = $this->config['webhooks']['events'] ?? [];
        $subscriptions = $this->list();
        $webhookUrl = $this->webhookUrl;

        $status = [];

        foreach ($configuredEvents as $eventType) {
            $subscription = $this->findSubscriptionForEvent($subscriptions, $eventType, $webhookUrl);
            
            $status[$eventType] = [
                'configured' => true,
                'subscribed' => !is_null($subscription),
                'subscription_id' => $subscription['SubscriptionId'] ?? null,
                'is_active' => $subscription['IsActive'] ?? false,
                'webhook_url' => $subscription['WebhookUrl'] ?? null,
            ];
        }

        // Check for extra subscriptions not in configuration
        foreach ($subscriptions as $subscription) {
            $eventType = $subscription['EventType'] ?? '';
            if (!in_array($eventType, $configuredEvents, true)) {
                $status[$eventType] = [
                    'configured' => false,
                    'subscribed' => true,
                    'subscription_id' => $subscription['SubscriptionId'] ?? null,
                    'is_active' => $subscription['IsActive'] ?? false,
                    'webhook_url' => $subscription['WebhookUrl'] ?? null,
                ];
            }
        }

        return $status;
    }

    /**
     * Find subscription for a specific event type and webhook URL
     */
    protected function findSubscriptionForEvent(array $subscriptions, string $eventType, ?string $webhookUrl): ?array
    {
        foreach ($subscriptions as $subscription) {
            if (($subscription['EventType'] ?? '') === $eventType) {
                if (!$webhookUrl || ($subscription['WebhookUrl'] ?? '') === $webhookUrl) {
                    return $subscription;
                }
            }
        }

        return null;
    }

    /**
     * Sync subscriptions (subscribe to missing, unsubscribe from extra)
     */
    public function sync(?string $webhookUrl = null): array
    {
        $webhookUrl = $webhookUrl ?? $this->webhookUrl;
        $configuredEvents = $this->config['webhooks']['events'] ?? [];
        $currentSubscriptions = $this->list(false);

        $results = [
            'subscribed' => [],
            'unsubscribed' => [],
            'errors' => [],
        ];

        // Subscribe to missing events
        foreach ($configuredEvents as $eventType) {
            $subscription = $this->findSubscriptionForEvent($currentSubscriptions, $eventType, $webhookUrl);
            
            if (!$subscription) {
                try {
                    $result = $this->subscribe($eventType, $webhookUrl);
                    $results['subscribed'][] = [
                        'event_type' => $eventType,
                        'subscription_id' => $result['SubscriptionId'] ?? null,
                    ];
                } catch (\Exception $e) {
                    $results['errors'][] = [
                        'event_type' => $eventType,
                        'action' => 'subscribe',
                        'error' => $e->getMessage(),
                    ];
                }
            }
        }

        // Optionally unsubscribe from events not in configuration
        if ($this->config['webhooks']['auto_cleanup'] ?? false) {
            foreach ($currentSubscriptions as $subscription) {
                $eventType = $subscription['EventType'] ?? '';
                $subscriptionWebhookUrl = $subscription['WebhookUrl'] ?? '';
                
                if (!in_array($eventType, $configuredEvents, true) && 
                    $subscriptionWebhookUrl === $webhookUrl) {
                    try {
                        $subscriptionId = $subscription['SubscriptionId'];
                        $this->delete($subscriptionId);
                        $results['unsubscribed'][] = [
                            'event_type' => $eventType,
                            'subscription_id' => $subscriptionId,
                        ];
                    } catch (\Exception $e) {
                        $results['errors'][] = [
                            'event_type' => $eventType,
                            'subscription_id' => $subscription['SubscriptionId'] ?? null,
                            'action' => 'unsubscribe',
                            'error' => $e->getMessage(),
                        ];
                    }
                }
            }
        }

        $this->logInfo('Subscription sync completed', [
            'subscribed' => count($results['subscribed']),
            'unsubscribed' => count($results['unsubscribed']),
            'errors' => count($results['errors']),
        ]);

        return $results;
    }

    /**
     * Test webhook endpoint connectivity
     */
    public function testWebhookEndpoint(?string $webhookUrl = null): array
    {
        $url = $webhookUrl ?? $this->webhookUrl;
        
        if (!$url) {
            return [
                'success' => false,
                'error' => 'No webhook URL configured',
            ];
        }

        try {
            // Send a test POST request to the webhook URL
            $testPayload = [
                'EventType' => 'test',
                'EventData' => ['test' => true],
                'EventTimestamp' => now()->toIso8601String(),
                'SiteId' => $this->config['api']['site_id'] ?? null,
            ];

            $response = Http::timeout(10)
                ->post($url, $testPayload);

            return [
                'success' => $response->successful(),
                'status_code' => $response->status(),
                'response_time' => $response->transferStats?->getTransferTime() ?? null,
                'url' => $url,
            ];

        } catch (\Exception $e) {
            return [
                'success' => false,
                'error' => $e->getMessage(),
                'url' => $url,
            ];
        }
    }

    /**
     * Get request headers for API calls
     */
    protected function getHeaders(): array
    {
        return [
            'Api-Key' => $this->apiKey,
            'Content-Type' => 'application/json',
            'Accept' => 'application/json',
        ];
    }

    /**
     * Get subscription cache key
     */
    protected function getSubscriptionCacheKey(): string
    {
        return 'mindbody:webhook_subscriptions';
    }

    /**
     * Clear subscription cache
     */
    protected function clearSubscriptionCache(): void
    {
        Cache::forget($this->getSubscriptionCacheKey());
    }

    /**
     * Check if logging is enabled
     */
    protected function isLoggingEnabled(): bool
    {
        return $this->config['logging']['enabled'] ?? true;
    }

    /**
     * Log info message
     */
    protected function logInfo(string $message, array $context = []): void
    {
        if ($this->isLoggingEnabled()) {
            Log::channel($this->config['logging']['channel'] ?? 'stack')
                ->info("[Mindbody Webhook Manager] {$message}", $context);
        }
    }

    /**
     * Log error message
     */
    protected function logError(string $message, array $context = []): void
    {
        if ($this->isLoggingEnabled()) {
            Log::channel($this->config['logging']['channel'] ?? 'stack')
                ->error("[Mindbody Webhook Manager] {$message}", $context);
        }
    }
}