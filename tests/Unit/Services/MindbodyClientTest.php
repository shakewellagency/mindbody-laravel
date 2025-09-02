<?php

declare(strict_types=1);

namespace Shakewell\MindbodyLaravel\Tests\Unit\Services;

use GuzzleHttp\Client;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Psr7\Response;
use PHPUnit\Framework\TestCase;
use Shakewell\MindbodyLaravel\Services\MindbodyClient;
use Shakewell\MindbodyLaravel\Services\Authentication\TokenManager;
use Shakewell\MindbodyLaravel\Exceptions\AuthenticationException;
use Shakewell\MindbodyLaravel\Exceptions\RateLimitException;
use Shakewell\MindbodyLaravel\Exceptions\ValidationException;
use Illuminate\Support\Facades\Cache;

class MindbodyClientTest extends TestCase
{
    private MindbodyClient $client;
    private MockHandler $mockHandler;

    protected function setUp(): void
    {
        parent::setUp();

        $this->mockHandler = new MockHandler();
        $httpClient = new Client(['handler' => HandlerStack::create($this->mockHandler)]);
        
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

        $this->client = new MindbodyClient($config, $httpClient);
    }

    /** @test */
    public function it_can_make_successful_get_request(): void
    {
        $this->mockHandler->append(
            new Response(200, [], json_encode([
                'Clients' => [
                    ['Id' => 1, 'FirstName' => 'John', 'LastName' => 'Doe'],
                ],
                'PaginationResponse' => ['TotalResults' => 1],
            ]))
        );

        $response = $this->client->get('client/clients');

        $this->assertIsArray($response);
        $this->assertArrayHasKey('Clients', $response);
        $this->assertEquals('John', $response['Clients'][0]['FirstName']);
    }

    /** @test */
    public function it_can_make_successful_post_request(): void
    {
        $this->mockHandler->append(
            new Response(200, [], json_encode([
                'Client' => ['Id' => 123, 'FirstName' => 'Jane', 'LastName' => 'Smith'],
            ]))
        );

        $data = [
            'FirstName' => 'Jane',
            'LastName' => 'Smith',
            'Email' => 'jane@example.com',
        ];

        $response = $this->client->post('client/addclient', $data);

        $this->assertIsArray($response);
        $this->assertArrayHasKey('Client', $response);
        $this->assertEquals(123, $response['Client']['Id']);
    }

    /** @test */
    public function it_handles_authentication_errors(): void
    {
        $this->mockHandler->append(
            new Response(401, [], json_encode([
                'Error' => [
                    'Message' => 'Invalid credentials',
                    'Code' => 'InvalidCredentials',
                ],
            ]))
        );

        $this->expectException(AuthenticationException::class);
        $this->expectExceptionMessage('Invalid credentials');

        $this->client->get('client/clients');
    }

    /** @test */
    public function it_handles_rate_limit_errors(): void
    {
        $this->mockHandler->append(
            new Response(429, ['Retry-After' => '60'], json_encode([
                'Error' => [
                    'Message' => 'Rate limit exceeded',
                    'Code' => 'RateLimitExceeded',
                ],
            ]))
        );

        $this->expectException(RateLimitException::class);
        $this->expectExceptionMessage('Rate limit exceeded');

        $this->client->get('client/clients');
    }

    /** @test */
    public function it_handles_validation_errors(): void
    {
        $this->mockHandler->append(
            new Response(400, [], json_encode([
                'Error' => [
                    'Message' => 'Validation failed',
                    'Code' => 'InvalidRequest',
                ],
            ]))
        );

        $this->expectException(ValidationException::class);
        $this->expectExceptionMessage('Validation failed');

        $this->client->post('client/addclient', []);
    }

    /** @test */
    public function it_can_authenticate_user(): void
    {
        $this->mockHandler->append(
            new Response(200, [], json_encode([
                'AccessToken' => 'test-access-token',
                'TokenType' => 'Bearer',
                'ExpiresIn' => 3600,
            ]))
        );

        $result = $this->client->authenticate('username', 'password');

        $this->assertInstanceOf(MindbodyClient::class, $result);
        $this->assertEquals('test-access-token', $this->client->getUserToken());
    }

    /** @test */
    public function it_can_clear_user_token(): void
    {
        // First set a token
        $this->mockHandler->append(
            new Response(200, [], json_encode([
                'AccessToken' => 'test-access-token',
                'TokenType' => 'Bearer',
                'ExpiresIn' => 3600,
            ]))
        );

        $this->client->authenticate('username', 'password');
        $this->assertNotNull($this->client->getUserToken());

        // Clear the token
        $result = $this->client->clearUserToken();

        $this->assertInstanceOf(MindbodyClient::class, $result);
        $this->assertNull($this->client->getUserToken());
    }

    /** @test */
    public function it_can_test_connection(): void
    {
        $this->mockHandler->append(
            new Response(200, [], json_encode([
                'Locations' => [],
                'PaginationResponse' => ['TotalResults' => 0],
            ]))
        );

        $result = $this->client->testConnection();

        $this->assertTrue($result);
    }

    /** @test */
    public function it_returns_false_for_failed_connection_test(): void
    {
        $this->mockHandler->append(
            new Response(500, [], json_encode([
                'Error' => ['Message' => 'Internal server error'],
            ]))
        );

        $result = $this->client->testConnection();

        $this->assertFalse($result);
    }

    /** @test */
    public function it_can_access_endpoint_services(): void
    {
        $client = $this->client->client();
        $this->assertInstanceOf(\Shakewell\MindbodyLaravel\Services\Api\ClientEndpoint::class, $client);

        $class = $this->client->class();
        $this->assertInstanceOf(\Shakewell\MindbodyLaravel\Services\Api\ClassEndpoint::class, $class);

        $appointment = $this->client->appointment();
        $this->assertInstanceOf(\Shakewell\MindbodyLaravel\Services\Api\AppointmentEndpoint::class, $appointment);

        $sale = $this->client->sale();
        $this->assertInstanceOf(\Shakewell\MindbodyLaravel\Services\Api\SaleEndpoint::class, $sale);

        $staff = $this->client->staff();
        $this->assertInstanceOf(\Shakewell\MindbodyLaravel\Services\Api\StaffEndpoint::class, $staff);

        $site = $this->client->site();
        $this->assertInstanceOf(\Shakewell\MindbodyLaravel\Services\Api\SiteEndpoint::class, $site);
    }

    /** @test */
    public function it_retries_failed_requests(): void
    {
        // First request fails, second succeeds
        $this->mockHandler->append(
            new Response(500, [], json_encode(['Error' => ['Message' => 'Server error']])),
            new Response(200, [], json_encode(['Clients' => []]))
        );

        $response = $this->client->get('client/clients');

        $this->assertIsArray($response);
        $this->assertArrayHasKey('Clients', $response);
    }

    /** @test */
    public function it_includes_required_headers(): void
    {
        $this->mockHandler->append(
            new Response(200, [], json_encode(['Clients' => []]))
        );

        $this->client->get('client/clients');

        $request = $this->mockHandler->getLastRequest();
        
        $this->assertTrue($request->hasHeader('Api-Version'));
        $this->assertTrue($request->hasHeader('SiteId'));
        $this->assertTrue($request->hasHeader('User-Agent'));
        $this->assertEquals('v6', $request->getHeader('Api-Version')[0]);
        $this->assertEquals('test-site-id', $request->getHeader('SiteId')[0]);
    }
}