# Changelog

All notable changes to `mindbody-laravel` will be documented in this file.

## 1.0.0 - 2024-03-01

### Added
- Initial release of Mindbody Laravel Package
- Complete API client implementation for Mindbody Public API v6
- All major endpoint support (Client, Class, Appointment, Sale, Staff, Site)
- OAuth authentication with automatic token management
- Comprehensive webhook system with signature verification
- Database migrations for webhook events and API tokens
- Eloquent models with proper relationships and scopes
- Laravel service provider with package registration
- Facade for fluent API access
- Comprehensive Artisan command suite:
  - `mindbody:test-connection` - Test API connectivity
  - `mindbody:subscribe-webhooks` - Subscribe to webhook events
  - `mindbody:unsubscribe-webhooks` - Unsubscribe from webhooks
  - `mindbody:list-webhooks` - List current subscriptions
  - `mindbody:sync-webhooks` - Synchronize with configuration
  - `mindbody:process-webhooks` - Process pending events
  - `mindbody:cleanup-webhooks` - Clean up old events
- Middleware for webhook signature verification
- Event system for webhook processing
- Comprehensive exception hierarchy for error handling
- Built-in caching with configurable TTL per endpoint
- Rate limiting support with per-endpoint configuration
- Retry mechanisms with exponential backoff
- Comprehensive test suite with PHPUnit
- Model factories for testing
- Complete documentation and usage examples

### Features
- 🚀 Complete API coverage for all major Mindbody endpoints
- 🔐 Secure OAuth authentication with token caching
- 🪝 Robust webhook handling with retry logic
- ⚡ Performance optimized with caching and pagination
- 🛡️ Security-first design with signature validation
- 🧪 Well-tested with comprehensive test coverage
- 📖 Full Laravel integration with facades and commands

### Security
- HMAC-SHA256 signature verification for webhooks
- Secure token management with automatic renewal
- Rate limiting to prevent API abuse
- Input validation and sanitization
- Comprehensive error handling without information leakage