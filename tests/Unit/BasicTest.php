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
    public function testItCanResolveMindbodyClientFromContainer()
    {
        $client = app(MindbodyClient::class);

        self::assertInstanceOf(MindbodyClient::class, $client);
    }

    public function testItCanAccessFacade()
    {
        self::assertTrue(class_exists(Mindbody::class));
    }

    public function testItLoadsConfiguration()
    {
        self::assertNotNull(config('mindbody.api.base_url'));
        self::assertNotNull(config('mindbody.api.site_id'));
    }

    public function testItRegistersServiceProvider()
    {
        $providers = app()->getLoadedProviders();

        self::assertArrayHasKey('Shakewell\MindbodyLaravel\MindbodyLaravelServiceProvider', $providers);
    }
}
