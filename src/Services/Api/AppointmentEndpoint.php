<?php

declare(strict_types=1);

namespace Shakewell\MindbodyLaravel\Services\Api;

use Carbon\Carbon;

/**
 * Appointment endpoint for managing appointments and bookings.
 */
class AppointmentEndpoint extends BaseEndpoint
{
    protected string $endpoint = 'appointment';

    /**
     * Get all appointments with optional filtering.
     */
    public function all(array $params = []): array
    {
        $params = $this->prepareAppointmentParams($params);

        $response = $this->client->get('appointments', $params);

        $appointments = $this->extractResultsFromResponse($response);

        return $this->transformRecords($appointments);
    }

    /**
     * Find a specific appointment by ID.
     */
    public function find(int $appointmentId): ?array
    {
        $response = $this->client->get('appointments', [
            'AppointmentIds' => [$appointmentId],
        ]);

        $appointments = $this->extractResultsFromResponse($response);

        if (empty($appointments)) {
            return null;
        }

        return $this->transformRecord($appointments[0]);
    }

    /**
     * Find appointments by multiple IDs.
     */
    public function findMany(array $appointmentIds): array
    {
        if (empty($appointmentIds)) {
            return [];
        }

        $response = $this->client->get('appointments', [
            'AppointmentIds' => $appointmentIds,
        ]);

        $appointments = $this->extractResultsFromResponse($response);

        return $this->transformRecords($appointments);
    }

    /**
     * Book a new appointment.
     */
    public function book(array $data): array
    {
        $this->validateRequired($data, ['ClientId', 'SessionTypeId', 'StaffId', 'StartDateTime']);

        $appointmentData = $this->validateAppointmentData($data);

        $response = $this->client->post('appointment/addappointment', $appointmentData);

        $this->clearCache();

        if (isset($response['Appointment'])) {
            return $this->transformRecord($response['Appointment']);
        }

        return $response;
    }

    /**
     * Update an existing appointment.
     */
    public function update(int $appointmentId, array $data): array
    {
        $appointmentData = $this->validateAppointmentData($data);
        $appointmentData['AppointmentId'] = $appointmentId;

        $response = $this->client->post('appointment/updateappointment', $appointmentData);

        $this->clearCache();

        if (isset($response['Appointment'])) {
            return $this->transformRecord($response['Appointment']);
        }

        return $response;
    }

    /**
     * Cancel an appointment.
     */
    public function cancel(int $appointmentId, string $reason = '', bool $sendEmail = true): array
    {
        $response = $this->client->post('appointment/cancelappointment', [
            'AppointmentId' => $appointmentId,
            'CancelReason' => $reason,
            'SendEmail' => $sendEmail,
        ]);

        $this->clearCache();

        return $response;
    }

    /**
     * Get appointments for a specific date range.
     */
    public function forDateRange(Carbon $startDate, Carbon $endDate, array $params = []): array
    {
        $params = array_merge($params, [
            'StartDate' => $this->formatDateOnly($startDate),
            'EndDate' => $this->formatDateOnly($endDate),
        ]);

        return $this->all($params);
    }

    /**
     * Get today's appointments.
     */
    public function today(array $params = []): array
    {
        $today = Carbon::today();

        return $this->forDateRange($today, $today, $params);
    }

    /**
     * Get this week's appointments.
     */
    public function thisWeek(array $params = []): array
    {
        $startOfWeek = Carbon::now()->startOfWeek();
        $endOfWeek = Carbon::now()->endOfWeek();

        return $this->forDateRange($startOfWeek, $endOfWeek, $params);
    }

    /**
     * Get appointments by client.
     */
    public function byClient(string $clientId, array $params = []): array
    {
        $params['ClientIds'] = [$clientId];

        return $this->all($params);
    }

    /**
     * Get appointments by staff member.
     */
    public function byStaff(int $staffId, array $params = []): array
    {
        $params['StaffIds'] = [$staffId];

        return $this->all($params);
    }

    /**
     * Get appointments by location.
     */
    public function byLocation(int $locationId, array $params = []): array
    {
        $params['LocationIds'] = [$locationId];

        return $this->all($params);
    }

    /**
     * Search appointments by various criteria.
     */
    public function search(array $criteria = []): array
    {
        $params = [];

        if (isset($criteria['client_id'])) {
            $params['ClientIds'] = [$criteria['client_id']];
        }

        if (isset($criteria['staff_id'])) {
            $params['StaffIds'] = [$criteria['staff_id']];
        }

        if (isset($criteria['location_id'])) {
            $params['LocationIds'] = [$criteria['location_id']];
        }

        if (isset($criteria['session_type_id'])) {
            $params['SessionTypeIds'] = [$criteria['session_type_id']];
        }

        if (isset($criteria['status'])) {
            $params['AppointmentStatuses'] = [$criteria['status']];
        }

        if (isset($criteria['start_date'])) {
            $params['StartDate'] = $this->formatDateOnly($criteria['start_date']);
        }

        if (isset($criteria['end_date'])) {
            $params['EndDate'] = $this->formatDateOnly($criteria['end_date']);
        }

        return $this->all($params);
    }

    /**
     * Get staff availability for appointments.
     */
    public function staffAvailability(int $staffId, Carbon $date, array $params = []): array
    {
        $params = array_merge([
            'StaffId' => $staffId,
            'StartDate' => $this->formatDateOnly($date),
            'EndDate' => $this->formatDateOnly($date),
        ], $this->prepareParams($params));

        $response = $this->client->get('appointment/staffavailability', $params);

        return $response['AvailableTimes'] ?? [];
    }

    /**
     * Get available appointment times.
     */
    public function availableTimes(array $params = []): array
    {
        $this->validateRequired($params, ['SessionTypeIds', 'StartDate']);

        $params = $this->prepareParams($params);

        if (isset($params['start_date'])) {
            $params['StartDate'] = $this->formatDateOnly($params['start_date']);
            unset($params['start_date']);
        }

        $response = $this->client->get('appointment/availabletimes', $params);

        return $response['AvailableTimes'] ?? [];
    }

    /**
     * Get appointment add-ons.
     */
    public function addOns(int $appointmentId): array
    {
        $response = $this->client->get('appointment/appointmentaddons', [
            'AppointmentId' => $appointmentId,
        ]);

        return $response['AppointmentAddOns'] ?? [];
    }

    /**
     * Add appointment add-on.
     */
    public function addAddOn(int $appointmentId, int $serviceId, int $quantity = 1): array
    {
        $response = $this->client->post('appointment/addappointmentaddon', [
            'AppointmentId' => $appointmentId,
            'ServiceId' => $serviceId,
            'Quantity' => $quantity,
        ]);

        $this->clearCache();

        return $response;
    }

    /**
     * Remove appointment add-on.
     */
    public function removeAddOn(int $appointmentId, int $addOnId): array
    {
        $response = $this->client->post('appointment/removeappointmentaddon', [
            'AppointmentId' => $appointmentId,
            'AddOnId' => $addOnId,
        ]);

        $this->clearCache();

        return $response;
    }

    /**
     * Check in a client to their appointment.
     */
    public function checkIn(int $appointmentId): array
    {
        $response = $this->client->post('appointment/checkinappointment', [
            'AppointmentId' => $appointmentId,
        ]);

        $this->clearCache();

        return $response;
    }

    /**
     * Get booking windows (when appointments can be booked).
     */
    public function bookingWindows(): array
    {
        $response = $this->client->get('appointment/bookingwindows');

        return $response['BookingWindows'] ?? [];
    }

    /**
     * Reschedule an appointment.
     */
    public function reschedule(int $appointmentId, Carbon $newDateTime, ?int $newStaffId = null): array
    {
        $data = [
            'AppointmentId' => $appointmentId,
            'StartDateTime' => $this->formatDate($newDateTime),
        ];

        if ($newStaffId) {
            $data['StaffId'] = $newStaffId;
        }

        return $this->update($appointmentId, $data);
    }

    /**
     * Get no-show appointments.
     */
    public function noShows(array $params = []): array
    {
        $params['AppointmentStatuses'] = ['No Show'];

        return $this->all($params);
    }

    /**
     * Get cancelled appointments.
     */
    public function cancelled(array $params = []): array
    {
        $params['AppointmentStatuses'] = ['Cancelled'];

        return $this->all($params);
    }

    /**
     * Get completed appointments.
     */
    public function completed(array $params = []): array
    {
        $params['AppointmentStatuses'] = ['Completed'];

        return $this->all($params);
    }

    /**
     * Get confirmed appointments.
     */
    public function confirmed(array $params = []): array
    {
        $params['AppointmentStatuses'] = ['Confirmed'];

        return $this->all($params);
    }

    /**
     * Mark appointment as arrived.
     */
    public function markAsArrived(int $appointmentId): array
    {
        return $this->update($appointmentId, ['Status' => 'Arrived']);
    }

    /**
     * Mark appointment as no-show.
     */
    public function markAsNoShow(int $appointmentId): array
    {
        return $this->update($appointmentId, ['Status' => 'No Show']);
    }

    /**
     * Mark appointment as completed.
     */
    public function markAsCompleted(int $appointmentId): array
    {
        return $this->update($appointmentId, ['Status' => 'Completed']);
    }

    /**
     * Get appointments that need confirmation.
     */
    public function needingConfirmation(array $params = []): array
    {
        $params['AppointmentStatuses'] = ['Requested'];

        return $this->all($params);
    }

    /**
     * Confirm an appointment.
     */
    public function confirm(int $appointmentId): array
    {
        return $this->update($appointmentId, ['Status' => 'Confirmed']);
    }

    /**
     * Extract results from API response.
     */
    protected function extractResultsFromResponse(array $response): array
    {
        return $response['Appointments'] ?? [];
    }

    /**
     * Prepare appointment-specific parameters.
     */
    protected function prepareAppointmentParams(array $params): array
    {
        // Set default date range if not provided
        if (! isset($params['StartDate']) && ! isset($params['start_date'])) {
            $params['StartDate'] = Carbon::now()->format('Y-m-d');
        }

        if (! isset($params['EndDate']) && ! isset($params['end_date'])) {
            $params['EndDate'] = Carbon::now()->addDays(7)->format('Y-m-d');
        }

        // Handle date aliases
        if (isset($params['start_date'])) {
            $params['StartDate'] = $this->formatDateOnly($params['start_date']);
            unset($params['start_date']);
        }

        if (isset($params['end_date'])) {
            $params['EndDate'] = $this->formatDateOnly($params['end_date']);
            unset($params['end_date']);
        }

        // Handle array parameters
        $arrayParams = ['AppointmentIds', 'ClientIds', 'StaffIds', 'LocationIds', 'SessionTypeIds', 'AppointmentStatuses'];
        foreach ($arrayParams as $param) {
            if (isset($params[$param]) && ! \is_array($params[$param])) {
                $params[$param] = [$params[$param]];
            }
        }

        return $this->prepareParams($params);
    }

    /**
     * Validate and prepare appointment data.
     */
    protected function validateAppointmentData(array $data): array
    {
        // Handle date/time fields
        if (isset($data['StartDateTime'])) {
            $data['StartDateTime'] = $this->formatDate($data['StartDateTime']);
        }

        if (isset($data['EndDateTime'])) {
            $data['EndDateTime'] = $this->formatDate($data['EndDateTime']);
        }

        // Map common field aliases
        $fieldMappings = [
            'client_id' => 'ClientId',
            'staff_id' => 'StaffId',
            'session_type_id' => 'SessionTypeId',
            'location_id' => 'LocationId',
            'start_time' => 'StartDateTime',
            'end_time' => 'EndDateTime',
            'notes' => 'Notes',
            'status' => 'Status',
        ];

        foreach ($fieldMappings as $alias => $field) {
            if (isset($data[$alias]) && ! isset($data[$field])) {
                $data[$field] = $data[$alias];
                unset($data[$alias]);
            }
        }

        return $data;
    }

    /**
     * Get date fields specific to appointments.
     */
    protected function getDateFields(): array
    {
        return array_merge(parent::getDateFields(), [
            'StartDateTime',
            'EndDateTime',
            'CreationDateTime',
            'LastModifiedDateTime',
            'BookedDateTime',
        ]);
    }

    /**
     * Process bulk appointment operations.
     */
    protected function processBulkBatch(string $operation, array $batch): array
    {
        switch ($operation) {
            case 'book':
                return $this->bulkBook($batch);
            case 'cancel':
                return $this->bulkCancel($batch);
            case 'update':
                return $this->bulkUpdate($batch);
            default:
                throw new \InvalidArgumentException("Unsupported bulk operation: {$operation}");
        }
    }

    /**
     * Bulk book appointments.
     */
    protected function bulkBook(array $appointments): array
    {
        $results = [];

        foreach ($appointments as $appointmentData) {
            try {
                $results[] = $this->book($appointmentData);
            } catch (\Exception $e) {
                $results[] = [
                    'success' => false,
                    'error' => $e->getMessage(),
                    'data' => $appointmentData,
                ];
            }
        }

        return $results;
    }

    /**
     * Bulk cancel appointments.
     */
    protected function bulkCancel(array $cancellations): array
    {
        $results = [];

        foreach ($cancellations as $cancellation) {
            if (! isset($cancellation['appointment_id'])) {
                $results[] = [
                    'success' => false,
                    'error' => 'appointment_id is required',
                    'data' => $cancellation,
                ];

                continue;
            }

            try {
                $results[] = $this->cancel(
                    $cancellation['appointment_id'],
                    $cancellation['reason'] ?? '',
                    $cancellation['send_email'] ?? true
                );
            } catch (\Exception $e) {
                $results[] = [
                    'success' => false,
                    'error' => $e->getMessage(),
                    'data' => $cancellation,
                ];
            }
        }

        return $results;
    }

    /**
     * Bulk update appointments.
     */
    protected function bulkUpdate(array $updates): array
    {
        $results = [];

        foreach ($updates as $update) {
            if (! isset($update['appointment_id'])) {
                $results[] = [
                    'success' => false,
                    'error' => 'appointment_id is required',
                    'data' => $update,
                ];

                continue;
            }

            try {
                $appointmentId = $update['appointment_id'];
                unset($update['appointment_id']);
                $results[] = $this->update($appointmentId, $update);
            } catch (\Exception $e) {
                $results[] = [
                    'success' => false,
                    'error' => $e->getMessage(),
                    'data' => $update,
                ];
            }
        }

        return $results;
    }
}
