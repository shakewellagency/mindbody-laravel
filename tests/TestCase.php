<?php

declare(strict_types=1);

namespace Shakewell\MindbodyLaravel\Tests;

use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Orchestra\Testbench\TestCase as Orchestra;
use Shakewell\MindbodyLaravel\MindbodyLaravelServiceProvider;

abstract class TestCase extends Orchestra
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Factory::guessFactoryNamesUsing(
            static fn (string $modelName) => 'Shakewell\MindbodyLaravel\Database\Factories\\'.class_basename($modelName).'Factory'
        );
    }

    protected function getPackageProviders($app): array
    {
        return [
            MindbodyLaravelServiceProvider::class,
        ];
    }

    protected function getEnvironmentSetUp($app): void
    {
        config()->set('database.default', 'testing');
        config()->set('database.connections.testing', [
            'driver' => 'sqlite',
            'database' => ':memory:',
            'prefix' => '',
        ]);

        // Set up basic Mindbody configuration for testing
        config()->set('mindbody.api.base_url', 'https://api.mindbodyonline.com');
        config()->set('mindbody.api.version', 'v6');
        config()->set('mindbody.api.site_id', 'test-site-id');
        config()->set('mindbody.api.source_name', 'Test Source');
        config()->set('mindbody.auth.username', 'test-username');
        config()->set('mindbody.auth.password', 'test-password');
        config()->set('mindbody.webhooks.enabled', true);
        config()->set('mindbody.webhooks.url', 'https://example.com/webhooks');
        config()->set('mindbody.webhooks.signature_key', 'test-signature-key');
        config()->set('mindbody.caching.enabled', false); // Disable caching for tests
        config()->set('mindbody.logging.enabled', false); // Disable logging for tests
    }

    /**
     * Define database migrations.
     */
    protected function defineDatabaseMigrations(): void
    {
        $this->loadMigrationsFrom(__DIR__.'/../database/migrations');
    }

    /**
     * Mock HTTP responses for testing.
     */
    protected function mockHttpResponse(array $responses): void
    {
        $this->app->bind('http-client', static function () use ($responses) {
            return new \GuzzleHttp\Client([
                'handler' => \GuzzleHttp\HandlerStack::create(
                    new \GuzzleHttp\Handler\MockHandler($responses)
                ),
            ]);
        });
    }

    /**
     * Create a fake webhook payload for testing.
     */
    protected function createWebhookPayload(string $eventType = 'appointment.booked', array $data = []): array
    {
        return [
            'EventId' => 'test-event-'.uniqid(),
            'MessageId' => 'test-message-'.uniqid(),
            'EventType' => $eventType,
            'EventInstanceId' => 'test-instance-'.uniqid(),
            'EventSchemaVersion' => 1,
            'EventOccurredDateTime' => now()->toISOString(),
            'SiteId' => config('mindbody.api.site_id'),
            'EventData' => array_merge([
                'Appointment' => [
                    'Id' => 12345,
                    'Status' => 'Confirmed',
                    'StartDateTime' => now()->addDay()->toISOString(),
                    'EndDateTime' => now()->addDay()->addHour()->toISOString(),
                    'ClientId' => 'test-client-123',
                    'StaffId' => 456,
                    'LocationId' => 1,
                    'SessionType' => [
                        'Id' => 1,
                        'Name' => 'Personal Training',
                    ],
                ],
            ], $data),
        ];
    }

    /**
     * Create a webhook signature for testing.
     */
    protected function createWebhookSignature(string $payload, ?string $key = null): string
    {
        $key = $key ?: config('mindbody.webhooks.signature_key');
        $hash = hash_hmac('sha256', $payload, $key);

        return 'sha256='.$hash;
    }

    /**
     * Assert that a webhook event was created.
     */
    protected function assertWebhookEventCreated(string $eventId, string $eventType): void
    {
        $this->assertDatabaseHas('mindbody_webhook_events', [
            'event_id' => $eventId,
            'event_type' => $eventType,
        ]);
    }

    /**
     * Assert that an event was dispatched.
     */
    protected function assertEventDispatched(string $eventClass): void
    {
        $this->expectsEvents($eventClass);
    }
}
