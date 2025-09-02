<?php

declare(strict_types=1);
namespace Shakewell\MindbodyLaravel\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/**
 * API token model for caching authentication tokens.
 *
 * @property int    $id
 * @property string $username
 * @property string $access_token
 * @property string $token_type
 * @property int    $expires_in
 * @property Carbon $issued_at
 * @property Carbon $expires_at
 * @property bool   $revoked
 * @property Carbon $created_at
 * @property Carbon $updated_at
 */
class ApiToken extends Model
{
    protected $table = 'mindbody_api_tokens';

    protected $fillable = [
        'username',
        'access_token',
        'token_type',
        'expires_in',
        'issued_at',
        'expires_at',
        'revoked',
    ];

    protected $casts = [
        'expires_in' => 'integer',
        'issued_at' => 'datetime',
        'expires_at' => 'datetime',
        'revoked' => 'boolean',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    protected $attributes = [
        'token_type' => 'Bearer',
        'revoked' => false,
    ];

    /**
     * Get the table name from configuration.
     */
    public function getTable(): string
    {
        return config('mindbody.database.api_tokens_table', parent::getTable());
    }

    /**
     * Get the database connection from configuration.
     */
    public function getConnectionName(): ?string
    {
        return config('mindbody.database.connection') ?? parent::getConnectionName();
    }

    /**
     * Scope for valid (non-revoked, non-expired) tokens.
     */
    public function scopeValid(Builder $query): Builder
    {
        return $query->where('revoked', false)
            ->where('expires_at', '>', now());
    }

    /**
     * Scope for expired tokens.
     */
    public function scopeExpired(Builder $query): Builder
    {
        return $query->where('expires_at', '<=', now());
    }

    /**
     * Scope for revoked tokens.
     */
    public function scopeRevoked(Builder $query): Builder
    {
        return $query->where('revoked', true);
    }

    /**
     * Scope for specific username.
     */
    public function scopeForUsername(Builder $query, string $username): Builder
    {
        return $query->where('username', $username);
    }

    /**
     * Check if token is valid (not expired and not revoked).
     */
    public function isValid(): bool
    {
        return ! $this->revoked && $this->expires_at->isFuture();
    }

    /**
     * Check if token is expired.
     */
    public function isExpired(): bool
    {
        return $this->expires_at->isPast();
    }

    /**
     * Check if token is revoked.
     */
    public function isRevoked(): bool
    {
        return $this->revoked;
    }

    /**
     * Revoke the token.
     */
    public function revoke(): bool
    {
        return $this->update(['revoked' => true]);
    }

    /**
     * Get remaining validity time in seconds.
     */
    public function getRemainingSeconds(): int
    {
        if ($this->isExpired()) {
            return 0;
        }

        return $this->expires_at->diffInSeconds(now());
    }

    /**
     * Check if token expires within specified minutes.
     */
    public function expiresWithin(int $minutes): bool
    {
        return $this->expires_at->isBefore(now()->addMinutes($minutes));
    }

    /**
     * Create token from API response.
     */
    public static function createFromApiResponse(
        string $username,
        array $tokenData
    ): static {
        $issuedAt = isset($tokenData['issued_at'])
            ? Carbon::createFromTimestamp($tokenData['issued_at'])
            : now();

        $expiresIn = $tokenData['expires_in'] ?? 3600;
        $expiresAt = $issuedAt->copy()->addSeconds($expiresIn);

        return static::create([
            'username' => $username,
            'access_token' => $tokenData['access_token'],
            'token_type' => $tokenData['token_type'] ?? 'Bearer',
            'expires_in' => $expiresIn,
            'issued_at' => $issuedAt,
            'expires_at' => $expiresAt,
        ]);
    }

    /**
     * Find valid token for username.
     */
    public static function findValidForUsername(string $username): ?static
    {
        return static::forUsername($username)
            ->valid()
            ->orderByDesc('created_at')
            ->first();
    }

    /**
     * Clean up expired and revoked tokens.
     */
    public static function cleanup(): int
    {
        $retentionDays = config('mindbody.database.cleanup.api_tokens_retention_days', 7);
        $cutoff = now()->subDays($retentionDays);

        return static::where(static function (Builder $query) use ($cutoff) {
            $query->where('revoked', true)
                ->orWhere('expires_at', '<', $cutoff);
        })->delete();
    }

    /**
     * Revoke all tokens for username.
     */
    public static function revokeAllForUsername(string $username): int
    {
        return static::forUsername($username)
            ->valid()
            ->update(['revoked' => true]);
    }

    /**
     * Get token statistics.
     */
    public static function getStats(): array
    {
        $total = static::count();
        $valid = static::valid()->count();
        $expired = static::expired()->count();
        $revoked = static::revoked()->count();

        return [
            'total' => $total,
            'valid' => $valid,
            'expired' => $expired,
            'revoked' => $revoked,
            'expiring_soon' => static::valid()
                ->where('expires_at', '<', now()->addHour())
                ->count(),
        ];
    }
}
