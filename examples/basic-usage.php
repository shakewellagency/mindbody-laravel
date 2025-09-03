<?php

/**
 * Basic Usage Examples for Mindbody Laravel Package
 *
 * This file demonstrates common use cases and patterns
 * for integrating with the Mindbody API using this Laravel package.
 */

use Carbon\Carbon;
use Illuminate\Support\Facades\Log;
use Shakewell\MindbodyLaravel\Exceptions\AuthenticationException;
use Shakewell\MindbodyLaravel\Exceptions\RateLimitException;
use Shakewell\MindbodyLaravel\Exceptions\ValidationException;
use Shakewell\MindbodyLaravel\Facades\Mindbody;

class MindbodyExamples
{
    /**
     * Basic authentication and client operations
     */
    public function clientOperations()
    {
        try {
            // Authenticate with Mindbody
            Mindbody::authenticate('your-username', 'your-password');

            // Create a new client
            $newClient = Mindbody::client()->create([
                'FirstName' => 'John',
                'LastName' => 'Doe',
                'Email' => 'john.doe@example.com',
                'Phone' => '555-1234',
                'BirthDate' => '1990-01-01',
                'Gender' => 'Male',
                'AddressLine1' => '123 Main St',
                'City' => 'Anytown',
                'State' => 'CA',
                'PostalCode' => '12345',
            ]);

            Log::info('Client created', ['client_id' => $newClient['Client']['Id']]);

            // Search for clients
            $clients = Mindbody::client()->search([
                'SearchText' => 'John',
                'Limit' => 25,
                'Offset' => 0,
            ]);

            // Update client information
            $updatedClient = Mindbody::client()->update($newClient['Client']['Id'], [
                'Phone' => '555-5678',
                'Email' => 'john.updated@example.com',
            ]);

            // Get client details
            $clientDetails = Mindbody::client()->getById($newClient['Client']['Id']);

        } catch (AuthenticationException $e) {
            Log::error('Authentication failed: '.$e->getMessage());
        } catch (ValidationException $e) {
            Log::error('Validation failed', $e->getValidationErrors());
        }
    }

    /**
     * Appointment booking and management
     */
    public function appointmentOperations()
    {
        try {
            // Get staff availability
            $availability = Mindbody::appointment()->getAvailability([
                'StartDateTime' => Carbon::now()->format('Y-m-d\TH:i:s'),
                'EndDateTime' => Carbon::now()->addDays(7)->format('Y-m-d\TH:i:s'),
                'StaffIds' => [1, 2, 3],
                'SessionTypeIds' => [1], // Personal Training
            ]);

            // Book an appointment
            $appointment = Mindbody::appointment()->book([
                'ClientId' => 'client-123',
                'SessionTypeId' => 1,
                'StartDateTime' => Carbon::now()->addDay()->format('Y-m-d\TH:i:s'),
                'StaffId' => 1,
                'Notes' => 'First session - focus on assessment',
                'SendEmail' => true,
            ]);

            Log::info('Appointment booked', [
                'appointment_id' => $appointment['Appointment']['Id'],
                'start_time' => $appointment['Appointment']['StartDateTime'],
            ]);

            // Reschedule appointment
            $rescheduled = Mindbody::appointment()->reschedule(
                $appointment['Appointment']['Id'],
                Carbon::now()->addDays(2)->format('Y-m-d\TH:i:s')
            );

            // Cancel appointment
            $cancelled = Mindbody::appointment()->cancel($appointment['Appointment']['Id']);

        } catch (Exception $e) {
            Log::error('Appointment operation failed: '.$e->getMessage());
        }
    }

    /**
     * Class booking and management
     */
    public function classOperations()
    {
        try {
            // Search for classes
            $classes = Mindbody::class()->search([
                'StartDateTime' => Carbon::now()->format('Y-m-d'),
                'EndDateTime' => Carbon::now()->addWeek()->format('Y-m-d'),
                'ClassDescriptionIds' => [1, 2], // Yoga, Pilates
            ]);

            foreach ($classes['Classes'] as $class) {
                echo "Class: {$class['ClassDescription']['Name']}\n";
                echo "Time: {$class['StartDateTime']}\n";
                echo 'Available spots: '.($class['MaxCapacity'] - $class['BookedCapacity'])."\n\n";
            }

            // Book a client into a class
            $booking = Mindbody::class()->book('client-123', $classes['Classes'][0]['Id']);

            // Add client to waitlist if class is full
            if (! $booking['Success']) {
                $waitlist = Mindbody::class()->addToWaitlist('client-123', $classes['Classes'][0]['Id']);
                Log::info('Client added to waitlist', ['class_id' => $classes['Classes'][0]['Id']]);
            }

        } catch (Exception $e) {
            Log::error('Class operation failed: '.$e->getMessage());
        }
    }

    /**
     * Sales and payment processing
     */
    public function salesOperations()
    {
        try {
            // Create a sale with multiple items
            $sale = Mindbody::sale()->create([
                'ClientId' => 'client-123',
                'Items' => [
                    [
                        'Type' => 'Package',
                        'Id' => 1,
                        'Quantity' => 1,
                    ],
                    [
                        'Type' => 'Service',
                        'Id' => 2,
                        'Quantity' => 2,
                    ],
                ],
                'PaymentInfo' => [
                    'Type' => 'CreditCard',
                    'Metadata' => [
                        'Amount' => 150.00,
                        'CreditCardNumber' => 'tokenized-card-number',
                        'ExpMonth' => '12',
                        'ExpYear' => '2025',
                        'SecurityCode' => '123',
                    ],
                ],
            ]);

            Log::info('Sale completed', [
                'sale_id' => $sale['Sale']['Id'],
                'total' => $sale['Sale']['Total'],
            ]);

            // Process refund
            $refund = Mindbody::sale()->refund($sale['Sale']['Id'], [
                'Amount' => 50.00,
                'Reason' => 'Customer requested partial refund',
            ]);

        } catch (Exception $e) {
            Log::error('Sales operation failed: '.$e->getMessage());
        }
    }

    /**
     * Staff management and scheduling
     */
    public function staffOperations()
    {
        try {
            // Get all staff members
            $staff = Mindbody::staff()->getAll();

            // Get staff schedule
            $schedule = Mindbody::staff()->getSchedule(1, [
                'StartDate' => Carbon::now()->format('Y-m-d'),
                'EndDate' => Carbon::now()->addWeek()->format('Y-m-d'),
            ]);

            // Get staff permissions
            $permissions = Mindbody::staff()->getPermissions(1);

            // Get clients assigned to staff member
            $assignedClients = Mindbody::staff()->getAssignedClients(1);

        } catch (Exception $e) {
            Log::error('Staff operation failed: '.$e->getMessage());
        }
    }

    /**
     * Site information and configuration
     */
    public function siteOperations()
    {
        try {
            // Get all locations
            $locations = Mindbody::site()->getLocations();

            // Get available services
            $services = Mindbody::site()->getServices();

            // Get programs
            $programs = Mindbody::site()->getPrograms();

            // Get business hours
            $businessHours = Mindbody::site()->getBusinessHours();

            // Display location information
            foreach ($locations['Locations'] as $location) {
                echo "Location: {$location['Name']}\n";
                echo "Address: {$location['Address']}, {$location['City']}\n";
                echo "Phone: {$location['Phone']}\n\n";
            }

        } catch (Exception $e) {
            Log::error('Site operation failed: '.$e->getMessage());
        }
    }

    /**
     * Error handling patterns
     */
    public function errorHandlingExamples()
    {
        try {
            // Attempt API operation
            $clients = Mindbody::client()->getAll();

        } catch (AuthenticationException $e) {
            // Handle authentication errors
            Log::error('Authentication failed', [
                'message' => $e->getMessage(),
                'context' => $e->getContext(),
            ]);

            // Attempt re-authentication
            try {
                Mindbody::authenticate(config('mindbody.auth.username'), config('mindbody.auth.password'));
                $clients = Mindbody::client()->getAll(); // Retry operation
            } catch (Exception $retryException) {
                Log::critical('Authentication retry failed', ['error' => $retryException->getMessage()]);
            }

        } catch (RateLimitException $e) {
            // Handle rate limiting
            $retryAfter = $e->getRetryAfter();
            Log::warning('Rate limited', ['retry_after' => $retryAfter]);

            // You might queue this operation for later
            // dispatch(new ProcessMindbodyOperation())->delay(now()->addSeconds($retryAfter));

        } catch (ValidationException $e) {
            // Handle validation errors
            $errors = $e->getValidationErrors();
            Log::error('Validation failed', ['errors' => $errors]);

            // Display user-friendly error messages
            foreach ($errors as $field => $error) {
                echo "Error in {$field}: {$error}\n";
            }
        }
    }

    /**
     * Bulk operations for efficiency
     */
    public function bulkOperations()
    {
        try {
            // Bulk create clients
            $clientsData = [
                [
                    'FirstName' => 'Alice',
                    'LastName' => 'Smith',
                    'Email' => 'alice@example.com',
                ],
                [
                    'FirstName' => 'Bob',
                    'LastName' => 'Johnson',
                    'Email' => 'bob@example.com',
                ],
                // ... more clients
            ];

            $results = Mindbody::client()->bulkCreate($clientsData);

            foreach ($results as $result) {
                if ($result['Success']) {
                    Log::info('Client created', ['id' => $result['Client']['Id']]);
                } else {
                    Log::error('Client creation failed', ['error' => $result['Error']]);
                }
            }

        } catch (Exception $e) {
            Log::error('Bulk operation failed: '.$e->getMessage());
        }
    }

    /**
     * Caching strategies
     */
    public function cachingExamples()
    {
        // The package automatically caches API responses based on configuration
        // You can also manually manage cache

        try {
            // This will be cached automatically
            $locations = Mindbody::site()->getLocations();

            // Clear specific cache
            Mindbody::clearCache('locations');

            // Clear all Mindbody cache
            Mindbody::clearCache();

        } catch (Exception $e) {
            Log::error('Caching operation failed: '.$e->getMessage());
        }
    }
}
