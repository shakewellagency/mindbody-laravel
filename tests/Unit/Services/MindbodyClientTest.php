<?php

declare(strict_types=1);
namespace Shakewell\MindbodyLaravel\Tests\Unit\Services;

use GuzzleHttp\Client;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Psr7\Response;
use PHPUnit\Framework\TestCase;
use Shakewell\MindbodyLaravel\Exceptions\AuthenticationException;
use Shakewell\MindbodyLaravel\Exceptions\RateLimitException;
use Shakewell\MindbodyLaravel\Exceptions\ValidationException;
use Shakewell\MindbodyLaravel\Services\MindbodyClient;

/**
 * @internal
 *
 * @coversNothing
 */
final class MindbodyClientTest extends TestCase
{
    private MindbodyClient $client;
    private MockHandler $mockHandler;

    public function testItCanMakeSuccessfulGetRequest(): void
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

        self::assertIsArray($response);
        self::assertArrayHasKey('Clients', $response);
        self::assertSame('John', $response['Clients'][0]['FirstName']);
    }

    public function testItCanMakeSuccessfulPostRequest(): void
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

        self::assertIsArray($response);
        self::assertArrayHasKey('Client', $response);
        self::assertSame(123, $response['Client']['Id']);
    }

    public function testItHandlesAuthenticationErrors(): void
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

    public function testItHandlesRateLimitErrors(): void
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

    public function testItHandlesValidationErrors(): void
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

    public function testItCanAuthenticateUser(): void
    {
        $this->mockHandler->append(
            new Response(200, [], json_encode([
                'AccessToken' => 'test-access-token',
                'TokenType' => 'Bearer',
                'ExpiresIn' => 3600,
            ]))
        );

        $result = $this->client->authenticate('username', 'password');

        self::assertInstanceOf(MindbodyClient::class, $result);
        self::assertSame('test-access-token', $this->client->getUserToken());
    }

    public function testItCanClearUserToken(): void
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
        self::assertNotNull($this->client->getUserToken());

        // Clear the token
        $result = $this->client->clearUserToken();

        self::assertInstanceOf(MindbodyClient::class, $result);
        self::assertNull($this->client->getUserToken());
    }

    public function testItCanTestConnection(): void
    {
        $this->mockHandler->append(
            new Response(200, [], json_encode([
                'Locations' => [],
                'PaginationResponse' => ['TotalResults' => 0],
            ]))
        );

        $result = $this->client->testConnection();

        self::assertTrue($result);
    }

    public function testItReturnsFalseForFailedConnectionTest(): void
    {
        $this->mockHandler->append(
            new Response(500, [], json_encode([
                'Error' => ['Message' => 'Internal server error'],
            ]))
        );

        $result = $this->client->testConnection();

        self::assertFalse($result);
    }

    public function testItCanAccessEndpointServices(): void
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

    public function testItRetriesFailedRequests(): void
    {
        // First request fails, second succeeds
        $this->mockHandler->append(
            new Response(500, [], json_encode(['Error' => ['Message' => 'Server error']])),
            new Response(200, [], json_encode(['Clients' => []]))
        );

        $response = $this->client->get('client/clients');

        self::assertIsArray($response);
        self::assertArrayHasKey('Clients', $response);
    }

    public function testItIncludesRequiredHeaders(): void
    {
        $this->mockHandler->append(
            new Response(200, [], json_encode(['Clients' => []]))
        );

        $this->client->get('client/clients');

        $request = $this->mockHandler->getLastRequest();

        self::assertTrue($request->hasHeader('Api-Version'));
        self::assertTrue($request->hasHeader('SiteId'));
        self::assertTrue($request->hasHeader('User-Agent'));
        self::assertSame('v6', $request->getHeader('Api-Version')[0]);
        self::assertSame('test-site-id', $request->getHeader('SiteId')[0]);
    }

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
}
