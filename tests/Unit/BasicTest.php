<?php

declare(strict_types=1);

namespace Shakewell\MindbodyLaravel\Tests\Unit;

use Shakewell\MindbodyLaravel\Facades\Mindbody;
use Shakewell\MindbodyLaravel\Services\MindbodyClient;
use Shakewell\MindbodyLaravel\Tests\TestCase;

/**
 * @internal
 *
 * @coversNothing
 */
final class BasicTest extends TestCase
{
    public function test_it_can_resolve_mindbody_client_from_container()
    {
        $client = app(MindbodyClient::class);

        self::assertInstanceOf(MindbodyClient::class, $client);
    }

    public function test_it_can_access_facade()
    {
        self::assertTrue(class_exists(Mindbody::class));
    }

    public function test_it_loads_configuration()
    {
        self::assertNotNull(config('mindbody.api.base_url'));
        self::assertNotNull(config('mindbody.api.site_id'));
    }

    public function test_it_registers_service_provider()
    {
        $providers = app()->getLoadedProviders();

        self::assertArrayHasKey('Shakewell\MindbodyLaravel\MindbodyLaravelServiceProvider', $providers);
    }
}
