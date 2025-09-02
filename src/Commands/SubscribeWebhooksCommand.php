<?php

declare(strict_types=1);
namespace Shakewell\MindbodyLaravel\Commands;

use Illuminate\Console\Command;
use Shakewell\MindbodyLaravel\Exceptions\WebhookException;
use Shakewell\MindbodyLaravel\Services\Webhooks\WebhookSubscriptionManager;

/**
 * Command to subscribe to Mindbody webhooks.
 */
class SubscribeWebhooksCommand extends Command
{
    protected $signature = 'mindbody:subscribe-webhooks
                           {--event=* : Specific event types to subscribe to}
                           {--all : Subscribe to all available events}
                           {--url= : Override webhook URL}
                           {--dry-run : Show what would be subscribed without actually subscribing}';

    protected $description = 'Subscribe to Mindbody webhooks';

    protected WebhookSubscriptionManager $subscriptionManager;

    public function __construct(WebhookSubscriptionManager $subscriptionManager)
    {
        parent::__construct();
        $this->subscriptionManager = $subscriptionManager;
    }

    public function handle(): int
    {
        if ($this->option('dry-run')) {
            $this->info('🔍 Dry run mode - no actual subscriptions will be created');
            $this->newLine();
        }

        $this->info('Managing Mindbody webhook subscriptions...');
        $this->newLine();

        // Get webhook configuration
        $webhookUrl = $this->getWebhookUrl();
        if (! $webhookUrl) {
            $this->error('❌ No webhook URL configured or provided');

            return Command::FAILURE;
        }

        $this->info("Webhook URL: {$webhookUrl}");
        $this->newLine();

        // Determine events to subscribe to
        $events = $this->getEventsToSubscribe();
        if (empty($events)) {
            $this->error('❌ No events specified for subscription');

            return Command::FAILURE;
        }

        // Display events to subscribe to
        $this->displayEvents($events);

        // Confirm subscription
        if (! $this->option('dry-run') && ! $this->confirmSubscription($events)) {
            $this->info('Subscription cancelled');

            return Command::SUCCESS;
        }

        // Subscribe to events
        return $this->subscribeToEvents($events, $webhookUrl);
    }

    protected function getWebhookUrl(): ?string
    {
        $url = $this->option('url') ?: config('mindbody.webhooks.url');

        if (! $url) {
            // Try to generate from app URL
            $appUrl = config('app.url');
            if ($appUrl) {
                $routePrefix = config('mindbody.webhooks.route_prefix', 'mindbody/webhooks');
                $url = rtrim($appUrl, '/').'/'.ltrim($routePrefix, '/');
            }
        }

        return $url;
    }

    protected function getEventsToSubscribe(): array
    {
        if ($this->option('all')) {
            return $this->subscriptionManager->getAvailableEvents();
        }

        $specifiedEvents = $this->option('event');
        if (! empty($specifiedEvents)) {
            return $specifiedEvents;
        }

        // Get from configuration
        return config('mindbody.webhooks.events', []);
    }

    protected function displayEvents(array $events): void
    {
        $this->info('Events to subscribe to:');
        foreach ($events as $event) {
            $this->line("  • {$event}");
        }
        $this->newLine();
    }

    protected function confirmSubscription(array $events): bool
    {
        if ($this->option('no-interaction')) {
            return true;
        }

        $count = \count($events);

        return $this->confirm("Subscribe to {$count} webhook event(s)?");
    }

    protected function subscribeToEvents(array $events, string $webhookUrl): int
    {
        $successCount = 0;
        $failureCount = 0;

        foreach ($events as $eventType) {
            if ($this->option('dry-run')) {
                $this->info("Would subscribe to: {$eventType}");
                $successCount++;
                continue;
            }

            try {
                $subscription = $this->subscriptionManager->subscribe($eventType, $webhookUrl);

                $this->info("✅ Subscribed to {$eventType}");
                $this->line("   Subscription ID: {$subscription['Id']}");
                $successCount++;
            } catch (WebhookException $e) {
                $this->error("❌ Failed to subscribe to {$eventType}: ".$e->getMessage());
                $failureCount++;
            } catch (\Exception $e) {
                $this->error("❌ Error subscribing to {$eventType}: ".$e->getMessage());
                $failureCount++;
            }
        }

        $this->newLine();
        $this->info('Subscription complete:');
        $this->line("  ✅ Successful: {$successCount}");

        if ($failureCount > 0) {
            $this->line("  ❌ Failed: {$failureCount}");

            return Command::FAILURE;
        }

        return Command::SUCCESS;
    }
}
