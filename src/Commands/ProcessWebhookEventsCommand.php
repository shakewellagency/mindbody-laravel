<?php

declare(strict_types=1);

namespace Shakewell\MindbodyLaravel\Commands;

use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Queue;
use Shakewell\MindbodyLaravel\Models\WebhookEvent;
use Shakewell\MindbodyLaravel\Services\Webhooks\WebhookHandler;

/**
 * Command to process pending webhook events.
 */
class ProcessWebhookEventsCommand extends Command
{
    protected $signature = 'mindbody:process-webhooks
                           {--limit=100 : Maximum number of events to process}
                           {--timeout=300 : Maximum execution time in seconds}
                           {--retry-failed : Include failed events for retry}
                           {--max-retries=3 : Maximum retry attempts for failed events}
                           {--dry-run : Show events that would be processed without processing them}';

    protected $description = 'Process pending webhook events';

    protected WebhookHandler $webhookHandler;

    public function __construct(WebhookHandler $webhookHandler)
    {
        parent::__construct();
        $this->webhookHandler = $webhookHandler;
    }

    public function handle(): int
    {
        $startTime = time();
        $timeout = (int) $this->option('timeout');
        $limit = (int) $this->option('limit');
        $retryFailed = $this->option('retry-failed');
        $maxRetries = (int) $this->option('max-retries');
        $dryRun = $this->option('dry-run');

        if ($dryRun) {
            $this->info('🔍 Dry run mode - no events will be processed');
            $this->newLine();
        }

        $this->info("Processing webhook events (limit: {$limit}, timeout: {$timeout}s)...");
        $this->newLine();

        // Get pending events
        $events = $this->getPendingEvents($limit, $retryFailed, $maxRetries);

        if ($events->isEmpty()) {
            $this->info('No pending webhook events to process');

            return Command::SUCCESS;
        }

        $this->info("Found {$events->count()} events to process");
        $this->newLine();

        if ($dryRun) {
            $this->displayEventsToProcess($events);

            return Command::SUCCESS;
        }

        // Process events
        $stats = $this->processEvents($events, $startTime, $timeout);

        // Display results
        $this->displayResults($stats);

        return $stats['failed'] > 0 ? Command::FAILURE : Command::SUCCESS;
    }

    protected function getPendingEvents(int $limit, bool $retryFailed, int $maxRetries)
    {
        $query = WebhookEvent::query()
            ->orderBy('created_at', 'asc')
            ->limit($limit);

        if ($retryFailed) {
            $query->where(static function ($q) use ($maxRetries) {
                $q->where('status', 'pending')
                    ->orWhere(static function ($subQ) use ($maxRetries) {
                        $subQ->where('status', 'failed')
                            ->where('retry_count', '<', $maxRetries);
                    });
            });
        } else {
            $query->where('status', 'pending');
        }

        return $query->get();
    }

    protected function displayEventsToProcess($events): void
    {
        $headers = ['ID', 'Event Type', 'Status', 'Retries', 'Created At', 'Last Error'];
        $rows = [];

        foreach ($events as $event) {
            $rows[] = [
                $event->id,
                $event->event_type,
                $event->status,
                $event->retry_count,
                $event->created_at->format('Y-m-d H:i:s'),
                $event->error_message ? substr($event->error_message, 0, 50).'...' : 'None',
            ];
        }

        $this->table($headers, $rows);
    }

    protected function processEvents($events, int $startTime, int $timeout): array
    {
        $stats = [
            'processed' => 0,
            'successful' => 0,
            'failed' => 0,
            'skipped' => 0,
        ];

        $progressBar = $this->output->createProgressBar($events->count());
        $progressBar->start();

        foreach ($events as $event) {
            // Check timeout
            if (time() - $startTime >= $timeout) {
                $this->newLine();
                $this->warn("⏰ Timeout reached after {$timeout} seconds");
                break;
            }

            try {
                $result = $this->processEvent($event);

                if ($result) {
                    $stats['successful']++;
                } else {
                    $stats['failed']++;
                }

                $stats['processed']++;
            } catch (\Exception $e) {
                $this->updateEventStatus($event, 'failed', $e->getMessage());
                $stats['failed']++;
                $stats['processed']++;
            }

            $progressBar->advance();
        }

        $progressBar->finish();
        $this->newLine(2);

        return $stats;
    }

    protected function processEvent(WebhookEvent $event): bool
    {
        try {
            // Update status to processing
            $event->update([
                'status' => 'processing',
                'processed_at' => Carbon::now(),
            ]);

            // Process the event
            $this->webhookHandler->processEvent($event);

            // Mark as processed
            $event->update([
                'status' => 'processed',
                'error_message' => null,
            ]);

            return true;
        } catch (\Exception $e) {
            // Handle retry logic
            $retryCount = $event->retry_count + 1;
            $maxRetries = (int) $this->option('max-retries');

            if ($retryCount < $maxRetries) {
                // Schedule for retry
                $nextRetryAt = Carbon::now()->addMinutes($this->calculateRetryDelay($retryCount));

                $event->update([
                    'status' => 'pending',
                    'retry_count' => $retryCount,
                    'error_message' => $e->getMessage(),
                    'next_retry_at' => $nextRetryAt,
                ]);
            } else {
                // Mark as permanently failed
                $event->update([
                    'status' => 'failed',
                    'retry_count' => $retryCount,
                    'error_message' => $e->getMessage(),
                ]);
            }

            return false;
        }
    }

    protected function updateEventStatus(WebhookEvent $event, string $status, ?string $error = null): void
    {
        $event->update([
            'status' => $status,
            'error_message' => $error,
            'processed_at' => Carbon::now(),
        ]);
    }

    protected function calculateRetryDelay(int $retryCount): int
    {
        // Exponential backoff: 1, 2, 4, 8, 16 minutes
        return min(2 ** ($retryCount - 1), 60);
    }

    protected function displayResults(array $stats): void
    {
        $this->info('Processing complete:');
        $this->line("  📊 Total processed: {$stats['processed']}");
        $this->line("  ✅ Successful: {$stats['successful']}");
        $this->line("  ❌ Failed: {$stats['failed']}");

        if ($stats['skipped'] > 0) {
            $this->line("  ⏭️  Skipped: {$stats['skipped']}");
        }

        // Show queue status if using queues
        if (config('mindbody.webhooks.use_queue', false)) {
            $this->newLine();
            $this->displayQueueStatus();
        }

        // Show failed events summary
        if ($stats['failed'] > 0) {
            $this->newLine();
            $this->displayFailedEventsSummary();
        }
    }

    protected function displayQueueStatus(): void
    {
        try {
            $queueName = config('mindbody.webhooks.queue_name', 'default');
            $size = Queue::size($queueName);

            $this->info('Queue status:');
            $this->line("  Queue: {$queueName}");
            $this->line("  Pending jobs: {$size}");
        } catch (\Exception $e) {
            $this->warn('Unable to check queue status: '.$e->getMessage());
        }
    }

    protected function displayFailedEventsSummary(): void
    {
        $failedEvents = WebhookEvent::whereNotNull('error')
            ->selectRaw('event_type, COUNT(*) as count')
            ->groupBy('event_type')
            ->get();

        if ($failedEvents->isNotEmpty()) {
            $this->warn('Failed events by type:');
            foreach ($failedEvents as $eventSummary) {
                $this->line("  {$eventSummary->event_type}: {$eventSummary->count}");
            }
        }
    }
}
