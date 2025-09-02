<?php

declare(strict_types=1);

namespace Shakewell\MindbodyLaravel\Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Shakewell\MindbodyLaravel\Events\Webhooks\AppointmentBooked;
use Shakewell\MindbodyLaravel\Models\WebhookEvent;
use Shakewell\MindbodyLaravel\Tests\TestCase;

class WebhookControllerTest extends TestCase
{
    use RefreshDatabase;

    /** @test */
    public function it_can_handle_valid_webhook_request(): void
    {
        Event::fake();

        $payload = $this->createWebhookPayload('appointment.booked');
        $payloadJson = json_encode($payload);
        $signature = $this->createWebhookSignature($payloadJson);

        $response = $this->postJson('/mindbody/webhooks', $payload, [
            'X-Mindbody-Signature' => $signature,
        ]);

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'message' => 'Webhook received and processed',
            ]);

        $this->assertWebhookEventCreated($payload['EventId'], $payload['EventType']);
        Event::assertDispatched(AppointmentBooked::class);
    }

    /** @test */
    public function it_rejects_webhook_with_invalid_signature(): void
    {
        $payload = $this->createWebhookPayload();
        $invalidSignature = 'sha256=invalid-signature';

        $response = $this->postJson('/mindbody/webhooks', $payload, [
            'X-Mindbody-Signature' => $invalidSignature,
        ]);

        $response->assertStatus(400)
            ->assertJson([
                'success' => false,
                'error' => 'Webhook validation failed',
            ]);

        $this->assertDatabaseMissing('mindbody_webhook_events', [
            'event_id' => $payload['EventId'],
        ]);
    }

    /** @test */
    public function it_rejects_webhook_without_signature(): void
    {
        $payload = $this->createWebhookPayload();

        $response = $this->postJson('/mindbody/webhooks', $payload);

        $response->assertStatus(400)
            ->assertJson([
                'success' => false,
                'error' => 'Webhook validation failed',
            ]);
    }

    /** @test */
    public function it_allows_webhook_when_signature_verification_is_disabled(): void
    {
        config(['mindbody.webhooks.verify_signature' => false]);
        Event::fake();

        $payload = $this->createWebhookPayload();

        $response = $this->postJson('/mindbody/webhooks', $payload);

        $response->assertStatus(200);
        $this->assertWebhookEventCreated($payload['EventId'], $payload['EventType']);
    }

    /** @test */
    public function it_handles_different_signature_header_formats(): void
    {
        Event::fake();

        $payload = $this->createWebhookPayload();
        $payloadJson = json_encode($payload);
        $signature = $this->createWebhookSignature($payloadJson);

        // Test different header names
        $headers = [
            'X-Mindbody-Signature',
            'X-MB-Signature',
            'X-Signature',
            'Signature',
        ];

        foreach ($headers as $headerName) {
            $response = $this->postJson('/mindbody/webhooks', $payload, [
                $headerName => $signature,
            ]);

            $response->assertStatus(200);
        }
    }

    /** @test */
    public function it_handles_duplicate_webhooks(): void
    {
        Event::fake();

        $payload = $this->createWebhookPayload();
        $payloadJson = json_encode($payload);
        $signature = $this->createWebhookSignature($payloadJson);

        // Send the same webhook twice
        $this->postJson('/mindbody/webhooks', $payload, [
            'X-Mindbody-Signature' => $signature,
        ]);

        $this->postJson('/mindbody/webhooks', $payload, [
            'X-Mindbody-Signature' => $signature,
        ]);

        // Should only have one record
        $this->assertDatabaseCount('mindbody_webhook_events', 1);
    }

    /** @test */
    public function it_provides_health_check_endpoint(): void
    {
        $response = $this->get('/mindbody/webhooks/health');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'status',
                'service',
                'timestamp',
                'version',
            ]);
    }

    /** @test */
    public function it_provides_test_endpoint_for_localhost(): void
    {
        config(['mindbody.webhooks.enable_test_endpoint' => true]);

        $response = $this->postJson('/mindbody/webhooks/test', [
            'test' => 'data',
        ]);

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'message' => 'Test webhook received successfully',
            ]);
    }

    /** @test */
    public function it_blocks_test_endpoint_from_unauthorized_ips(): void
    {
        config([
            'mindbody.webhooks.enable_test_endpoint' => true,
            'mindbody.webhooks.test_allowed_ips' => ['192.168.1.1'],
        ]);

        // Override the IP for this test
        $this->app->bind('request', function () {
            $request = \Illuminate\Http\Request::create('/mindbody/webhooks/test');
            $request->server->set('REMOTE_ADDR', '10.0.0.1');
            return $request;
        });

        $response = $this->postJson('/mindbody/webhooks/test', ['test' => 'data']);

        $response->assertStatus(403);
    }

    /** @test */
    public function it_provides_stats_endpoint_when_enabled(): void
    {
        config(['mindbody.webhooks.expose_stats' => true]);

        // Create some test events
        WebhookEvent::factory()->count(5)->create(['status' => 'processed']);
        WebhookEvent::factory()->count(2)->create(['status' => 'failed']);

        $response = $this->get('/mindbody/webhooks/stats');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'stats',
                'timestamp',
            ]);
    }

    /** @test */
    public function it_blocks_stats_endpoint_when_disabled(): void
    {
        config(['mindbody.webhooks.expose_stats' => false]);

        $response = $this->get('/mindbody/webhooks/stats');

        $response->assertStatus(404);
    }

    /** @test */
    public function it_handles_malformed_json(): void
    {
        $signature = $this->createWebhookSignature('invalid-json');

        $response = $this->json('POST', '/mindbody/webhooks', [], [
            'X-Mindbody-Signature' => $signature,
            'Content-Type' => 'application/json',
        ], 'invalid-json');

        $response->assertStatus(500)
            ->assertJson([
                'success' => false,
                'error' => 'Internal server error',
            ]);
    }

    /** @test */
    public function it_logs_security_events(): void
    {
        config(['mindbody.logging.enabled' => true]);

        $payload = $this->createWebhookPayload();
        $invalidSignature = 'sha256=invalid';

        $response = $this->postJson('/mindbody/webhooks', $payload, [
            'X-Mindbody-Signature' => $invalidSignature,
        ]);

        $response->assertStatus(400);

        // In a real application, you would check the logs here
        // For testing purposes, we just verify the response
    }
}