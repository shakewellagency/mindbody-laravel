<?php

declare(strict_types=1);
namespace Shakewell\MindbodyLaravel\Commands;

use Illuminate\Console\Command;
use Shakewell\MindbodyLaravel\Exceptions\WebhookException;
use Shakewell\MindbodyLaravel\Services\Webhooks\WebhookSubscriptionManager;

/**
 * Command to unsubscribe from Mindbody webhooks.
 */
class UnsubscribeWebhooksCommand extends Command
{
    protected $signature = 'mindbody:unsubscribe-webhooks
                           {--id=* : Specific subscription IDs to unsubscribe from}
                           {--event=* : Unsubscribe from specific event types}
                           {--all : Unsubscribe from all events}
                           {--dry-run : Show what would be unsubscribed without actually unsubscribing}';

    protected $description = 'Unsubscribe from Mindbody webhooks';

    protected WebhookSubscriptionManager $subscriptionManager;

    public function __construct(WebhookSubscriptionManager $subscriptionManager)
    {
        parent::__construct();
        $this->subscriptionManager = $subscriptionManager;
    }

    public function handle(): int
    {
        if ($this->option('dry-run')) {
            $this->info('🔍 Dry run mode - no actual unsubscriptions will be performed');
            $this->newLine();
        }

        $this->info('Managing Mindbody webhook unsubscriptions...');
        $this->newLine();

        // Get current subscriptions
        try {
            $subscriptions = $this->subscriptionManager->getSubscriptions();
        } catch (\Exception $e) {
            $this->error('Failed to retrieve current subscriptions: '.$e->getMessage());

            return Command::FAILURE;
        }

        if (empty($subscriptions)) {
            $this->info('No active webhook subscriptions found');

            return Command::SUCCESS;
        }

        // Determine what to unsubscribe from
        $toUnsubscribe = $this->getSubscriptionsToRemove($subscriptions);

        if (empty($toUnsubscribe)) {
            $this->info('No subscriptions match the specified criteria');

            return Command::SUCCESS;
        }

        // Display subscriptions to remove
        $this->displaySubscriptionsToRemove($toUnsubscribe);

        // Confirm unsubscription
        if (! $this->option('dry-run') && ! $this->confirmUnsubscription($toUnsubscribe)) {
            $this->info('Unsubscription cancelled');

            return Command::SUCCESS;
        }

        // Perform unsubscriptions
        return $this->performUnsubscriptions($toUnsubscribe);
    }

    protected function getSubscriptionsToRemove(array $subscriptions): array
    {
        if ($this->option('all')) {
            return $subscriptions;
        }

        $specificIds = $this->option('id');
        $specificEvents = $this->option('event');

        if (empty($specificIds) && empty($specificEvents)) {
            // Interactive mode - let user select
            return $this->interactiveSelection($subscriptions);
        }

        $toRemove = [];

        foreach ($subscriptions as $subscription) {
            $shouldRemove = false;

            // Check if ID matches
            if (! empty($specificIds) && \in_array($subscription['Id'], $specificIds, true)) {
                $shouldRemove = true;
            }

            // Check if event type matches
            if (! empty($specificEvents) && \in_array($subscription['EventType'], $specificEvents, true)) {
                $shouldRemove = true;
            }

            if ($shouldRemove) {
                $toRemove[] = $subscription;
            }
        }

        return $toRemove;
    }

    protected function interactiveSelection(array $subscriptions): array
    {
        if ($this->option('no-interaction')) {
            return [];
        }

        $this->info('Current webhook subscriptions:');
        $choices = [];

        foreach ($subscriptions as $index => $subscription) {
            $id = $subscription['Id'];
            $eventType = $subscription['EventType'];
            $url = $subscription['WebhookUrl'] ?? 'N/A';
            $status = $subscription['Status'] ?? 'Active';

            $choice = "{$eventType} (ID: {$id}) - {$status}";
            $choices[$index] = $choice;

            $this->line("  [{$index}] {$choice}");
            $this->line("      URL: {$url}");
        }

        $this->newLine();

        if ($this->confirm('Select specific subscriptions to remove?')) {
            $selected = $this->ask('Enter subscription numbers separated by commas (e.g., 0,1,2)');

            if ($selected) {
                $indices = array_map('trim', explode(',', $selected));
                $toRemove = [];

                foreach ($indices as $index) {
                    if (isset($subscriptions[$index])) {
                        $toRemove[] = $subscriptions[$index];
                    }
                }

                return $toRemove;
            }
        }

        return [];
    }

    protected function displaySubscriptionsToRemove(array $subscriptions): void
    {
        $this->info('Subscriptions to remove:');

        foreach ($subscriptions as $subscription) {
            $id = $subscription['Id'];
            $eventType = $subscription['EventType'];
            $url = $subscription['WebhookUrl'] ?? 'N/A';

            $this->line("  • {$eventType} (ID: {$id})");
            $this->line("    URL: {$url}");
        }

        $this->newLine();
    }

    protected function confirmUnsubscription(array $subscriptions): bool
    {
        if ($this->option('no-interaction')) {
            return true;
        }

        $count = \count($subscriptions);

        return $this->confirm("Remove {$count} webhook subscription(s)?");
    }

    protected function performUnsubscriptions(array $subscriptions): int
    {
        $successCount = 0;
        $failureCount = 0;

        foreach ($subscriptions as $subscription) {
            $id = $subscription['Id'];
            $eventType = $subscription['EventType'];

            if ($this->option('dry-run')) {
                $this->info("Would unsubscribe from: {$eventType} (ID: {$id})");
                $successCount++;
                continue;
            }

            try {
                $this->subscriptionManager->unsubscribe($id);

                $this->info("✅ Unsubscribed from {$eventType} (ID: {$id})");
                $successCount++;
            } catch (WebhookException $e) {
                $this->error("❌ Failed to unsubscribe from {$eventType}: ".$e->getMessage());
                $failureCount++;
            } catch (\Exception $e) {
                $this->error("❌ Error unsubscribing from {$eventType}: ".$e->getMessage());
                $failureCount++;
            }
        }

        $this->newLine();
        $this->info('Unsubscription complete:');
        $this->line("  ✅ Successful: {$successCount}");

        if ($failureCount > 0) {
            $this->line("  ❌ Failed: {$failureCount}");

            return Command::FAILURE;
        }

        return Command::SUCCESS;
    }
}
