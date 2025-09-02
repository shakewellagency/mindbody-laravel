<?php

declare(strict_types=1);

namespace Shakewell\MindbodyLaravel\Facades;

use Illuminate\Support\Facades\Facade;
use Shakewell\MindbodyLaravel\Services\Api\AppointmentEndpoint;
use Shakewell\MindbodyLaravel\Services\Api\ClassEndpoint;
use Shakewell\MindbodyLaravel\Services\Api\ClientEndpoint;
use Shakewell\MindbodyLaravel\Services\Api\SaleEndpoint;
use Shakewell\MindbodyLaravel\Services\Api\SiteEndpoint;
use Shakewell\MindbodyLaravel\Services\Api\StaffEndpoint;
use Shakewell\MindbodyLaravel\Services\MindbodyClient;

/**
 * Facade for the Mindbody API client
 *
 * @method static MindbodyClient authenticate(string $username, string $password)
 * @method static string|null getUserToken()
 * @method static MindbodyClient clearUserToken()
 * @method static array get(string $endpoint, array $params = [])
 * @method static array post(string $endpoint, array $data = [])
 * @method static array put(string $endpoint, array $data = [])
 * @method static array delete(string $endpoint, array $params = [])
 * @method static array request(string $method, string $endpoint, array $options = [])
 * @method static bool clearCache(string $tag = null)
 * @method static mixed getConfig(string $key = null)
 * @method static bool testConnection()
 * @method static AppointmentEndpoint appointment()
 * @method static ClassEndpoint class()
 * @method static ClientEndpoint client()
 * @method static SaleEndpoint sale()
 * @method static SiteEndpoint site()
 * @method static StaffEndpoint staff()
 *
 * @see MindbodyClient
 */
class Mindbody extends Facade
{
    /**
     * Get the registered name of the component
     */
    protected static function getFacadeAccessor(): string
    {
        return MindbodyClient::class;
    }
}