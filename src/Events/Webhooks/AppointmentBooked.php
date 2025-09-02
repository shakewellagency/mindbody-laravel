<?php

declare(strict_types=1);
namespace Shakewell\MindbodyLaravel\Events\Webhooks;

use Carbon\Carbon;
use Shakewell\MindbodyLaravel\Events\WebhookReceived;

/**
 * Event fired when an appointment is booked.
 */
class AppointmentBooked extends WebhookReceived
{
    /**
     * Get the appointment data from the event.
     */
    public function getAppointment(): array
    {
        return $this->getEventData()['Appointment'] ?? [];
    }

    /**
     * Get the appointment ID.
     */
    public function getAppointmentId(): ?int
    {
        return $this->getAppointment()['Id'] ?? null;
    }

    /**
     * Get the client ID.
     */
    public function getClientId(): ?string
    {
        return $this->getAppointment()['ClientId'] ?? null;
    }

    /**
     * Get the staff ID.
     */
    public function getStaffId(): ?int
    {
        return $this->getAppointment()['StaffId'] ?? null;
    }

    /**
     * Get the appointment start time.
     */
    public function getStartTime(): ?Carbon
    {
        $startDateTime = $this->getAppointment()['StartDateTime'] ?? null;

        return $startDateTime ? Carbon::parse($startDateTime) : null;
    }

    /**
     * Get the appointment end time.
     */
    public function getEndTime(): ?Carbon
    {
        $endDateTime = $this->getAppointment()['EndDateTime'] ?? null;

        return $endDateTime ? Carbon::parse($endDateTime) : null;
    }

    /**
     * Get the service/session type.
     */
    public function getSessionType(): array
    {
        return $this->getAppointment()['SessionType'] ?? [];
    }

    /**
     * Get the appointment status.
     */
    public function getStatus(): ?string
    {
        return $this->getAppointment()['Status'] ?? null;
    }

    /**
     * Get the location ID.
     */
    public function getLocationId(): ?int
    {
        return $this->getAppointment()['LocationId'] ?? null;
    }
}
