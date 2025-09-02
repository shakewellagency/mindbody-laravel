# Mindbody Laravel Package

[![Latest Version on Packagist](https://img.shields.io/packagist/v/shakewell/mindbody-laravel.svg?style=flat-square)](https://packagist.org/packages/shakewell/mindbody-laravel)
[![GitHub Tests Action Status](https://img.shields.io/github/actions/workflow/status/shakewell/mindbody-laravel/run-tests.yml?branch=main&label=tests&style=flat-square)](https://github.com/shakewell/mindbody-laravel/actions?query=workflow%3Arun-tests+branch%3Amain)
[![GitHub Code Style Action Status](https://img.shields.io/github/actions/workflow/status/shakewell/mindbody-laravel/fix-php-code-style-issues.yml?branch=main&label=code%20style&style=flat-square)](https://github.com/shakewell/mindbody-laravel/actions?query=workflow%3A"Fix+PHP+code+style+issues"+branch%3Amain)
[![Total Downloads](https://img.shields.io/packagist/dt/shakewell/mindbody-laravel.svg?style=flat-square)](https://packagist.org/packages/shakewell/mindbody-laravel)

A comprehensive Laravel package for integrating with the Mindbody Public API v6 and handling webhooks. This package provides a fluent API for all major Mindbody endpoints and a robust webhook processing system.

## Features

- 🚀 **Complete API Coverage** - All major Mindbody API v6 endpoints (Client, Class, Appointment, Sale, Staff, Site)
- 🔐 **OAuth Authentication** - Automatic token management with caching and renewal
- 🪝 **Webhook System** - Secure webhook handling with signature verification and retry logic
- ⚡ **Performance Optimized** - Built-in caching, pagination, and retry mechanisms
- 🛡️ **Security First** - HMAC signature validation, rate limiting, and comprehensive error handling
- 🧪 **Well Tested** - Comprehensive test suite with high coverage
- 📖 **Laravel Integration** - Facades, Artisan commands, migrations, and service providers

## Installation

Install the package via composer:

```bash
composer require shakewell/mindbody-laravel
```

Publish and run the migrations:

```bash
php artisan vendor:publish --tag="mindbody-laravel-migrations"
php artisan migrate
```

Optionally publish the config file:

```bash
php artisan vendor:publish --tag="mindbody-laravel-config"
```

## Configuration

Add your Mindbody credentials to your `.env` file:

```env
MINDBODY_API_KEY=your-api-key
MINDBODY_SOURCE_NAME=your-source-name
MINDBODY_SITE_ID=your-site-id
MINDBODY_USERNAME=your-username
MINDBODY_PASSWORD=your-password

# Webhook Configuration
MINDBODY_WEBHOOK_URL=https://yourdomain.com/mindbody/webhooks
MINDBODY_WEBHOOK_SIGNATURE_KEY=your-webhook-secret
```

## Quick Start

### API Usage

```php
use Shakewell\MindbodyLaravel\Facades\Mindbody;

// Authenticate with username/password
Mindbody::authenticate('username', 'password');

// Get all clients
$clients = Mindbody::client()->getAll();

// Search for clients
$clients = Mindbody::client()->search([
    'SearchText' => 'John Doe',
    'Limit' => 25
]);

// Create a new client
$newClient = Mindbody::client()->create([
    'FirstName' => 'John',
    'LastName' => 'Doe',
    'Email' => 'john@example.com'
]);

// Book an appointment
$appointment = Mindbody::appointment()->book([
    'ClientId' => 'client-123',
    'SessionTypeId' => 1,
    'StartDateTime' => '2024-03-15T10:00:00',
    'StaffId' => 1
]);
```

### Available Endpoints

#### Client Management
```php
// Get clients
$clients = Mindbody::client()->getAll($params);
$client = Mindbody::client()->getById('client-123');

// Search and filter
$clients = Mindbody::client()->search(['SearchText' => 'John']);
$activeClients = Mindbody::client()->getActive($params);

// Client operations
$client = Mindbody::client()->create($clientData);
$client = Mindbody::client()->update('client-123', $updateData);

// Bulk operations
$results = Mindbody::client()->bulkCreate($clientsData);
```

#### Class Management
```php
// Get classes
$classes = Mindbody::class()->getAll($params);
$class = Mindbody::class()->getById(123);

// Search classes
$classes = Mindbody::class()->search([
    'StartDateTime' => '2024-03-15',
    'EndDateTime' => '2024-03-16'
]);

// Booking operations
$booking = Mindbody::class()->book('client-123', 456);
$result = Mindbody::class()->cancel('client-123', 456);

// Waitlist management
$result = Mindbody::class()->addToWaitlist('client-123', 456);
```

#### Appointment Management
```php
// Get appointments
$appointments = Mindbody::appointment()->getAll($params);
$appointment = Mindbody::appointment()->getById(123);

// Book appointments
$appointment = Mindbody::appointment()->book($appointmentData);
$appointment = Mindbody::appointment()->reschedule(123, $newDateTime);
$result = Mindbody::appointment()->cancel(123);

// Staff availability
$availability = Mindbody::appointment()->getAvailability([
    'StartDateTime' => '2024-03-15',
    'EndDateTime' => '2024-03-16',
    'StaffIds' => [1, 2, 3]
]);
```

#### Sales Operations
```php
// Process sales
$sale = Mindbody::sale()->create($saleData);
$result = Mindbody::sale()->processPayment($paymentData);

// Get transactions
$sales = Mindbody::sale()->getAll($params);
$sale = Mindbody::sale()->getById(123);

// Refunds and adjustments
$refund = Mindbody::sale()->refund(123, $refundData);
$adjustment = Mindbody::sale()->adjust(123, $adjustmentData);
```

#### Staff Management
```php
// Get staff
$staff = Mindbody::staff()->getAll($params);
$member = Mindbody::staff()->getById(123);

// Staff scheduling
$schedule = Mindbody::staff()->getSchedule(123, $params);
$availability = Mindbody::staff()->getAvailability(123, $params);

// Permissions and assignments
$permissions = Mindbody::staff()->getPermissions(123);
$clients = Mindbody::staff()->getAssignedClients(123);
```

#### Site Information
```php
// Get site data
$locations = Mindbody::site()->getLocations();
$services = Mindbody::site()->getServices();
$programs = Mindbody::site()->getPrograms();

// Business information
$hours = Mindbody::site()->getBusinessHours();
$info = Mindbody::site()->getInfo();
```

## Webhook Handling

### Setting Up Webhooks

1. Configure your webhook endpoint in the config:

```php
// config/mindbody.php
'webhooks' => [
    'enabled' => true,
    'url' => env('MINDBODY_WEBHOOK_URL'),
    'signature_key' => env('MINDBODY_WEBHOOK_SIGNATURE_KEY'),
    'events' => [
        'appointment.booked',
        'appointment.cancelled',
        'client.created',
        'class.booked',
    ],
],
```

2. Subscribe to webhooks:

```bash
php artisan mindbody:subscribe-webhooks --all
# or specific events
php artisan mindbody:subscribe-webhooks --event=appointment.booked --event=client.created
```

### Listening to Webhook Events

Create event listeners in your application:

```php
// app/Listeners/HandleAppointmentBooked.php
use Shakewell\MindbodyLaravel\Events\Webhooks\AppointmentBooked;

class HandleAppointmentBooked
{
    public function handle(AppointmentBooked $event): void
    {
        $appointment = $event->getAppointment();
        $clientId = $event->getClientId();
        $startTime = $event->getStartTime();
        
        // Your business logic here
        Log::info('Appointment booked', [
            'appointment_id' => $appointment['Id'],
            'client_id' => $clientId,
            'start_time' => $startTime,
        ]);
    }
}
```

Register the listener in your `EventServiceProvider`:

```php
use Shakewell\MindbodyLaravel\Events\Webhooks\AppointmentBooked;

protected $listen = [
    AppointmentBooked::class => [
        HandleAppointmentBooked::class,
    ],
];
```

### Processing Webhook Events

The package automatically processes webhooks, but you can also manually process them:

```bash
# Process pending webhook events
php artisan mindbody:process-webhooks

# Process with options
php artisan mindbody:process-webhooks --limit=50 --retry-failed
```

## Artisan Commands

### API Management
```bash
# Test API connection
php artisan mindbody:test-connection

# Test with custom credentials
php artisan mindbody:test-connection --username=test --password=secret
```

### Webhook Management
```bash
# List current subscriptions
php artisan mindbody:list-webhooks

# Subscribe to events
php artisan mindbody:subscribe-webhooks --all
php artisan mindbody:subscribe-webhooks --event=appointment.booked

# Unsubscribe from events
php artisan mindbody:unsubscribe-webhooks --id=subscription-123
php artisan mindbody:unsubscribe-webhooks --event=appointment.booked

# Sync subscriptions with config
php artisan mindbody:sync-webhooks

# Process pending webhooks
php artisan mindbody:process-webhooks --limit=100

# Clean up old webhook events
php artisan mindbody:cleanup-webhooks --days=30 --status=processed
```

## Advanced Usage

### Custom HTTP Client Configuration

```php
use Shakewell\MindbodyLaravel\Services\MindbodyClient;
use GuzzleHttp\Client;

$httpClient = new Client([
    'timeout' => 60,
    'connect_timeout' => 10,
]);

$mindbody = new MindbodyClient(config('mindbody'), $httpClient);
```

### Caching Configuration

The package supports multiple caching strategies:

```php
// config/mindbody.php
'caching' => [
    'enabled' => true,
    'default_ttl' => 3600, // 1 hour
    'per_endpoint' => [
        'clients' => 1800,      // 30 minutes
        'staff' => 7200,        // 2 hours
        'locations' => 86400,   // 24 hours
    ],
    'cache_driver' => 'redis', // or 'file', 'database'
    'cache_prefix' => 'mindbody',
],
```

### Error Handling

```php
use Shakewell\MindbodyLaravel\Exceptions\AuthenticationException;
use Shakewell\MindbodyLaravel\Exceptions\RateLimitException;
use Shakewell\MindbodyLaravel\Exceptions\ValidationException;

try {
    $clients = Mindbody::client()->getAll();
} catch (AuthenticationException $e) {
    // Handle authentication errors
    Log::error('Authentication failed: ' . $e->getMessage());
} catch (RateLimitException $e) {
    // Handle rate limiting
    $retryAfter = $e->getRetryAfter();
    Log::warning("Rate limited. Retry after {$retryAfter} seconds");
} catch (ValidationException $e) {
    // Handle validation errors
    $errors = $e->getValidationErrors();
    Log::error('Validation failed', $errors);
}
```

### Custom Event Processing

```php
use Shakewell\MindbodyLaravel\Services\Webhooks\WebhookHandler;

class CustomWebhookHandler extends WebhookHandler
{
    protected function processEvent(WebhookEvent $event): void
    {
        // Custom processing logic
        match ($event->event_type) {
            'appointment.booked' => $this->handleAppointmentBooked($event),
            'client.created' => $this->handleClientCreated($event),
            default => parent::processEvent($event),
        };
    }
    
    private function handleAppointmentBooked(WebhookEvent $event): void
    {
        // Custom appointment booking logic
    }
}
```

## Testing

Run the tests with:

```bash
composer test
```

Run tests with coverage:

```bash
composer test:coverage
```

## Configuration Reference

<details>
<summary>Complete configuration options</summary>

```php
return [
    // API Configuration
    'api' => [
        'base_url' => env('MINDBODY_API_BASE_URL', 'https://api.mindbodyonline.com'),
        'version' => env('MINDBODY_API_VERSION', 'v6'),
        'site_id' => env('MINDBODY_SITE_ID'),
        'source_name' => env('MINDBODY_SOURCE_NAME'),
        'api_key' => env('MINDBODY_API_KEY'),
        'timeout' => env('MINDBODY_API_TIMEOUT', 30),
        'retry_attempts' => env('MINDBODY_API_RETRY_ATTEMPTS', 3),
        'retry_delay' => env('MINDBODY_API_RETRY_DELAY', 1000),
    ],

    // Authentication
    'auth' => [
        'username' => env('MINDBODY_USERNAME'),
        'password' => env('MINDBODY_PASSWORD'),
        'token_cache_key' => 'mindbody_user_token',
        'token_ttl' => 3600,
    ],

    // Webhooks
    'webhooks' => [
        'enabled' => env('MINDBODY_WEBHOOKS_ENABLED', true),
        'url' => env('MINDBODY_WEBHOOK_URL'),
        'signature_key' => env('MINDBODY_WEBHOOK_SIGNATURE_KEY'),
        'verify_signature' => env('MINDBODY_WEBHOOK_VERIFY_SIGNATURE', true),
        'route_prefix' => 'mindbody/webhooks',
        'use_queue' => env('MINDBODY_WEBHOOK_USE_QUEUE', false),
        'queue_name' => env('MINDBODY_WEBHOOK_QUEUE', 'default'),
        'events' => [
            'appointment.booked',
            'appointment.cancelled',
            'appointment.completed',
            'client.created',
            'client.updated',
            'class.booked',
            'class.cancelled',
        ],
        'enable_test_endpoint' => env('MINDBODY_WEBHOOK_ENABLE_TEST', false),
        'test_allowed_ips' => ['127.0.0.1', '::1'],
        'expose_stats' => env('MINDBODY_WEBHOOK_EXPOSE_STATS', false),
    ],

    // Caching
    'caching' => [
        'enabled' => env('MINDBODY_CACHE_ENABLED', true),
        'default_ttl' => env('MINDBODY_CACHE_TTL', 3600),
        'cache_driver' => env('MINDBODY_CACHE_DRIVER', 'default'),
        'cache_prefix' => env('MINDBODY_CACHE_PREFIX', 'mindbody'),
    ],

    // Logging
    'logging' => [
        'enabled' => env('MINDBODY_LOGGING_ENABLED', true),
        'channel' => env('MINDBODY_LOG_CHANNEL', 'stack'),
        'level' => env('MINDBODY_LOG_LEVEL', 'info'),
    ],

    // Rate Limiting
    'rate_limiting' => [
        'enabled' => true,
        'max_attempts' => 1000,
        'decay_minutes' => 60,
        'by_endpoint' => [
            'clients' => ['max_attempts' => 500, 'decay_minutes' => 60],
            'appointments' => ['max_attempts' => 200, 'decay_minutes' => 60],
        ],
    ],
];
```
</details>

## Changelog

Please see [CHANGELOG](CHANGELOG.md) for more information on what has changed recently.

## Contributing

Please see [CONTRIBUTING](CONTRIBUTING.md) for details.

## Security Vulnerabilities

Please review [our security policy](../../security/policy) on how to report security vulnerabilities.

## Credits

- [Shakewell](https://github.com/shakewell)
- [All Contributors](../../contributors)

## License

The MIT License (MIT). Please see [License File](LICENSE.md) for more information.