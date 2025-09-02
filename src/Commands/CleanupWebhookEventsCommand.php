<?php

declare(strict_types=1);
namespace Shakewell\MindbodyLaravel\Commands;

use Carbon\Carbon;
use Illuminate\Console\Command;
use Shakewell\MindbodyLaravel\Models\WebhookEvent;

/**
 * Command to clean up old webhook events.
 */
class CleanupWebhookEventsCommand extends Command
{
    protected $signature = 'mindbody:cleanup-webhooks
                           {--days=30 : Delete events older than this many days}
                           {--status=* : Only delete events with specific status (processed|failed|all)}
                           {--batch-size=1000 : Number of records to delete per batch}
                           {--dry-run : Show what would be deleted without actually deleting}
                           {--force : Skip confirmation prompt}';

    protected $description = 'Clean up old webhook events from the database';

    public function handle(): int
    {
        $days = (int) $this->option('days');
        $statuses = $this->option('status') ?: ['processed'];
        $batchSize = (int) $this->option('batch-size');
        $dryRun = $this->option('dry-run');
        $force = $this->option('force');

        if ($dryRun) {
            $this->info('🔍 Dry run mode - no records will be deleted');
            $this->newLine();
        }

        $this->info("Cleaning up webhook events older than {$days} days...");
        $this->newLine();

        // Validate inputs
        if ($days <= 0) {
            $this->error('Days must be greater than 0');

            return Command::FAILURE;
        }

        if ($batchSize <= 0) {
            $this->error('Batch size must be greater than 0');

            return Command::FAILURE;
        }

        // Calculate cutoff date
        $cutoffDate = Carbon::now()->subDays($days);
        $this->info("Cutoff date: {$cutoffDate->format('Y-m-d H:i:s')}");

        // Build query
        $query = $this->buildCleanupQuery($cutoffDate, $statuses);

        // Get count of records to delete
        $totalCount = $query->count();

        if (0 === $totalCount) {
            $this->info('No records found matching cleanup criteria');

            return Command::SUCCESS;
        }

        // Display summary
        $this->displayCleanupSummary($totalCount, $cutoffDate, $statuses);

        // Confirm deletion
        if (! $dryRun && ! $force && ! $this->confirmCleanup($totalCount)) {
            $this->info('Cleanup cancelled');

            return Command::SUCCESS;
        }

        // Perform cleanup
        $deletedCount = $this->performCleanup($query, $batchSize, $dryRun);

        $this->newLine();
        if ($dryRun) {
            $this->info("Would delete {$deletedCount} webhook events");
        } else {
            $this->info("Successfully deleted {$deletedCount} webhook events");
        }

        return Command::SUCCESS;
    }

    protected function buildCleanupQuery(Carbon $cutoffDate, array $statuses)
    {
        $query = WebhookEvent::where('created_at', '<', $cutoffDate);

        if (! \in_array('all', $statuses, true)) {
            $query->whereIn('status', $statuses);
        }

        return $query;
    }

    protected function displayCleanupSummary(int $totalCount, Carbon $cutoffDate, array $statuses): void
    {
        $this->info('Cleanup Summary:');
        $this->line("  Records to delete: {$totalCount}");
        $this->line("  Cutoff date: {$cutoffDate->format('Y-m-d H:i:s')}");
        $this->line('  Status filter: '.implode(', ', $statuses));
        $this->newLine();

        // Show breakdown by status
        $this->displayStatusBreakdown($cutoffDate, $statuses);

        // Show breakdown by event type
        $this->displayEventTypeBreakdown($cutoffDate, $statuses);
    }

    protected function displayStatusBreakdown(Carbon $cutoffDate, array $statuses): void
    {
        $query = WebhookEvent::where('created_at', '<', $cutoffDate);

        if (! \in_array('all', $statuses, true)) {
            $query->whereIn('status', $statuses);
        }

        $breakdown = $query->selectRaw('status, COUNT(*) as count')
            ->groupBy('status')
            ->orderBy('count', 'desc')
            ->get();

        if ($breakdown->isNotEmpty()) {
            $this->info('Breakdown by status:');
            foreach ($breakdown as $item) {
                $this->line("  {$item->status}: {$item->count}");
            }
            $this->newLine();
        }
    }

    protected function displayEventTypeBreakdown(Carbon $cutoffDate, array $statuses): void
    {
        $query = WebhookEvent::where('created_at', '<', $cutoffDate);

        if (! \in_array('all', $statuses, true)) {
            $query->whereIn('status', $statuses);
        }

        $breakdown = $query->selectRaw('event_type, COUNT(*) as count')
            ->groupBy('event_type')
            ->orderBy('count', 'desc')
            ->limit(10)
            ->get();

        if ($breakdown->isNotEmpty()) {
            $this->info('Top event types:');
            foreach ($breakdown as $item) {
                $this->line("  {$item->event_type}: {$item->count}");
            }
            $this->newLine();
        }
    }

    protected function confirmCleanup(int $totalCount): bool
    {
        if ($this->option('no-interaction')) {
            return true;
        }

        return $this->confirm("Are you sure you want to delete {$totalCount} webhook events?");
    }

    protected function performCleanup($query, int $batchSize, bool $dryRun): int
    {
        $totalDeleted = 0;
        $totalCount = $query->count();

        if (0 === $totalCount) {
            return 0;
        }

        $progressBar = $this->output->createProgressBar($totalCount);
        $progressBar->setFormat('verbose');
        $progressBar->start();

        do {
            // Get a batch of IDs to delete
            $batch = clone $query;
            $ids = $batch->limit($batchSize)->pluck('id')->toArray();

            if (empty($ids)) {
                break;
            }

            if (! $dryRun) {
                // Delete the batch
                $deletedInBatch = WebhookEvent::whereIn('id', $ids)->delete();
                $totalDeleted += $deletedInBatch;
            } else {
                $totalDeleted += \count($ids);
            }

            $progressBar->advance(\count($ids));

            // Small delay to prevent overwhelming the database
            if (! $dryRun) {
                usleep(50000); // 50ms
            }
        } while (\count($ids) === $batchSize);

        $progressBar->finish();
        $this->newLine();

        return $totalDeleted;
    }
}
