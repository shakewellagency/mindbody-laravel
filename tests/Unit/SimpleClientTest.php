<?php

declare(strict_types=1);

namespace Shakewell\MindbodyLaravel\Tests\Unit;

use PHPUnit\Framework\TestCase;
use Shakewell\MindbodyLaravel\Exceptions\AuthenticationException;
use Shakewell\MindbodyLaravel\Exceptions\RateLimitException;
use Shakewell\MindbodyLaravel\Exceptions\WebhookValidationException;
use Shakewell\MindbodyLaravel\Services\MindbodyClient;

final class SimpleClientTest extends TestCase
{
    private MindbodyClient $client;

    public function testItCanCreateClient(): void
    {
        self::assertInstanceOf(MindbodyClient::class, $this->client);
    }

    public function testItHasRequiredEndpoints(): void
    {
        $client = $this->client->client();
        self::assertInstanceOf(\Shakewell\MindbodyLaravel\Services\Api\ClientEndpoint::class, $client);

        $class = $this->client->class();
        self::assertInstanceOf(\Shakewell\MindbodyLaravel\Services\Api\ClassEndpoint::class, $class);

        $appointment = $this->client->appointment();
        self::assertInstanceOf(\Shakewell\MindbodyLaravel\Services\Api\AppointmentEndpoint::class, $appointment);

        $sale = $this->client->sale();
        self::assertInstanceOf(\Shakewell\MindbodyLaravel\Services\Api\SaleEndpoint::class, $sale);

        $staff = $this->client->staff();
        self::assertInstanceOf(\Shakewell\MindbodyLaravel\Services\Api\StaffEndpoint::class, $staff);

        $site = $this->client->site();
        self::assertInstanceOf(\Shakewell\MindbodyLaravel\Services\Api\SiteEndpoint::class, $site);
    }

    public function testItHasExceptionClasses(): void
    {
        self::assertTrue(class_exists(AuthenticationException::class));
        self::assertTrue(class_exists(RateLimitException::class));
        self::assertTrue(class_exists(WebhookValidationException::class));
    }

    public function testItHasRequiredMethods(): void
    {
        self::assertTrue(method_exists($this->client, 'authenticate'));
        self::assertTrue(method_exists($this->client, 'get'));
        self::assertTrue(method_exists($this->client, 'post'));
        self::assertTrue(method_exists($this->client, 'put'));
        self::assertTrue(method_exists($this->client, 'delete'));
    }

    protected function setUp(): void
    {
        parent::setUp();

        $config = [
            'api' => [
                'base_url' => 'https://api.mindbodyonline.com',
                'version' => 'v6',
                'site_id' => 'test-site-id',
                'source_name' => 'Test Source',
                'timeout' => 30,
                'retry_attempts' => 3,
                'retry_delay' => 1000,
            ],
            'auth' => [
                'username' => 'test-username',
                'password' => 'test-password',
            ],
            'caching' => [
                'enabled' => false,
            ],
            'logging' => [
                'enabled' => false,
            ],
        ];

        $this->client = new MindbodyClient($config);
    }
}