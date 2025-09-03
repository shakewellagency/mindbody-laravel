<?php

/**
 * Webhook Handling Examples for Mindbody Laravel Package
 *
 * This file demonstrates how to properly handle webhooks
 * from Mindbody and implement custom business logic.
 */

use Carbon\Carbon;
use Illuminate\Support\Facades\Log;
use Shakewell\MindbodyLaravel\Events\Webhooks\AppointmentBooked;
use Shakewell\MindbodyLaravel\Events\Webhooks\ClientCreated;
use Shakewell\MindbodyLaravel\Models\WebhookEvent;

/**
 * Example Event Listener for Appointment Bookings
 */
class HandleAppointmentBooked
{
    public function handle(AppointmentBooked $event): void
    {
        $appointment = $event->getAppointment();
        $clientId = $event->getClientId();
        $staffId = $event->getStaffId();
        $startTime = $event->getStartTime();
        $sessionType = $event->getSessionType();

        Log::info('Appointment booked via webhook', [
            'appointment_id' => $appointment['Id'],
            'client_id' => $clientId,
            'staff_id' => $staffId,
            'start_time' => $startTime?->toISOString(),
            'session_type' => $sessionType['Name'] ?? 'Unknown',
        ]);

        // Send confirmation email to client
        $this->sendAppointmentConfirmation($clientId, $appointment);

        // Update your local database
        $this->syncAppointmentToLocalDatabase($appointment);

        // Send notifications to relevant staff
        $this->notifyStaff($staffId, $appointment);

        // Update business metrics
        $this->updateBookingMetrics($appointment);
    }

    private function sendAppointmentConfirmation(string $clientId, array $appointment): void
    {
        // Implementation would depend on your notification system
        // This is just an example structure

        try {
            // Get client details (you might have this cached or in local DB)
            $client = \Shakewell\MindbodyLaravel\Facades\Mindbody::client()->getById($clientId);

            // Send email using your preferred mail service
            \Illuminate\Support\Facades\Mail::to($client['Email'])->send(
                new \App\Mail\AppointmentConfirmation($appointment, $client)
            );

            Log::info('Confirmation email sent', ['client_id' => $clientId]);

        } catch (\Exception $e) {
            Log::error('Failed to send confirmation email', [
                'client_id' => $clientId,
                'error' => $e->getMessage(),
            ]);
        }
    }

    private function syncAppointmentToLocalDatabase(array $appointment): void
    {
        // Sync appointment data to your local database
        // This example assumes you have a local Appointment model

        try {
            \App\Models\Appointment::updateOrCreate(
                ['mindbody_id' => $appointment['Id']],
                [
                    'client_id' => $appointment['ClientId'],
                    'staff_id' => $appointment['StaffId'],
                    'start_time' => Carbon::parse($appointment['StartDateTime']),
                    'end_time' => Carbon::parse($appointment['EndDateTime']),
                    'status' => $appointment['Status'],
                    'session_type_id' => $appointment['SessionType']['Id'],
                    'location_id' => $appointment['LocationId'],
                    'notes' => $appointment['Notes'] ?? '',
                ]
            );

            Log::info('Appointment synced to local database', ['appointment_id' => $appointment['Id']]);

        } catch (\Exception $e) {
            Log::error('Failed to sync appointment to local database', [
                'appointment_id' => $appointment['Id'],
                'error' => $e->getMessage(),
            ]);
        }
    }

    private function notifyStaff(int $staffId, array $appointment): void
    {
        try {
            // Get staff details
            $staff = \Shakewell\MindbodyLaravel\Facades\Mindbody::staff()->getById($staffId);

            // Send notification (email, SMS, push notification, etc.)
            \Illuminate\Support\Facades\Notification::send(
                \App\Models\StaffMember::where('mindbody_id', $staffId)->first(),
                new \App\Notifications\NewAppointmentBooked($appointment)
            );

            Log::info('Staff notified of new appointment', ['staff_id' => $staffId]);

        } catch (\Exception $e) {
            Log::error('Failed to notify staff', [
                'staff_id' => $staffId,
                'error' => $e->getMessage(),
            ]);
        }
    }

    private function updateBookingMetrics(array $appointment): void
    {
        try {
            // Update your business metrics
            // This could be stored in a metrics table, sent to analytics service, etc.

            $sessionType = $appointment['SessionType']['Name'];
            $date = Carbon::parse($appointment['StartDateTime'])->format('Y-m-d');

            // Example: Update daily booking counts
            \App\Models\DailyMetric::updateOrCreate(
                ['date' => $date, 'metric' => 'appointments_booked'],
                ['value' => \DB::raw('value + 1')]
            );

            // Example: Update session type popularity
            \App\Models\SessionTypeMetric::updateOrCreate(
                ['session_type' => $sessionType, 'date' => $date],
                ['bookings' => \DB::raw('bookings + 1')]
            );

        } catch (\Exception $e) {
            Log::error('Failed to update booking metrics', ['error' => $e->getMessage()]);
        }
    }
}

/**
 * Example Event Listener for Client Creation
 */
class HandleClientCreated
{
    public function handle(ClientCreated $event): void
    {
        $client = $event->getClient();
        $clientId = $event->getClientId();

        Log::info('New client created via webhook', [
            'client_id' => $clientId,
            'name' => "{$client['FirstName']} {$client['LastName']}",
            'email' => $client['Email'] ?? 'N/A',
        ]);

        // Send welcome email
        $this->sendWelcomeEmail($client);

        // Sync to local database
        $this->syncClientToLocalDatabase($client);

        // Trigger onboarding workflow
        $this->triggerOnboardingWorkflow($client);

        // Add to marketing lists
        $this->addToMarketingLists($client);
    }

    private function sendWelcomeEmail(array $client): void
    {
        try {
            if (! empty($client['Email'])) {
                \Illuminate\Support\Facades\Mail::to($client['Email'])->send(
                    new \App\Mail\WelcomeNewClient($client)
                );

                Log::info('Welcome email sent to new client', ['client_id' => $client['Id']]);
            }
        } catch (\Exception $e) {
            Log::error('Failed to send welcome email', [
                'client_id' => $client['Id'],
                'error' => $e->getMessage(),
            ]);
        }
    }

    private function syncClientToLocalDatabase(array $client): void
    {
        try {
            \App\Models\Client::updateOrCreate(
                ['mindbody_id' => $client['Id']],
                [
                    'first_name' => $client['FirstName'],
                    'last_name' => $client['LastName'],
                    'email' => $client['Email'] ?? null,
                    'phone' => $client['MobilePhone'] ?? null,
                    'birth_date' => isset($client['BirthDate']) ? Carbon::parse($client['BirthDate']) : null,
                    'status' => $client['Status'] ?? 'Active',
                    'creation_date' => Carbon::parse($client['CreationDate']),
                ]
            );

            Log::info('Client synced to local database', ['client_id' => $client['Id']]);

        } catch (\Exception $e) {
            Log::error('Failed to sync client to local database', [
                'client_id' => $client['Id'],
                'error' => $e->getMessage(),
            ]);
        }
    }

    private function triggerOnboardingWorkflow(array $client): void
    {
        try {
            // Trigger an onboarding workflow (could be a job, event, or service call)
            \App\Jobs\OnboardNewClient::dispatch($client);

            Log::info('Onboarding workflow triggered', ['client_id' => $client['Id']]);

        } catch (\Exception $e) {
            Log::error('Failed to trigger onboarding workflow', [
                'client_id' => $client['Id'],
                'error' => $e->getMessage(),
            ]);
        }
    }

    private function addToMarketingLists(array $client): void
    {
        try {
            if (! empty($client['Email'])) {
                // Add to your email marketing service
                // This example assumes you have a marketing service integration

                \App\Services\MarketingService::addToList($client['Email'], [
                    'first_name' => $client['FirstName'],
                    'last_name' => $client['LastName'],
                    'source' => 'mindbody',
                    'tags' => ['new-client', 'mindbody-sync'],
                ]);

                Log::info('Client added to marketing lists', ['client_id' => $client['Id']]);
            }
        } catch (\Exception $e) {
            Log::error('Failed to add client to marketing lists', [
                'client_id' => $client['Id'],
                'error' => $e->getMessage(),
            ]);
        }
    }
}

/**
 * Custom Webhook Handler for Advanced Processing
 */
class CustomWebhookHandler extends \Shakewell\MindbodyLaravel\Services\Webhooks\WebhookHandler
{
    protected function processEvent(\Shakewell\MindbodyLaravel\Models\WebhookEvent $event): void
    {
        // Add custom logging
        Log::info('Processing webhook event', [
            'event_id' => $event->event_id,
            'event_type' => $event->event_type,
            'attempt' => $event->retry_count + 1,
        ]);

        // Custom processing based on event type
        try {
            match ($event->event_type) {
                'appointment.booked' => $this->handleAppointmentBooked($event),
                'appointment.cancelled' => $this->handleAppointmentCancelled($event),
                'client.created' => $this->handleClientCreated($event),
                'class.booked' => $this->handleClassBooked($event),
                default => parent::processEvent($event),
            };

            // Mark as processed
            $event->update(['status' => 'processed']);

        } catch (\Exception $e) {
            Log::error('Custom webhook processing failed', [
                'event_id' => $event->event_id,
                'error' => $e->getMessage(),
            ]);

            // Let parent class handle retry logic
            throw $e;
        }
    }

    private function handleAppointmentBooked(WebhookEvent $event): void
    {
        $appointmentData = $event->payload['EventData']['Appointment'] ?? [];

        // Custom business logic for appointment booking
        // For example, check for conflicts, send special notifications, etc.

        if ($this->isVipClient($appointmentData['ClientId'])) {
            $this->notifyVipAppointmentBooked($appointmentData);
        }

        if ($this->isFirstTimeBooking($appointmentData['ClientId'])) {
            $this->sendFirstTimeBookingInstructions($appointmentData);
        }
    }

    private function handleAppointmentCancelled(WebhookEvent $event): void
    {
        $appointmentData = $event->payload['EventData']['Appointment'] ?? [];

        // Custom logic for cancellations
        // Update waitlists, send notifications, update metrics, etc.

        $this->processWaitlist($appointmentData);
        $this->updateCancellationMetrics($appointmentData);
    }

    private function isVipClient(string $clientId): bool
    {
        // Your logic to determine if client is VIP
        // This could check a local database, client tags, purchase history, etc.

        try {
            return \App\Models\Client::where('mindbody_id', $clientId)
                ->where('vip_status', true)
                ->exists();
        } catch (\Exception $e) {
            return false;
        }
    }

    private function isFirstTimeBooking(string $clientId): bool
    {
        try {
            $appointmentCount = \App\Models\Appointment::where('client_id', $clientId)->count();

            return $appointmentCount <= 1; // Including this booking
        } catch (\Exception $e) {
            return false;
        }
    }

    private function notifyVipAppointmentBooked(array $appointment): void
    {
        // Send special notifications for VIP clients
        // Could be SMS, email to manager, Slack notification, etc.

        \App\Jobs\NotifyVipAppointment::dispatch($appointment);
    }

    private function sendFirstTimeBookingInstructions(array $appointment): void
    {
        // Send special instructions to first-time clients
        // What to bring, where to park, check-in procedures, etc.

        \App\Jobs\SendFirstTimeClientInstructions::dispatch($appointment);
    }

    private function processWaitlist(array $appointment): void
    {
        // Check if anyone is on the waitlist for this time slot
        // and automatically book them if the appointment was cancelled

        \App\Jobs\ProcessWaitlistForCancelledAppointment::dispatch($appointment);
    }

    private function updateCancellationMetrics(array $appointment): void
    {
        // Update metrics for cancelled appointments
        // Track cancellation patterns, reasons, timing, etc.

        $date = Carbon::parse($appointment['StartDateTime'])->format('Y-m-d');

        \App\Models\DailyMetric::updateOrCreate(
            ['date' => $date, 'metric' => 'appointments_cancelled'],
            ['value' => \DB::raw('value + 1')]
        );
    }
}

/**
 * Example Laravel Job for Processing Webhook Events
 */
class ProcessWebhookEvent
{
    use \Illuminate\Bus\Queueable;
    use \Illuminate\Contracts\Queue\ShouldQueue;
    use \Illuminate\Foundation\Bus\Dispatchable;
    use \Illuminate\Queue\InteractsWithQueue;
    use \Illuminate\Queue\SerializesModels;

    public function __construct(
        public WebhookEvent $webhookEvent
    ) {}

    public function handle(): void
    {
        Log::info('Processing webhook event in background', [
            'event_id' => $this->webhookEvent->event_id,
            'event_type' => $this->webhookEvent->event_type,
        ]);

        try {
            // Process the webhook event
            app(\Shakewell\MindbodyLaravel\Services\Webhooks\WebhookHandler::class)
                ->processEvent($this->webhookEvent);

        } catch (\Exception $e) {
            Log::error('Background webhook processing failed', [
                'event_id' => $this->webhookEvent->event_id,
                'error' => $e->getMessage(),
            ]);

            // Re-throw to trigger job retry
            throw $e;
        }
    }

    public function failed(\Throwable $exception): void
    {
        Log::error('Webhook processing job failed permanently', [
            'event_id' => $this->webhookEvent->event_id,
            'error' => $exception->getMessage(),
        ]);

        // Mark webhook as failed
        $this->webhookEvent->update([
            'status' => 'failed',
            'error_message' => $exception->getMessage(),
        ]);
    }
}
