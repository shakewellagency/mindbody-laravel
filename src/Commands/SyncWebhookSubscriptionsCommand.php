<?php

declare(strict_types=1);

namespace Shakewell\MindbodyLaravel\Commands;

use Illuminate\Console\Command;
use Shakewell\MindbodyLaravel\Services\Webhooks\WebhookSubscriptionManager;

/**
 * Command to synchronize webhook subscriptions with configuration.
 */
class SyncWebhookSubscriptionsCommand extends Command
{
    protected $signature = 'mindbody:sync-webhooks
                           {--dry-run : Show what would be changed without making changes}
                           {--force : Force sync without confirmation}';

    protected $description = 'Synchronize webhook subscriptions with configuration';

    protected WebhookSubscriptionManager $subscriptionManager;

    public function __construct(WebhookSubscriptionManager $subscriptionManager)
    {
        parent::__construct();
        $this->subscriptionManager = $subscriptionManager;
    }

    public function handle(): int
    {
        if ($this->option('dry-run')) {
            $this->info('🔍 Dry run mode - no changes will be made');
            $this->newLine();
        }

        $this->info('Synchronizing webhook subscriptions with configuration...');
        $this->newLine();

        // Get configured events
        $configuredEvents = $this->getConfiguredEvents();
        if (empty($configuredEvents)) {
            $this->warn('No events configured in mindbody.webhooks.events - nothing to sync');

            return Command::SUCCESS;
        }

        // Get webhook URL
        $webhookUrl = $this->getWebhookUrl();
        if (! $webhookUrl) {
            $this->error('❌ No webhook URL configured');

            return Command::FAILURE;
        }

        // Get current subscriptions
        try {
            $currentSubscriptions = $this->subscriptionManager->getSubscriptions();
        } catch (\Exception $e) {
            $this->error('Failed to retrieve current subscriptions: '.$e->getMessage());

            return Command::FAILURE;
        }

        // Analyze differences
        $analysis = $this->analyzeSubscriptions($configuredEvents, $currentSubscriptions, $webhookUrl);

        // Display analysis
        $this->displayAnalysis($analysis);

        // Check if any changes are needed
        if (empty($analysis['toAdd']) && empty($analysis['toRemove']) && empty($analysis['toUpdate'])) {
            $this->info('✅ Subscriptions are already in sync');

            return Command::SUCCESS;
        }

        // Confirm changes
        if (! $this->option('dry-run') && ! $this->confirmSync($analysis)) {
            $this->info('Synchronization cancelled');

            return Command::SUCCESS;
        }

        // Perform synchronization
        return $this->performSync($analysis);
    }

    protected function getConfiguredEvents(): array
    {
        return config('mindbody.webhooks.events', []);
    }

    protected function getWebhookUrl(): ?string
    {
        $url = config('mindbody.webhooks.url');

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

    protected function analyzeSubscriptions(array $configuredEvents, array $currentSubscriptions, string $webhookUrl): array
    {
        $currentEventMap = [];
        foreach ($currentSubscriptions as $subscription) {
            $eventType = $subscription['EventType'];
            $currentEventMap[$eventType] = $subscription;
        }

        $toAdd = [];
        $toRemove = [];
        $toUpdate = [];

        // Find events to add
        foreach ($configuredEvents as $eventType) {
            if (! isset($currentEventMap[$eventType])) {
                $toAdd[] = $eventType;
            } elseif ($currentEventMap[$eventType]['WebhookUrl'] !== $webhookUrl) {
                $toUpdate[] = [
                    'subscription' => $currentEventMap[$eventType],
                    'newUrl' => $webhookUrl,
                ];
            }
        }

        // Find events to remove
        foreach ($currentEventMap as $eventType => $subscription) {
            if (! \in_array($eventType, $configuredEvents, true)) {
                $toRemove[] = $subscription;
            }
        }

        return [
            'toAdd' => $toAdd,
            'toRemove' => $toRemove,
            'toUpdate' => $toUpdate,
            'webhookUrl' => $webhookUrl,
        ];
    }

    protected function displayAnalysis(array $analysis): void
    {
        $this->info("Webhook URL: {$analysis['webhookUrl']}");
        $this->newLine();

        if (! empty($analysis['toAdd'])) {
            $this->info('Events to add:');
            foreach ($analysis['toAdd'] as $eventType) {
                $this->line("  + {$eventType}");
            }
            $this->newLine();
        }

        if (! empty($analysis['toRemove'])) {
            $this->info('Subscriptions to remove:');
            foreach ($analysis['toRemove'] as $subscription) {
                $this->line("  - {$subscription['EventType']} (ID: {$subscription['Id']})");
            }
            $this->newLine();
        }

        if (! empty($analysis['toUpdate'])) {
            $this->info('Subscriptions to update:');
            foreach ($analysis['toUpdate'] as $update) {
                $subscription = $update['subscription'];
                $this->line("  ~ {$subscription['EventType']} (ID: {$subscription['Id']})");
                $this->line("    Old URL: {$subscription['WebhookUrl']}");
                $this->line("    New URL: {$update['newUrl']}");
            }
            $this->newLine();
        }
    }

    protected function confirmSync(array $analysis): bool
    {
        if ($this->option('force') || $this->option('no-interaction')) {
            return true;
        }

        $addCount = \count($analysis['toAdd']);
        $removeCount = \count($analysis['toRemove']);
        $updateCount = \count($analysis['toUpdate']);

        $message = 'Proceed with synchronization? ';
        $message .= "({$addCount} to add, {$removeCount} to remove, {$updateCount} to update)";

        return $this->confirm($message);
    }

    protected function performSync(array $analysis): int
    {
        $totalOperations = \count($analysis['toAdd']) + \count($analysis['toRemove']) + \count($analysis['toUpdate']);
        $successCount = 0;
        $failureCount = 0;

        // Remove subscriptions
        foreach ($analysis['toRemove'] as $subscription) {
            if ($this->option('dry-run')) {
                $this->info("Would remove: {$subscription['EventType']} (ID: {$subscription['Id']})");
                $successCount++;

                continue;
            }

            try {
                $this->subscriptionManager->unsubscribe($subscription['Id']);
                $this->info("✅ Removed {$subscription['EventType']} (ID: {$subscription['Id']})");
                $successCount++;
            } catch (\Exception $e) {
                $this->error("❌ Failed to remove {$subscription['EventType']}: ".$e->getMessage());
                $failureCount++;
            }
        }

        // Add subscriptions
        foreach ($analysis['toAdd'] as $eventType) {
            if ($this->option('dry-run')) {
                $this->info("Would add: {$eventType}");
                $successCount++;

                continue;
            }

            try {
                $subscription = $this->subscriptionManager->subscribe($eventType, $analysis['webhookUrl']);
                $this->info("✅ Added {$eventType} (ID: {$subscription['Id']})");
                $successCount++;
            } catch (\Exception $e) {
                $this->error("❌ Failed to add {$eventType}: ".$e->getMessage());
                $failureCount++;
            }
        }

        // Update subscriptions
        foreach ($analysis['toUpdate'] as $update) {
            $subscription = $update['subscription'];
            $eventType = $subscription['EventType'];
            $subscriptionId = $subscription['Id'];

            if ($this->option('dry-run')) {
                $this->info("Would update: {$eventType} (ID: {$subscriptionId})");
                $successCount++;

                continue;
            }

            try {
                // Remove old subscription and create new one
                $this->subscriptionManager->unsubscribe($subscriptionId);
                $newSubscription = $this->subscriptionManager->subscribe($eventType, $update['newUrl']);

                $this->info("✅ Updated {$eventType} (New ID: {$newSubscription['Id']})");
                $successCount++;
            } catch (\Exception $e) {
                $this->error("❌ Failed to update {$eventType}: ".$e->getMessage());
                $failureCount++;
            }
        }

        $this->newLine();
        $this->info('Synchronization complete:');
        $this->line("  ✅ Successful: {$successCount}/{$totalOperations}");

        if ($failureCount > 0) {
            $this->line("  ❌ Failed: {$failureCount}/{$totalOperations}");

            return Command::FAILURE;
        }

        return Command::SUCCESS;
    }
}
