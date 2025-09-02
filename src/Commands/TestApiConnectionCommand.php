<?php

declare(strict_types=1);
namespace Shakewell\MindbodyLaravel\Commands;

use Illuminate\Console\Command;
use Shakewell\MindbodyLaravel\Exceptions\AuthenticationException;
use Shakewell\MindbodyLaravel\Exceptions\MindbodyException;
use Shakewell\MindbodyLaravel\Services\MindbodyClient;

/**
 * Command to test the Mindbody API connection.
 */
class TestApiConnectionCommand extends Command
{
    protected $signature = 'mindbody:test-connection
                           {--username= : Override config username for testing}
                           {--password= : Override config password for testing}
                           {--timeout=30 : Connection timeout in seconds}';

    protected $description = 'Test the connection to the Mindbody API';

    protected MindbodyClient $client;

    public function __construct(MindbodyClient $client)
    {
        parent::__construct();
        $this->client = $client;
    }

    public function handle(): int
    {
        $this->info('Testing Mindbody API connection...');
        $this->newLine();

        // Display configuration
        $this->displayConfiguration();

        // Test basic connectivity
        if (! $this->testBasicConnectivity()) {
            return Command::FAILURE;
        }

        // Test authentication
        if (! $this->testAuthentication()) {
            return Command::FAILURE;
        }

        // Test API endpoints
        if (! $this->testApiEndpoints()) {
            return Command::FAILURE;
        }

        $this->newLine();
        $this->info('✅ All connection tests passed successfully!');

        return Command::SUCCESS;
    }

    protected function displayConfiguration(): void
    {
        $this->info('Configuration:');
        $this->line('  Base URL: '.config('mindbody.api.base_url'));
        $this->line('  Version: '.config('mindbody.api.version'));
        $this->line('  Site ID: '.config('mindbody.api.site_id'));
        $this->line('  Source Name: '.config('mindbody.api.source_name'));
        $this->line('  Timeout: '.config('mindbody.api.timeout', 30).'s');
        $this->newLine();
    }

    protected function testBasicConnectivity(): bool
    {
        $this->info('Testing basic connectivity...');

        try {
            $response = $this->client->testConnection();

            if ($response) {
                $this->info('✅ Basic connectivity test passed');

                return true;
            }
            $this->error('❌ Basic connectivity test failed');

            return false;
        } catch (\Exception $e) {
            $this->error('❌ Connectivity error: '.$e->getMessage());

            return false;
        }
    }

    protected function testAuthentication(): bool
    {
        $this->info('Testing authentication...');

        $username = $this->option('username') ?: config('mindbody.auth.username');
        $password = $this->option('password') ?: config('mindbody.auth.password');

        if (! $username || ! $password) {
            $this->warn('⚠️  No authentication credentials configured - skipping auth test');

            return true;
        }

        try {
            $this->client->authenticate($username, $password);

            $token = $this->client->getUserToken();
            if ($token) {
                $this->info('✅ Authentication test passed');
                $this->line('  Token: '.substr($token, 0, 20).'...');

                return true;
            }
            $this->error('❌ Authentication test failed - no token received');

            return false;
        } catch (AuthenticationException $e) {
            $this->error('❌ Authentication failed: '.$e->getMessage());

            return false;
        } catch (\Exception $e) {
            $this->error('❌ Authentication error: '.$e->getMessage());

            return false;
        }
    }

    protected function testApiEndpoints(): bool
    {
        $this->info('Testing API endpoints...');

        $endpoints = [
            'Site Information' => fn () => $this->client->site()->getLocations(),
            'Staff List' => fn () => $this->client->staff()->getAll(['Limit' => 1]),
            'Services' => fn () => $this->client->site()->getServices(['Limit' => 1]),
        ];

        $allPassed = true;

        foreach ($endpoints as $name => $test) {
            try {
                $result = $test();

                if ($result && \is_array($result)) {
                    $this->info("✅ {$name} endpoint test passed");
                } else {
                    $this->warn("⚠️  {$name} endpoint returned empty result");
                }
            } catch (MindbodyException $e) {
                $this->error("❌ {$name} endpoint failed: ".$e->getMessage());
                $allPassed = false;
            } catch (\Exception $e) {
                $this->error("❌ {$name} endpoint error: ".$e->getMessage());
                $allPassed = false;
            }
        }

        return $allPassed;
    }
}
