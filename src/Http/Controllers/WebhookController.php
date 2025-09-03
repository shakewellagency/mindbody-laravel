<?php

declare(strict_types=1);

namespace Shakewell\MindbodyLaravel\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Log;
use Shakewell\MindbodyLaravel\Exceptions\WebhookValidationException;
use Shakewell\MindbodyLaravel\Services\Webhooks\WebhookHandler;

/**
 * Controller for handling incoming webhooks from Mindbody.
 */
class WebhookController extends Controller
{
    protected WebhookHandler $webhookHandler;

    public function __construct(WebhookHandler $webhookHandler)
    {
        $this->webhookHandler = $webhookHandler;
    }

    /**
     * Handle incoming webhook requests.
     */
    public function handle(Request $request): JsonResponse
    {
        try {
            // Handle the webhook
            $webhookEvent = $this->webhookHandler->handle($request);

            // Log successful reception
            $this->logInfo('Webhook handled successfully', [
                'event_id' => $webhookEvent->event_id,
                'event_type' => $webhookEvent->event_type,
                'db_id' => $webhookEvent->id,
            ]);

            // Return success response
            return response()->json([
                'success' => true,
                'message' => 'Webhook received and processed',
                'event_id' => $webhookEvent->event_id,
                'event_type' => $webhookEvent->event_type,
            ], 200);
        } catch (WebhookValidationException $e) {
            // Log validation errors
            $this->logError('Webhook validation failed', [
                'error' => $e->getMessage(),
                'ip' => $request->ip(),
                'user_agent' => $request->userAgent(),
            ]);

            return response()->json([
                'success' => false,
                'error' => 'Webhook validation failed',
                'message' => $e->getMessage(),
            ], 400);
        } catch (\Exception $e) {
            // Log unexpected errors
            $this->logError('Webhook processing failed', [
                'error' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
                'trace' => $e->getTraceAsString(),
            ]);

            return response()->json([
                'success' => false,
                'error' => 'Internal server error',
                'message' => 'Failed to process webhook',
            ], 500);
        }
    }

    /**
     * Health check endpoint for webhook connectivity testing.
     */
    public function health(): JsonResponse
    {
        return response()->json([
            'status' => 'ok',
            'service' => 'Mindbody Webhook Receiver',
            'timestamp' => now()->toIso8601String(),
            'version' => $this->getPackageVersion(),
        ]);
    }

    /**
     * Get webhook statistics (if enabled).
     */
    public function stats(): JsonResponse
    {
        if (! config('mindbody.webhooks.expose_stats', false)) {
            return response()->json([
                'error' => 'Statistics endpoint not enabled',
            ], 404);
        }

        try {
            $stats = $this->webhookHandler->getStats();

            return response()->json([
                'stats' => $stats,
                'timestamp' => now()->toIso8601String(),
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'error' => 'Failed to retrieve statistics',
                'message' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Test endpoint for webhook connectivity.
     */
    public function test(Request $request): JsonResponse
    {
        // Only allow test requests from localhost or configured IPs
        if (! $this->isTestRequestAllowed($request)) {
            return response()->json([
                'error' => 'Test endpoint access denied',
            ], 403);
        }

        $testData = [
            'received_at' => now()->toIso8601String(),
            'ip' => $request->ip(),
            'user_agent' => $request->userAgent(),
            'headers' => $this->sanitizeHeaders($request->headers->all()),
            'payload' => $request->all(),
            'method' => $request->method(),
            'url' => $request->fullUrl(),
        ];

        $this->logInfo('Test webhook request received', $testData);

        return response()->json([
            'success' => true,
            'message' => 'Test webhook received successfully',
            'data' => $testData,
        ]);
    }

    /**
     * Check if test requests are allowed from this IP.
     */
    protected function isTestRequestAllowed(Request $request): bool
    {
        $allowedIps = config('mindbody.webhooks.test_allowed_ips', ['127.0.0.1', '::1']);
        $requestIp = $request->ip();

        return \in_array($requestIp, $allowedIps, true)
               || $this->isLocalhost($requestIp)
               || app()->environment('testing');
    }

    /**
     * Check if IP is localhost.
     */
    protected function isLocalhost(string $ip): bool
    {
        return \in_array($ip, ['127.0.0.1', '::1', 'localhost'], true);
    }

    /**
     * Sanitize headers for logging (remove sensitive data).
     */
    protected function sanitizeHeaders(array $headers): array
    {
        $sensitiveHeaders = [
            'authorization',
            'x-api-key',
            'x-mindbody-signature',
            'x-signature',
        ];

        $sanitized = [];
        foreach ($headers as $key => $value) {
            if (\in_array(strtolower($key), $sensitiveHeaders, true)) {
                $sanitized[$key] = '[REDACTED]';
            } else {
                $sanitized[$key] = $value;
            }
        }

        return $sanitized;
    }

    /**
     * Get package version.
     */
    protected function getPackageVersion(): string
    {
        try {
            $composer = json_decode(file_get_contents(__DIR__.'/../../../composer.json'), true);

            return $composer['version'] ?? 'dev';
        } catch (\Exception $e) {
            return 'unknown';
        }
    }

    /**
     * Log info message.
     */
    protected function logInfo(string $message, array $context = []): void
    {
        if (config('mindbody.logging.enabled', true)) {
            Log::channel(config('mindbody.logging.channel', 'stack'))
                ->info("[Mindbody Webhook Controller] {$message}", $context);
        }
    }

    /**
     * Log error message.
     */
    protected function logError(string $message, array $context = []): void
    {
        if (config('mindbody.logging.enabled', true)) {
            Log::channel(config('mindbody.logging.channel', 'stack'))
                ->error("[Mindbody Webhook Controller] {$message}", $context);
        }
    }
}
