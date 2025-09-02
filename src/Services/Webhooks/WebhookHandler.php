<?php

declare(strict_types=1);
namespace Shakewell\MindbodyLaravel\Services\Webhooks;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Queue;
use Shakewell\MindbodyLaravel\Events\WebhookReceived;
use Shakewell\MindbodyLaravel\Exceptions\WebhookValidationException;
use Shakewell\MindbodyLaravel\Models\WebhookEvent;

/**
 * Handles incoming webhook requests from Mindbody.
 */
class WebhookHandler
{
    protected array $config;

    public function __construct(array $config)
    {
        $this->config = $config;
    }

    /**
     * Handle incoming webhook request.
     */
    public function handle(Request $request): WebhookEvent
    {
        // Extract webhook data
        $payload = $this->extractPayload($request);
        $signature = $this->extractSignature($request);
        $headers = $this->extractHeaders($request);

        // Validate webhook signature if enabled
        if ($this->shouldVerifySignature()) {
            $this->validateSignature($request, $signature);
        }

        // Validate webhook payload
        $this->validatePayload($payload);

        // Check if event type is supported
        $this->validateEventType($payload['EventType'] ?? '');

        // Store webhook event
        $webhookEvent = $this->storeEvent($payload, $signature, $headers);

        // Dispatch event for processing
        $this->dispatchEvent($webhookEvent);

        // Log successful webhook reception
        $this->logWebhookReceived($webhookEvent);

        return $webhookEvent;
    }

    /**
     * Process a webhook event (for manual processing or queue workers).
     */
    public function processEvent(WebhookEvent $webhookEvent): bool
    {
        try {
            if ($webhookEvent->processed) {
                $this->logInfo('Event already processed', ['event_id' => $webhookEvent->id]);

                return true;
            }

            // Validate that we still support this event type
            if (! $this->isEventTypeSupported($webhookEvent->event_type)) {
                throw new WebhookValidationException("Unsupported event type: {$webhookEvent->event_type}");
            }

            // Process the event based on type
            $result = $this->processEventByType($webhookEvent);

            if ($result) {
                $webhookEvent->markAsProcessed();
                $this->logInfo('Event processed successfully', [
                    'event_id' => $webhookEvent->id,
                    'event_type' => $webhookEvent->event_type,
                ]);

                return true;
            }

            throw new \RuntimeException('Event processing returned false');
        } catch (\Exception $e) {
            $this->handleProcessingError($webhookEvent, $e);

            return false;
        }
    }

    /**
     * Get processing statistics.
     */
    public function getStats(): array
    {
        return WebhookEvent::getProcessingStats();
    }

    /**
     * Process pending webhooks (for manual processing).
     */
    public function processPendingEvents(int $maxRetries = 3, int $limit = 100): array
    {
        $events = WebhookEvent::retryable($maxRetries)
            ->limit($limit)
            ->get();

        $results = [
            'processed' => 0,
            'failed' => 0,
            'skipped' => 0,
        ];

        foreach ($events as $event) {
            if ($this->processEvent($event)) {
                $results['processed']++;
            } else {
                $results['failed']++;
            }
        }

        return $results;
    }

    /**
     * Extract payload from request.
     */
    protected function extractPayload(Request $request): array
    {
        $payload = $request->all();

        if (empty($payload)) {
            throw new WebhookValidationException('Empty webhook payload');
        }

        return $payload;
    }

    /**
     * Extract signature from request headers.
     */
    protected function extractSignature(Request $request): ?string
    {
        // Try different possible header names for signature
        $possibleHeaders = [
            'X-Mindbody-Signature',
            'X-MB-Signature',
            'X-Signature',
            'Signature',
        ];

        foreach ($possibleHeaders as $header) {
            $signature = $request->header($header);
            if ($signature) {
                return $signature;
            }
        }

        return null;
    }

    /**
     * Extract relevant headers from request.
     */
    protected function extractHeaders(Request $request): array
    {
        $relevantHeaders = [
            'content-type',
            'user-agent',
            'x-mindbody-signature',
            'x-mb-signature',
            'x-signature',
            'x-forwarded-for',
        ];

        $headers = [];
        foreach ($relevantHeaders as $header) {
            $value = $request->header($header);
            if ($value) {
                $headers[$header] = $value;
            }
        }

        return $headers;
    }

    /**
     * Check if signature verification should be performed.
     */
    protected function shouldVerifySignature(): bool
    {
        return $this->config['webhooks']['verify_signature'] ?? true;
    }

    /**
     * Validate webhook signature.
     */
    protected function validateSignature(Request $request, ?string $signature): void
    {
        if (! $signature) {
            throw WebhookValidationException::missingSignature();
        }

        $signatureKey = $this->config['webhooks']['signature_key'] ?? null;
        if (! $signatureKey) {
            throw WebhookValidationException::missingSignatureKey();
        }

        $payload = $request->getContent();
        $expectedSignature = $this->calculateExpectedSignature($payload, $signatureKey);

        if (! hash_equals($expectedSignature, $signature)) {
            throw WebhookValidationException::invalidSignature();
        }
    }

    /**
     * Calculate expected signature.
     */
    protected function calculateExpectedSignature(string $payload, string $key): string
    {
        $hash = hash_hmac('sha256', $payload, $key);

        return 'sha256='.$hash;
    }

    /**
     * Validate webhook payload structure.
     */
    protected function validatePayload(array $payload): void
    {
        if (! isset($payload['EventType'])) {
            throw WebhookValidationException::invalidPayload('Missing EventType');
        }

        if (! isset($payload['EventData'])) {
            throw WebhookValidationException::invalidPayload('Missing EventData');
        }
    }

    /**
     * Validate that event type is supported.
     */
    protected function validateEventType(string $eventType): void
    {
        if (! $this->isEventTypeSupported($eventType)) {
            throw WebhookValidationException::unsupportedEvent($eventType);
        }
    }

    /**
     * Check if event type is supported.
     */
    protected function isEventTypeSupported(string $eventType): bool
    {
        $supportedEvents = $this->config['webhooks']['events'] ?? [];

        return \in_array($eventType, $supportedEvents, true);
    }

    /**
     * Store webhook event in database.
     */
    protected function storeEvent(array $payload, ?string $signature, array $headers): WebhookEvent
    {
        return WebhookEvent::createFromWebhook($payload, $signature, $headers);
    }

    /**
     * Dispatch event for processing.
     */
    protected function dispatchEvent(WebhookEvent $webhookEvent): void
    {
        $event = new WebhookReceived($webhookEvent);

        if ($this->shouldQueueWebhooks()) {
            $queue = $this->config['webhooks']['webhook_queue'] ?? 'default';
            $connection = $this->config['webhooks']['webhook_connection'] ?? null;

            Queue::connection($connection)->pushOn($queue, $event);
        } else {
            event($event);
        }
    }

    /**
     * Check if webhooks should be queued.
     */
    protected function shouldQueueWebhooks(): bool
    {
        return $this->config['webhooks']['queue_webhooks'] ?? true;
    }

    /**
     * Process event based on its type.
     */
    protected function processEventByType(WebhookEvent $webhookEvent): bool
    {
        $eventType = $webhookEvent->event_type;
        $eventData = $webhookEvent->getEventDataArray();

        // Create a more specific event based on the type
        $specificEvent = $this->createSpecificEvent($eventType, $eventData, $webhookEvent);

        if ($specificEvent) {
            event($specificEvent);

            return true;
        }

        // Default processing - just log the event
        $this->logInfo('Processing webhook event', [
            'event_type' => $eventType,
            'event_id' => $webhookEvent->event_id,
            'site_id' => $webhookEvent->site_id,
        ]);

        return true;
    }

    /**
     * Create specific event based on webhook type.
     */
    protected function createSpecificEvent(string $eventType, array $eventData, WebhookEvent $webhookEvent)
    {
        // This can be extended to create specific event classes for different webhook types
        // For now, we'll use the generic WebhookReceived event
    }

    /**
     * Handle processing error.
     */
    protected function handleProcessingError(WebhookEvent $webhookEvent, \Exception $error): void
    {
        $errorMessage = $error->getMessage();
        $maxRetries = $this->config['webhooks']['retry']['max_attempts'] ?? 3;

        $webhookEvent->markAsFailed($errorMessage);

        $this->logError('Failed to process webhook event', [
            'event_id' => $webhookEvent->id,
            'event_type' => $webhookEvent->event_type,
            'error' => $errorMessage,
            'retry_count' => $webhookEvent->retry_count,
            'max_retries' => $maxRetries,
        ]);

        // Schedule retry if not exceeded max attempts
        if ($webhookEvent->canRetry($maxRetries)) {
            $this->scheduleRetry($webhookEvent);
        }
    }

    /**
     * Schedule webhook event for retry.
     */
    protected function scheduleRetry(WebhookEvent $webhookEvent): void
    {
        $delay = $this->calculateRetryDelay($webhookEvent->retry_count);

        $event = new WebhookReceived($webhookEvent);

        if ($this->shouldQueueWebhooks()) {
            $queue = $this->config['webhooks']['webhook_queue'] ?? 'default';
            $connection = $this->config['webhooks']['webhook_connection'] ?? null;

            Queue::connection($connection)
                ->later($delay, $event)
                ->onQueue($queue);
        }
    }

    /**
     * Calculate retry delay based on attempt count.
     */
    protected function calculateRetryDelay(int $retryCount): int
    {
        $baseDelay = $this->config['webhooks']['retry']['delay'] ?? 5;
        $useExponentialBackoff = $this->config['webhooks']['retry']['exponential_backoff'] ?? true;

        if ($useExponentialBackoff) {
            return $baseDelay * (2 ** $retryCount);
        }

        return $baseDelay;
    }

    /**
     * Log webhook received.
     */
    protected function logWebhookReceived(WebhookEvent $webhookEvent): void
    {
        if (! $this->isLoggingEnabled()) {
            return;
        }

        $this->logInfo('Webhook received and stored', [
            'event_id' => $webhookEvent->event_id,
            'event_type' => $webhookEvent->event_type,
            'site_id' => $webhookEvent->site_id,
            'db_id' => $webhookEvent->id,
        ]);
    }

    /**
     * Check if logging is enabled.
     */
    protected function isLoggingEnabled(): bool
    {
        return $this->config['logging']['enabled'] ?? true;
    }

    /**
     * Log info message.
     */
    protected function logInfo(string $message, array $context = []): void
    {
        if ($this->isLoggingEnabled()) {
            Log::channel($this->config['logging']['channel'] ?? 'stack')
                ->info("[Mindbody Webhook] {$message}", $context);
        }
    }

    /**
     * Log error message.
     */
    protected function logError(string $message, array $context = []): void
    {
        if ($this->isLoggingEnabled()) {
            Log::channel($this->config['logging']['channel'] ?? 'stack')
                ->error("[Mindbody Webhook] {$message}", $context);
        }
    }
}
