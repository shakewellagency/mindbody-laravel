# Changelog

All notable changes to `mindbody-laravel` will be documented in this file.

## v1.0.0 - Initial Release - 2025-09-03

### MindbBody Laravel Package v1.0.0

#### Features

- Complete MindbBody Public API v6 integration
- Support for all major endpoints: Site, Staff, Classes, Appointments, Sales, Clients
- Laravel Sanctum authentication support
- Webhook handling with signature verification
- Comprehensive error handling and logging
- Configurable caching for performance optimization

#### API Endpoints

- **Site Information**: Current site details, locations, programs, session types
- **Staff Management**: All staff, active staff, individual staff members
- **Class Management**: Classes with filtering, class descriptions
- **Appointment Management**: Appointments with filtering, individual appointments
- **Sales & Transactions**: Sales data with date filtering
- **Client Management**: Client listing and individual client details

#### Webhooks

- Secure webhook endpoint with signature verification
- Event-driven architecture for real-time updates
- Configurable webhook subscriptions

#### Fixed Issues

- ✅ Fixed appointment endpoint URLs for MindbBody API v6 compatibility
- ✅ Fixed class descriptions data extraction
- ✅ Improved error handling and response validation

This is the first stable release of the shakewellagency/mindbody-laravel package.

## MindbBody Laravel Package v1.0.0 - 2025-09-03

### 🎉 MindbBody Laravel Package v1.0.0

First stable release of the comprehensive Laravel package for MindbBody Public API v6 integration.

#### ✨ Features

##### Complete API Coverage

- **Site API**: Business information, locations, programs, session types
- **Staff API**: Staff and instructor management with search capabilities
- **Class API**: Class schedules and descriptions with booking availability
- **Appointment API**: Personal training bookings and scheduling
- **Sale API**: Transaction history and revenue tracking
- **Client API**: Customer database and membership management

##### Laravel Integration

- Service provider with automatic discovery
- Facade for easy access (`Mindbody::site()->locations()`)
- Configuration file with environment variables
- Middleware for webhook signature verification
- Artisan commands for API testing and webhook management

##### Advanced Features

- **Token Management**: Automatic token refresh and caching
- **Rate Limiting**: Built-in API rate limiting compliance
- **Error Handling**: Comprehensive exception handling with detailed error messages
- **Webhooks**: Real-time event handling for appointments, clients, and more
- **Caching**: Intelligent caching for performance optimization
- **Validation**: Request validation and data sanitization

#### 🔧 Installation

```bash
composer require shakewellagency/mindbody-laravel
php artisan vendor:publish --provider="Shakewell\MindbodyLaravel\MindbodyLaravelServiceProvider"


```
#### 📊 API Status

All 6 MindbBody API endpoints tested and verified working:

- ✅ Site API (23 data points)
- ✅ Staff API (184 staff members)
- ✅ Class API (100+ classes and descriptions)
- ✅ Appointment API (fully functional)
- ✅ Sale API (732 transactions)
- ✅ Client API (customer management)

#### 🐛 Recent Fixes

- Fixed appointment endpoint URLs for MindbBody v6 API compatibility
- Corrected class descriptions data extraction method
- Improved error handling and validation across all endpoints
- Enhanced token management and authentication flow

#### 📚 Documentation

Complete documentation available in the repository README with examples, configuration options, and usage patterns.

#### 🧪 Testing

Comprehensive test suite with unit and integration tests covering all API endpoints and webhook functionality.

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
