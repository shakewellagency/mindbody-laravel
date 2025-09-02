<?php

declare(strict_types=1);
namespace Shakewell\MindbodyLaravel;

use Illuminate\Support\Facades\Route;
use Shakewell\MindbodyLaravel\Commands\CleanupWebhookEventsCommand;
use Shakewell\MindbodyLaravel\Commands\ListWebhookSubscriptionsCommand;
use Shakewell\MindbodyLaravel\Commands\ProcessWebhookEventsCommand;
use Shakewell\MindbodyLaravel\Commands\SubscribeWebhooksCommand;
use Shakewell\MindbodyLaravel\Commands\SyncWebhookSubscriptionsCommand;
use Shakewell\MindbodyLaravel\Commands\TestApiConnectionCommand;
use Shakewell\MindbodyLaravel\Commands\UnsubscribeWebhooksCommand;
use Shakewell\MindbodyLaravel\Http\Controllers\WebhookController;
use Shakewell\MindbodyLaravel\Http\Middleware\VerifyWebhookSignature;
use Shakewell\MindbodyLaravel\Services\MindbodyClient;
use Shakewell\MindbodyLaravel\Services\Webhooks\WebhookHandler;
use Shakewell\MindbodyLaravel\Services\Webhooks\WebhookSubscriptionManager;
use Spatie\LaravelPackageTools\Commands\InstallCommand;
use Spatie\LaravelPackageTools\Package;
use Spatie\LaravelPackageTools\PackageServiceProvider;

class MindbodyLaravelServiceProvider extends PackageServiceProvider
{
    public function configurePackage(Package $package): void
    {
        $package
            ->name('mindbody-laravel')
            ->hasConfigFile()
            ->hasMigrations([
                'create_mindbody_webhook_events_table',
                'create_mindbody_api_tokens_table',
            ])
            ->hasCommands([
                TestApiConnectionCommand::class,
                SubscribeWebhooksCommand::class,
                UnsubscribeWebhooksCommand::class,
                ListWebhookSubscriptionsCommand::class,
                SyncWebhookSubscriptionsCommand::class,
                ProcessWebhookEventsCommand::class,
                CleanupWebhookEventsCommand::class,
            ])
            ->hasInstallCommand(static function (InstallCommand $command) {
                $command
                    ->publishConfigFile()
                    ->publishMigrations()
                    ->askToRunMigrations()
                    ->askToStarRepoOnGitHub('shakewell/mindbody-laravel');
            });
    }

    public function packageRegistered(): void
    {
        // Register the main client as singleton
        $this->app->singleton(MindbodyClient::class, static function ($app) {
            $config = $app['config']['mindbody'] ?? [];

            return new MindbodyClient($config);
        });

        // Register webhook handler
        $this->app->singleton(WebhookHandler::class, static function ($app) {
            $config = $app['config']['mindbody'] ?? [];

            return new WebhookHandler($config);
        });

        // Register webhook subscription manager
        $this->app->singleton(WebhookSubscriptionManager::class, static function ($app) {
            $config = $app['config']['mindbody'] ?? [];

            return new WebhookSubscriptionManager($config);
        });

        // Register facade aliases
        $this->app->alias(MindbodyClient::class, 'mindbody');
        $this->app->alias(WebhookHandler::class, 'mindbody.webhooks');
        $this->app->alias(WebhookSubscriptionManager::class, 'mindbody.webhook-manager');
    }

    public function packageBooted(): void
    {
        // Register middleware
        $this->app['router']->aliasMiddleware(
            'mindbody.webhook',
            VerifyWebhookSignature::class
        );

        // Register webhook routes
        $this->registerWebhookRoutes();

        // Register model observers if needed
        $this->registerObservers();

        // Register event listeners
        $this->registerEventListeners();
    }

    /**
     * Get the services provided by the provider.
     */
    public function provides(): array
    {
        return [
            MindbodyClient::class,
            WebhookHandler::class,
            WebhookSubscriptionManager::class,
            'mindbody',
            'mindbody.webhooks',
            'mindbody.webhook-manager',
        ];
    }

    /**
     * Register webhook routes.
     */
    protected function registerWebhookRoutes(): void
    {
        if (! config('mindbody.webhooks.enabled', true)) {
            return;
        }

        Route::group([
            'prefix' => config('mindbody.webhooks.route_prefix', 'mindbody/webhooks'),
            'middleware' => ['api'],
            'as' => 'mindbody.webhooks.',
        ], static function () {
            // Main webhook endpoint
            Route::post('/', [WebhookController::class, 'handle'])
                ->middleware('mindbody.webhook')
                ->name('handle');

            // Health check endpoint
            Route::get('/health', [WebhookController::class, 'health'])
                ->name('health');

            // Test endpoint (for development)
            if (config('mindbody.webhooks.enable_test_endpoint', false)) {
                Route::match(['get', 'post'], '/test', [WebhookController::class, 'test'])
                    ->name('test');
            }

            // Statistics endpoint (if enabled)
            if (config('mindbody.webhooks.expose_stats', false)) {
                Route::get('/stats', [WebhookController::class, 'stats'])
                    ->name('stats');
            }
        });
    }

    /**
     * Register model observers.
     */
    protected function registerObservers(): void
    {
        // Add any model observers here if needed
    }

    /**
     * Register event listeners.
     */
    protected function registerEventListeners(): void
    {
        // Default event listeners are registered via EventServiceProvider
        // This method can be used for package-specific listeners
    }
}
