<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Mindbody Public API Configuration
    |--------------------------------------------------------------------------
    |
    | Configuration for Mindbody Public API v6 integration
    |
    */

    'api' => [
        /*
        |--------------------------------------------------------------------------
        | API Base URL
        |--------------------------------------------------------------------------
        |
        | The base URL for Mindbody Public API v6
        |
        */
        'base_url' => env('MINDBODY_API_BASE_URL', 'https://api.mindbodyonline.com/public/v6'),

        /*
        |--------------------------------------------------------------------------
        | API Key
        |--------------------------------------------------------------------------
        |
        | Your Mindbody API key for authentication
        |
        */
        'api_key' => env('MINDBODY_API_KEY'),

        /*
        |--------------------------------------------------------------------------
        | Site ID
        |--------------------------------------------------------------------------
        |
        | Your Mindbody site ID (required for most API calls)
        |
        */
        'site_id' => env('MINDBODY_SITE_ID'),

        /*
        |--------------------------------------------------------------------------
        | Staff Credentials
        |--------------------------------------------------------------------------
        |
        | Staff username and password for authenticated endpoints
        | (required for endpoints that need user authentication)
        |
        */
        'staff_username' => env('MINDBODY_STAFF_USERNAME'),
        'staff_password' => env('MINDBODY_STAFF_PASSWORD'),

        /*
        |--------------------------------------------------------------------------
        | HTTP Client Settings
        |--------------------------------------------------------------------------
        */
        'timeout' => env('MINDBODY_API_TIMEOUT', 30),
        'connect_timeout' => env('MINDBODY_API_CONNECT_TIMEOUT', 10),
        'retry_times' => env('MINDBODY_API_RETRY_TIMES', 3),
        'retry_delay' => env('MINDBODY_API_RETRY_DELAY', 1000), // milliseconds

        /*
        |--------------------------------------------------------------------------
        | Rate Limiting
        |--------------------------------------------------------------------------
        |
        | Configure API rate limiting to stay within Mindbody's limits
        |
        */
        'rate_limit' => [
            'enabled' => env('MINDBODY_RATE_LIMIT_ENABLED', true),
            'max_requests_per_minute' => env('MINDBODY_MAX_REQUESTS_PER_MINUTE', 1000),
            'max_requests_per_day' => env('MINDBODY_MAX_REQUESTS_PER_DAY', 50000),
        ],

        /*
        |--------------------------------------------------------------------------
        | Default Request Parameters
        |--------------------------------------------------------------------------
        |
        | Default parameters to include with API requests
        |
        */
        'default_params' => [
            'limit' => env('MINDBODY_DEFAULT_LIMIT', 100),
            'offset' => env('MINDBODY_DEFAULT_OFFSET', 0),
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Webhooks Configuration
    |--------------------------------------------------------------------------
    |
    | Configuration for Mindbody Webhooks API
    |
    */

    'webhooks' => [
        /*
        |--------------------------------------------------------------------------
        | Webhooks API Settings
        |--------------------------------------------------------------------------
        */
        'enabled' => env('MINDBODY_WEBHOOKS_ENABLED', true),
        'base_url' => env('MINDBODY_WEBHOOKS_BASE_URL', 'https://api.mindbodyonline.com/push/api/v1'),
        'api_key' => env('MINDBODY_WEBHOOKS_API_KEY'),
        'signature_key' => env('MINDBODY_WEBHOOKS_SIGNATURE_KEY'),

        /*
        |--------------------------------------------------------------------------
        | Webhook Endpoint Configuration
        |--------------------------------------------------------------------------
        |
        | The URL where webhooks will be received
        |
        */
        'webhook_url' => env('MINDBODY_WEBHOOK_RECEIVER_URL'),
        'verify_signature' => env('MINDBODY_VERIFY_WEBHOOK_SIGNATURE', true),

        /*
        |--------------------------------------------------------------------------
        | Webhook Processing
        |--------------------------------------------------------------------------
        */
        'queue_webhooks' => env('MINDBODY_QUEUE_WEBHOOKS', true),
        'webhook_queue' => env('MINDBODY_WEBHOOK_QUEUE', 'default'),
        'webhook_connection' => env('MINDBODY_WEBHOOK_CONNECTION', 'database'),

        /*
        |--------------------------------------------------------------------------
        | Supported Webhook Events
        |--------------------------------------------------------------------------
        |
        | List of webhook events to subscribe to
        |
        */
        'events' => [
            // Appointment events
            'appointment.created',
            'appointment.updated',
            'appointment.cancelled',
            'appointmentAddOn.created',
            'appointmentAddOn.deleted',
            'appointmentBooking.updated',

            // Class events
            'class.created',
            'class.updated',
            'class.cancelled',
            'classBooking.created',
            'classBooking.cancelled',
            'classBooking.updated',

            // Client events
            'client.created',
            'client.updated',
            'client.deactivated',

            // Sale events
            'sale.created',
            'sale.updated',

            // Contract events
            'contract.created',
            'contract.updated',

            // Staff events
            'staff.created',
            'staff.updated',

            // Site events
            'site.updated',
        ],

        /*
        |--------------------------------------------------------------------------
        | Webhook Retry Configuration
        |--------------------------------------------------------------------------
        */
        'retry' => [
            'max_attempts' => env('MINDBODY_WEBHOOK_MAX_RETRY_ATTEMPTS', 3),
            'delay' => env('MINDBODY_WEBHOOK_RETRY_DELAY', 5), // seconds
            'exponential_backoff' => env('MINDBODY_WEBHOOK_EXPONENTIAL_BACKOFF', true),
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Caching Configuration
    |--------------------------------------------------------------------------
    |
    | Configure caching for API responses
    |
    */

    'cache' => [
        'enabled' => env('MINDBODY_CACHE_ENABLED', true),
        'store' => env('MINDBODY_CACHE_STORE'), // defaults to default cache store
        'ttl' => env('MINDBODY_CACHE_TTL', 3600), // 1 hour in seconds
        'prefix' => env('MINDBODY_CACHE_PREFIX', 'mindbody'),

        /*
        |--------------------------------------------------------------------------
        | Cache Tags
        |--------------------------------------------------------------------------
        |
        | Tags for cache invalidation (requires cache store that supports tagging)
        |
        */
        'tags' => [
            'clients' => 'mindbody_clients',
            'classes' => 'mindbody_classes',
            'appointments' => 'mindbody_appointments',
            'staff' => 'mindbody_staff',
            'sites' => 'mindbody_sites',
            'sales' => 'mindbody_sales',
        ],

        /*
        |--------------------------------------------------------------------------
        | Cache Strategy
        |--------------------------------------------------------------------------
        |
        | Different caching strategies for different types of data
        |
        */
        'strategies' => [
            'clients' => [
                'ttl' => 1800, // 30 minutes
                'cache_empty_results' => false,
            ],
            'classes' => [
                'ttl' => 300, // 5 minutes
                'cache_empty_results' => true,
            ],
            'appointments' => [
                'ttl' => 600, // 10 minutes
                'cache_empty_results' => false,
            ],
            'staff' => [
                'ttl' => 3600, // 1 hour
                'cache_empty_results' => true,
            ],
            'sites' => [
                'ttl' => 7200, // 2 hours
                'cache_empty_results' => true,
            ],
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Logging Configuration
    |--------------------------------------------------------------------------
    |
    | Configure logging for API requests and responses
    |
    */

    'logging' => [
        'enabled' => env('MINDBODY_LOGGING_ENABLED', true),
        'channel' => env('MINDBODY_LOG_CHANNEL', 'stack'),

        /*
        |--------------------------------------------------------------------------
        | Request/Response Logging
        |--------------------------------------------------------------------------
        */
        'log_requests' => env('MINDBODY_LOG_REQUESTS', false),
        'log_responses' => env('MINDBODY_LOG_RESPONSES', false),
        'log_headers' => env('MINDBODY_LOG_HEADERS', false),

        /*
        |--------------------------------------------------------------------------
        | Error Logging
        |--------------------------------------------------------------------------
        */
        'log_errors' => env('MINDBODY_LOG_ERRORS', true),
        'log_api_errors' => env('MINDBODY_LOG_API_ERRORS', true),
        'log_webhook_errors' => env('MINDBODY_LOG_WEBHOOK_ERRORS', true),

        /*
        |--------------------------------------------------------------------------
        | Performance Logging
        |--------------------------------------------------------------------------
        */
        'log_performance' => env('MINDBODY_LOG_PERFORMANCE', false),
        'slow_request_threshold' => env('MINDBODY_SLOW_REQUEST_THRESHOLD', 2000), // milliseconds
    ],

    /*
    |--------------------------------------------------------------------------
    | Database Configuration
    |--------------------------------------------------------------------------
    |
    | Database settings for storing webhook events and tokens
    |
    */

    'database' => [
        /*
        |--------------------------------------------------------------------------
        | Table Names
        |--------------------------------------------------------------------------
        */
        'webhook_events_table' => 'mindbody_webhook_events',
        'api_tokens_table' => 'mindbody_api_tokens',

        /*
        |--------------------------------------------------------------------------
        | Database Connection
        |--------------------------------------------------------------------------
        |
        | The database connection to use for Mindbody tables
        |
        */
        'connection' => env('MINDBODY_DB_CONNECTION'), // defaults to default connection

        /*
        |--------------------------------------------------------------------------
        | Cleanup Configuration
        |--------------------------------------------------------------------------
        */
        'cleanup' => [
            'enabled' => env('MINDBODY_CLEANUP_ENABLED', true),
            'webhook_events_retention_days' => env('MINDBODY_WEBHOOK_EVENTS_RETENTION_DAYS', 30),
            'failed_webhook_events_retention_days' => env('MINDBODY_FAILED_WEBHOOK_EVENTS_RETENTION_DAYS', 90),
            'api_tokens_retention_days' => env('MINDBODY_API_TOKENS_RETENTION_DAYS', 7),
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Development & Testing Configuration
    |--------------------------------------------------------------------------
    */

    'testing' => [
        /*
        |--------------------------------------------------------------------------
        | Mock API Responses
        |--------------------------------------------------------------------------
        |
        | Enable mocking for testing environments
        |
        */
        'mock_api' => env('MINDBODY_MOCK_API', false),
        'mock_webhooks' => env('MINDBODY_MOCK_WEBHOOKS', false),

        /*
        |--------------------------------------------------------------------------
        | Sandbox Configuration
        |--------------------------------------------------------------------------
        */
        'use_sandbox' => env('MINDBODY_USE_SANDBOX', false),
        'sandbox_base_url' => env('MINDBODY_SANDBOX_BASE_URL', 'https://api.mindbodyonline.com/public/v6'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Feature Flags
    |--------------------------------------------------------------------------
    |
    | Enable or disable specific features
    |
    */

    'features' => [
        'auto_pagination' => env('MINDBODY_AUTO_PAGINATION', true),
        'bulk_operations' => env('MINDBODY_BULK_OPERATIONS', true),
        'data_validation' => env('MINDBODY_DATA_VALIDATION', true),
        'response_transformation' => env('MINDBODY_RESPONSE_TRANSFORMATION', true),
    ],
];