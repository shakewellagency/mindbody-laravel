<?php

declare(strict_types=1);

namespace Shakewell\MindbodyLaravel\Tests\Unit;

use Shakewell\MindbodyLaravel\Tests\TestCase;
use Shakewell\MindbodyLaravel\Services\MindbodyClient;
use Shakewell\MindbodyLaravel\Facades\Mindbody;

class BasicTest extends TestCase
{
    /** @test */
    public function it_can_resolve_mindbody_client_from_container()
    {
        $client = app(MindbodyClient::class);
        
        $this->assertInstanceOf(MindbodyClient::class, $client);
    }

    /** @test */
    public function it_can_access_facade()
    {
        $this->assertTrue(class_exists(\Shakewell\MindbodyLaravel\Facades\Mindbody::class));
    }

    /** @test */
    public function it_loads_configuration()
    {
        $this->assertNotNull(config('mindbody.api.base_url'));
        $this->assertNotNull(config('mindbody.api.site_id'));
    }

    /** @test */
    public function it_registers_service_provider()
    {
        $providers = app()->getLoadedProviders();
        
        $this->assertArrayHasKey('Shakewell\\MindbodyLaravel\\MindbodyLaravelServiceProvider', $providers);
    }
}