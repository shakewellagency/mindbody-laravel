<?php

declare(strict_types=1);

namespace Shakewell\MindbodyLaravel\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;

/**
 * Webhook event model for storing incoming webhook data.
 *
 * @property int $id
 * @property null|string $event_id
 * @property string $event_type
 * @property null|string $site_id
 * @property Collection $event_data
 * @property null|Collection $headers
 * @property null|Carbon $event_timestamp
 * @property bool $processed
 * @property null|Carbon $processed_at
 * @property int $retry_count
 * @property null|string $error
 * @property null|string $signature
 * @property Carbon $created_at
 * @property Carbon $updated_at
 */
class WebhookEvent extends Model
{
    protected $table = 'mindbody_webhook_events';

    protected $fillable = [
        'event_id',
        'event_type',
        'site_id',
        'event_data',
        'headers',
        'event_timestamp',
        'processed',
        'processed_at',
        'retry_count',
        'error',
        'signature',
    ];

    protected $casts = [
        'event_data' => 'collection',
        'headers' => 'collection',
        'event_timestamp' => 'datetime',
        'processed' => 'boolean',
        'processed_at' => 'datetime',
        'retry_count' => 'integer',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    protected $attributes = [
        'processed' => false,
        'retry_count' => 0,
    ];

    /**
     * Get the table name from configuration.
     */
    public function getTable(): string
    {
        return config('mindbody.database.webhook_events_table', parent::getTable());
    }

    /**
     * Get the database connection from configuration.
     */
    public function getConnectionName(): ?string
    {
        return config('mindbody.database.connection') ?? parent::getConnectionName();
    }

    /**
     * Scope for unprocessed events.
     */
    public function scopeUnprocessed(Builder $query): Builder
    {
        return $query->where('processed', false);
    }

    /**
     * Scope for processed events.
     */
    public function scopeProcessed(Builder $query): Builder
    {
        return $query->where('processed', true);
    }

    /**
     * Scope for failed events (with errors).
     */
    public function scopeFailed(Builder $query): Builder
    {
        return $query->whereNotNull('error');
    }

    /**
     * Scope for successful events (processed without errors).
     */
    public function scopeSuccessful(Builder $query): Builder
    {
        return $query->where('processed', true)->whereNull('error');
    }

    /**
     * Scope for specific event type.
     */
    public function scopeOfType(Builder $query, string $type): Builder
    {
        return $query->where('event_type', $type);
    }

    /**
     * Scope for specific site.
     */
    public function scopeForSite(Builder $query, string $siteId): Builder
    {
        return $query->where('site_id', $siteId);
    }

    /**
     * Scope for events within date range.
     */
    public function scopeBetweenDates(Builder $query, Carbon $start, Carbon $end): Builder
    {
        return $query->whereBetween('event_timestamp', [$start, $end]);
    }

    /**
     * Scope for events that can be retried.
     */
    public function scopeRetryable(Builder $query, int $maxRetries = 3): Builder
    {
        return $query->unprocessed()
            ->where('retry_count', '<', $maxRetries);
    }

    /**
     * Mark event as processed successfully.
     */
    public function markAsProcessed(): bool
    {
        return $this->update([
            'processed' => true,
            'processed_at' => now(),
            'error' => null,
        ]);
    }

    /**
     * Mark event as failed with error message.
     */
    public function markAsFailed(string $error): bool
    {
        return $this->update([
            'processed' => false,
            'error' => $error,
            'retry_count' => $this->retry_count + 1,
        ]);
    }

    /**
     * Reset event for retry.
     */
    public function resetForRetry(): bool
    {
        return $this->update([
            'processed' => false,
            'error' => null,
            'retry_count' => $this->retry_count + 1,
        ]);
    }

    /**
     * Check if event can be retried.
     */
    public function canRetry(int $maxRetries = 3): bool
    {
        return ! $this->processed && $this->retry_count < $maxRetries;
    }

    /**
     * Check if event has exceeded max retries.
     */
    public function hasExceededMaxRetries(int $maxRetries = 3): bool
    {
        return $this->retry_count >= $maxRetries;
    }

    /**
     * Get event data as array.
     */
    public function getEventDataArray(): array
    {
        return $this->event_data?->toArray() ?? [];
    }

    /**
     * Get specific field from event data.
     */
    public function getEventDataField(string $key, mixed $default = null): mixed
    {
        return data_get($this->event_data, $key, $default);
    }

    /**
     * Check if event is of specific type.
     */
    public function isType(string $type): bool
    {
        return $this->event_type === $type;
    }

    /**
     * Check if event is for specific site.
     */
    public function isForSite(string $siteId): bool
    {
        return $this->site_id === $siteId;
    }

    /**
     * Get the age of the event in minutes.
     */
    public function getAgeInMinutes(): int
    {
        return $this->created_at->diffInMinutes(now());
    }

    /**
     * Check if event is stale (older than specified minutes).
     */
    public function isStale(int $minutes = 60): bool
    {
        return $this->getAgeInMinutes() > $minutes;
    }

    /**
     * Get formatted event summary.
     */
    public function getSummary(): array
    {
        return [
            'id' => $this->id,
            'event_id' => $this->event_id,
            'event_type' => $this->event_type,
            'site_id' => $this->site_id,
            'processed' => $this->processed,
            'retry_count' => $this->retry_count,
            'has_error' => $this->error !== null,
            'age_minutes' => $this->getAgeInMinutes(),
            'created_at' => $this->created_at->toIso8601String(),
        ];
    }

    /**
     * Create event from webhook payload.
     */
    public static function createFromWebhook(array $payload, ?string $signature = null, ?array $headers = null): static
    {
        return static::create([
            'event_id' => $payload['EventId'] ?? null,
            'event_type' => $payload['EventType'] ?? 'unknown',
            'site_id' => $payload['SiteId'] ?? null,
            'event_data' => collect($payload['EventData'] ?? []),
            'headers' => $headers ? collect($headers) : null,
            'event_timestamp' => isset($payload['EventTimestamp'])
                ? Carbon::parse($payload['EventTimestamp'])
                : now(),
            'signature' => $signature,
        ]);
    }

    /**
     * Get events that need processing.
     */
    public static function needingProcessing(int $maxRetries = 3): \Illuminate\Database\Eloquent\Collection
    {
        return static::retryable($maxRetries)
            ->orderBy('created_at')
            ->get();
    }

    /**
     * Get processing statistics.
     */
    public static function getProcessingStats(): array
    {
        $total = static::count();
        $processed = static::processed()->count();
        $failed = static::failed()->count();
        $pending = static::unprocessed()->count();

        return [
            'total' => $total,
            'processed' => $processed,
            'failed' => $failed,
            'pending' => $pending,
            'success_rate' => $total > 0 ? round(($processed / $total) * 100, 2) : 0,
        ];
    }

    /**
     * Clean up old processed events.
     */
    public static function cleanup(int $daysToKeep = 30): int
    {
        $cutoff = now()->subDays($daysToKeep);

        return static::processed()
            ->where('processed_at', '<', $cutoff)
            ->delete();
    }
}
