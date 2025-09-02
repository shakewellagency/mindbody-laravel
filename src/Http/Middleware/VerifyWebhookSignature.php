<?php

declare(strict_types=1);
namespace Shakewell\MindbodyLaravel\Http\Middleware;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Shakewell\MindbodyLaravel\Exceptions\WebhookValidationException;
use Symfony\Component\HttpFoundation\Response as SymfonyResponse;

/**
 * Middleware to verify webhook signatures from Mindbody.
 */
class VerifyWebhookSignature
{
    /**
     * Handle an incoming request.
     */
    public function handle(Request $request, \Closure $next): SymfonyResponse
    {
        if (! $this->shouldVerifySignature()) {
            return $next($request);
        }

        try {
            $this->validateSignature($request);

            return $next($request);
        } catch (WebhookValidationException $e) {
            $this->logSecurityError($request, $e);

            return response()->json(['error' => 'Invalid webhook signature'], 401);
        }
    }

    /**
     * Check if signature verification is enabled.
     */
    protected function shouldVerifySignature(): bool
    {
        return config('mindbody.webhooks.verify_signature', true);
    }

    /**
     * Validate the webhook signature.
     */
    protected function validateSignature(Request $request): void
    {
        $signature = $this->extractSignature($request);

        if (! $signature) {
            throw WebhookValidationException::missingSignature();
        }

        $signatureKey = $this->getSignatureKey();

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
     * Extract signature from request headers.
     */
    protected function extractSignature(Request $request): ?string
    {
        // Try different possible header names
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
     * Get the signature key from configuration.
     */
    protected function getSignatureKey(): ?string
    {
        return config('mindbody.webhooks.signature_key');
    }

    /**
     * Calculate the expected signature.
     */
    protected function calculateExpectedSignature(string $payload, string $key): string
    {
        $hash = hash_hmac('sha256', $payload, $key);

        return 'sha256='.$hash;
    }

    /**
     * Log security error.
     */
    protected function logSecurityError(Request $request, WebhookValidationException $exception): void
    {
        Log::channel(config('mindbody.logging.channel', 'stack'))
            ->warning('[Mindbody Security] Invalid webhook signature', [
                'ip' => $request->ip(),
                'user_agent' => $request->userAgent(),
                'url' => $request->fullUrl(),
                'error' => $exception->getMessage(),
                'headers' => $request->headers->all(),
            ]);
    }
}
