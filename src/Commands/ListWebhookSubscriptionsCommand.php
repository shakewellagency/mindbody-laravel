<?php

declare(strict_types=1);

namespace Shakewell\MindbodyLaravel\Commands;

use Illuminate\Console\Command;
use Shakewell\MindbodyLaravel\Services\Webhooks\WebhookSubscriptionManager;
use Shakewell\MindbodyLaravel\Exceptions\WebhookException;

/**
 * Command to list current Mindbody webhook subscriptions
 */
class ListWebhookSubscriptionsCommand extends Command
{
    protected $signature = 'mindbody:list-webhooks 
                           {--format=table : Output format (table|json|csv)}
                           {--filter= : Filter by event type}
                           {--status= : Filter by status (active|inactive|all)}';

    protected $description = 'List all current Mindbody webhook subscriptions';

    protected WebhookSubscriptionManager $subscriptionManager;

    public function __construct(WebhookSubscriptionManager $subscriptionManager)
    {
        parent::__construct();
        $this->subscriptionManager = $subscriptionManager;
    }

    public function handle(): int
    {
        $this->info('Retrieving Mindbody webhook subscriptions...');
        $this->newLine();

        try {
            $subscriptions = $this->subscriptionManager->getSubscriptions();
        } catch (WebhookException $e) {
            $this->error("Failed to retrieve subscriptions: " . $e->getMessage());
            return Command::FAILURE;
        } catch (\Exception $e) {
            $this->error("Error retrieving subscriptions: " . $e->getMessage());
            return Command::FAILURE;
        }

        if (empty($subscriptions)) {
            $this->info('No webhook subscriptions found');
            return Command::SUCCESS;
        }

        // Apply filters
        $subscriptions = $this->applyFilters($subscriptions);

        if (empty($subscriptions)) {
            $this->info('No subscriptions match the specified filters');
            return Command::SUCCESS;
        }

        // Display subscriptions
        $this->displaySubscriptions($subscriptions);

        return Command::SUCCESS;
    }

    protected function applyFilters(array $subscriptions): array
    {
        $eventFilter = $this->option('filter');
        $statusFilter = $this->option('status');

        $filtered = $subscriptions;

        // Filter by event type
        if ($eventFilter) {
            $filtered = array_filter($filtered, function ($subscription) use ($eventFilter) {
                return stripos($subscription['EventType'], $eventFilter) !== false;
            });
        }

        // Filter by status
        if ($statusFilter && $statusFilter !== 'all') {
            $filtered = array_filter($filtered, function ($subscription) use ($statusFilter) {
                $status = strtolower($subscription['Status'] ?? 'active');
                return $status === strtolower($statusFilter);
            });
        }

        return array_values($filtered);
    }

    protected function displaySubscriptions(array $subscriptions): void
    {
        $format = $this->option('format');

        switch ($format) {
            case 'json':
                $this->displayAsJson($subscriptions);
                break;
            case 'csv':
                $this->displayAsCsv($subscriptions);
                break;
            case 'table':
            default:
                $this->displayAsTable($subscriptions);
                break;
        }
    }

    protected function displayAsTable(array $subscriptions): void
    {
        $headers = ['ID', 'Event Type', 'Status', 'URL', 'Created', 'Last Updated'];
        $rows = [];

        foreach ($subscriptions as $subscription) {
            $rows[] = [
                $subscription['Id'],
                $subscription['EventType'],
                $subscription['Status'] ?? 'Active',
                $this->truncateUrl($subscription['WebhookUrl'] ?? 'N/A'),
                $this->formatDate($subscription['CreatedDateTime'] ?? null),
                $this->formatDate($subscription['UpdatedDateTime'] ?? null),
            ];
        }

        $this->table($headers, $rows);
        $this->newLine();
        $this->info("Total subscriptions: " . count($subscriptions));
    }

    protected function displayAsJson(array $subscriptions): void
    {
        $this->line(json_encode($subscriptions, JSON_PRETTY_PRINT));
    }

    protected function displayAsCsv(array $subscriptions): void
    {
        $headers = ['ID', 'EventType', 'Status', 'WebhookUrl', 'CreatedDateTime', 'UpdatedDateTime'];
        $this->line(implode(',', $headers));

        foreach ($subscriptions as $subscription) {
            $row = [
                $subscription['Id'],
                $subscription['EventType'],
                $subscription['Status'] ?? 'Active',
                '"' . ($subscription['WebhookUrl'] ?? '') . '"',
                $subscription['CreatedDateTime'] ?? '',
                $subscription['UpdatedDateTime'] ?? '',
            ];
            $this->line(implode(',', $row));
        }
    }

    protected function truncateUrl(string $url, int $maxLength = 50): string
    {
        if (strlen($url) <= $maxLength) {
            return $url;
        }

        return substr($url, 0, $maxLength - 3) . '...';
    }

    protected function formatDate(?string $dateString): string
    {
        if (!$dateString) {
            return 'N/A';
        }

        try {
            return \Carbon\Carbon::parse($dateString)->format('Y-m-d H:i');
        } catch (\Exception $e) {
            return $dateString;
        }
    }
}