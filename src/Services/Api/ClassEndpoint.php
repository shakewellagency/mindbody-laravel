<?php

declare(strict_types=1);

namespace Shakewell\MindbodyLaravel\Services\Api;

use Carbon\Carbon;

/**
 * Class endpoint for managing classes and class schedules.
 */
class ClassEndpoint extends BaseEndpoint
{
    protected string $endpoint = 'class';

    /**
     * Get all classes with optional filtering.
     */
    public function all(array $params = []): array
    {
        $params = $this->prepareClassParams($params);

        $response = $this->client->get('class/classes', $params);

        $classes = $this->extractResultsFromResponse($response);

        return $this->transformRecords($classes);
    }

    /**
     * Find a specific class by ID.
     */
    public function find(int $classId): ?array
    {
        $response = $this->client->get('class/classes', [
            'ClassIds' => [$classId],
        ]);

        $classes = $this->extractResultsFromResponse($response);

        if (empty($classes)) {
            return null;
        }

        return $this->transformRecord($classes[0]);
    }

    /**
     * Find classes by multiple IDs.
     */
    public function findMany(array $classIds): array
    {
        if (empty($classIds)) {
            return [];
        }

        $response = $this->client->get('class/classes', [
            'ClassIds' => $classIds,
        ]);

        $classes = $this->extractResultsFromResponse($response);

        return $this->transformRecords($classes);
    }

    /**
     * Get classes for a specific date range.
     */
    public function forDateRange(Carbon $startDate, Carbon $endDate, array $params = []): array
    {
        $params = array_merge($params, [
            'StartDateTime' => $this->formatDate($startDate),
            'EndDateTime' => $this->formatDate($endDate),
        ]);

        return $this->all($params);
    }

    /**
     * Get today's classes.
     */
    public function today(array $params = []): array
    {
        $today = Carbon::today();

        return $this->forDateRange($today, $today->copy()->endOfDay(), $params);
    }

    /**
     * Get this week's classes.
     */
    public function thisWeek(array $params = []): array
    {
        $startOfWeek = Carbon::now()->startOfWeek();
        $endOfWeek = Carbon::now()->endOfWeek();

        return $this->forDateRange($startOfWeek, $endOfWeek, $params);
    }

    /**
     * Get next week's classes.
     */
    public function nextWeek(array $params = []): array
    {
        $startOfNextWeek = Carbon::now()->addWeek()->startOfWeek();
        $endOfNextWeek = Carbon::now()->addWeek()->endOfWeek();

        return $this->forDateRange($startOfNextWeek, $endOfNextWeek, $params);
    }

    /**
     * Get class descriptions/types.
     */
    public function descriptions(array $params = []): array
    {
        $params = $this->prepareParams($params);

        $response = $this->client->get('class/classdescriptions', $params);

        $descriptions = $response['ClassDescriptions'] ?? [];

        return $this->transformRecords($descriptions);
    }

    /**
     * Get class schedules (recurring schedule templates).
     */
    public function schedules(array $params = []): array
    {
        $params = $this->prepareScheduleParams($params);

        $response = $this->client->get('class/classschedules', $params);

        return $response['ClassSchedules'] ?? [];
    }

    /**
     * Get waitlist entries for classes.
     */
    public function waitlist(int $classId): array
    {
        $response = $this->client->get('class/waitlistentries', [
            'ClassIds' => [$classId],
        ]);

        return $response['WaitlistEntries'] ?? [];
    }

    /**
     * Add a client to a class.
     */
    public function addClient(string $clientId, int $classId, array $options = []): array
    {
        $data = array_merge([
            'ClientId' => $clientId,
            'ClassId' => $classId,
            'RequirePayment' => false,
            'Waitlist' => false,
            'SendEmail' => true,
            'CrossRegionalBooking' => false,
        ], $options);

        $response = $this->client->post('class/addclienttoclass', $data);

        $this->clearCache();

        return $response;
    }

    /**
     * Remove a client from a class.
     */
    public function removeClient(string $clientId, int $classId, array $options = []): array
    {
        $data = array_merge([
            'ClientId' => $clientId,
            'ClassId' => $classId,
            'SendEmail' => true,
            'LateCancel' => false,
        ], $options);

        $response = $this->client->post('class/removeclientfromclass', $data);

        $this->clearCache();

        return $response;
    }

    /**
     * Substitute a class teacher/instructor.
     */
    public function substituteInstructor(int $classId, int $originalStaffId, int $substituteStaffId): array
    {
        $response = $this->client->post('class/substituteclassteacher', [
            'ClassId' => $classId,
            'OriginalTeacherId' => $originalStaffId,
            'SubstituteTeacherId' => $substituteStaffId,
        ]);

        $this->clearCache();

        return $response;
    }

    /**
     * Cancel a class.
     */
    public function cancel(int $classId, string $reason = '', bool $sendClientEmail = true): array
    {
        $response = $this->client->post('class/cancelclass', [
            'ClassId' => $classId,
            'CancelReason' => $reason,
            'SendClientEmail' => $sendClientEmail,
        ]);

        $this->clearCache();

        return $response;
    }

    /**
     * Get class visits (who attended).
     */
    public function visits(int $classId): array
    {
        $response = $this->client->get('class/classvisits', [
            'ClassId' => $classId,
        ]);

        return $response['Class']['Visits'] ?? [];
    }

    /**
     * Get clients enrolled in a class.
     */
    public function enrolledClients(int $classId): array
    {
        $class = $this->find($classId);

        if (! $class) {
            return [];
        }

        return $class['Clients'] ?? [];
    }

    /**
     * Check class availability.
     */
    public function checkAvailability(int $classId): array
    {
        $class = $this->find($classId);

        if (! $class) {
            return [
                'available' => false,
                'reason' => 'Class not found',
            ];
        }

        $maxCapacity = $class['MaxCapacity'] ?? 0;
        $totalBookings = $class['TotalBookings'] ?? 0;
        $webBookings = $class['WebCapacity'] ?? 0;
        $totalWebBookings = $class['TotalWebBookings'] ?? 0;

        $hasGeneralCapacity = $maxCapacity === 0 || $totalBookings < $maxCapacity;
        $hasWebCapacity = $webBookings === 0 || $totalWebBookings < $webBookings;

        $isAvailable = $hasGeneralCapacity && $hasWebCapacity;

        return [
            'available' => $isAvailable,
            'max_capacity' => $maxCapacity,
            'total_bookings' => $totalBookings,
            'web_capacity' => $webBookings,
            'total_web_bookings' => $totalWebBookings,
            'spaces_available' => $maxCapacity > 0 ? $maxCapacity - $totalBookings : null,
            'web_spaces_available' => $webBookings > 0 ? $webBookings - $totalWebBookings : null,
        ];
    }

    /**
     * Search classes by various criteria.
     */
    public function search(array $criteria = []): array
    {
        $params = [];

        if (isset($criteria['instructor_id'])) {
            $params['StaffIds'] = [$criteria['instructor_id']];
        }

        if (isset($criteria['program_id'])) {
            $params['ProgramIds'] = [$criteria['program_id']];
        }

        if (isset($criteria['location_id'])) {
            $params['LocationIds'] = [$criteria['location_id']];
        }

        if (isset($criteria['class_description_id'])) {
            $params['ClassDescriptionIds'] = [$criteria['class_description_id']];
        }

        if (isset($criteria['level'])) {
            $params['Levels'] = [$criteria['level']];
        }

        if (isset($criteria['start_date'])) {
            $params['StartDateTime'] = $this->formatDate($criteria['start_date']);
        }

        if (isset($criteria['end_date'])) {
            $params['EndDateTime'] = $this->formatDate($criteria['end_date']);
        }

        return $this->all($params);
    }

    /**
     * Get classes by instructor.
     */
    public function byInstructor(int $staffId, array $params = []): array
    {
        $params['StaffIds'] = [$staffId];

        return $this->all($params);
    }

    /**
     * Get classes by program.
     */
    public function byProgram(int $programId, array $params = []): array
    {
        $params['ProgramIds'] = [$programId];

        return $this->all($params);
    }

    /**
     * Get classes by location.
     */
    public function byLocation(int $locationId, array $params = []): array
    {
        $params['LocationIds'] = [$locationId];

        return $this->all($params);
    }

    /**
     * Get classes by description/type.
     */
    public function byDescription(int $classDescriptionId, array $params = []): array
    {
        $params['ClassDescriptionIds'] = [$classDescriptionId];

        return $this->all($params);
    }

    /**
     * Book multiple clients into a class.
     */
    public function addMultipleClients(int $classId, array $clientIds, array $options = []): array
    {
        $results = [];

        foreach ($clientIds as $clientId) {
            try {
                $results[$clientId] = $this->addClient($clientId, $classId, $options);
            } catch (\Exception $e) {
                $results[$clientId] = [
                    'success' => false,
                    'error' => $e->getMessage(),
                ];
            }
        }

        return $results;
    }

    /**
     * Extract results from API response.
     */
    protected function extractResultsFromResponse(array $response): array
    {
        return $response['Classes'] ?? [];
    }

    /**
     * Prepare class-specific parameters.
     */
    protected function prepareClassParams(array $params): array
    {
        // Set default date range if not provided
        if (! isset($params['StartDateTime']) && ! isset($params['start_date'])) {
            $params['StartDateTime'] = Carbon::now()->toIso8601String();
        }

        if (! isset($params['EndDateTime']) && ! isset($params['end_date'])) {
            $params['EndDateTime'] = Carbon::now()->addDays(7)->toIso8601String();
        }

        // Handle date aliases
        if (isset($params['start_date'])) {
            $params['StartDateTime'] = $this->formatDate($params['start_date']);
            unset($params['start_date']);
        }

        if (isset($params['end_date'])) {
            $params['EndDateTime'] = $this->formatDate($params['end_date']);
            unset($params['end_date']);
        }

        // Handle array parameters
        $arrayParams = ['StaffIds', 'ProgramIds', 'LocationIds', 'ClassDescriptionIds', 'ClassIds', 'Levels'];
        foreach ($arrayParams as $param) {
            if (isset($params[$param]) && ! \is_array($params[$param])) {
                $params[$param] = [$params[$param]];
            }
        }

        return $this->prepareParams($params);
    }

    /**
     * Prepare schedule-specific parameters.
     */
    protected function prepareScheduleParams(array $params): array
    {
        // Set default date range if not provided
        if (! isset($params['StartDate'])) {
            $params['StartDate'] = Carbon::now()->format('Y-m-d');
        }

        if (! isset($params['EndDate'])) {
            $params['EndDate'] = Carbon::now()->addMonths(1)->format('Y-m-d');
        }

        // Handle date fields
        if (isset($params['start_date'])) {
            $params['StartDate'] = $this->formatDateOnly($params['start_date']);
            unset($params['start_date']);
        }

        if (isset($params['end_date'])) {
            $params['EndDate'] = $this->formatDateOnly($params['end_date']);
            unset($params['end_date']);
        }

        return $this->prepareParams($params);
    }

    /**
     * Get date fields specific to classes.
     */
    protected function getDateFields(): array
    {
        return array_merge(parent::getDateFields(), [
            'StartDateTime',
            'EndDateTime',
            'LastModifiedDateTime',
            'BookingOpenDate',
            'BookingCloseDate',
        ]);
    }

    /**
     * Process bulk class operations.
     */
    protected function processBulkBatch(string $operation, array $batch): array
    {
        switch ($operation) {
            case 'add_clients':
                return $this->bulkAddClients($batch);
            case 'remove_clients':
                return $this->bulkRemoveClients($batch);
            default:
                throw new \InvalidArgumentException("Unsupported bulk operation: {$operation}");
        }
    }

    /**
     * Bulk add clients to classes.
     */
    protected function bulkAddClients(array $operations): array
    {
        $results = [];

        foreach ($operations as $operation) {
            if (! isset($operation['client_id'], $operation['class_id'])) {
                $results[] = [
                    'success' => false,
                    'error' => 'client_id and class_id are required',
                    'data' => $operation,
                ];

                continue;
            }

            try {
                $results[] = $this->addClient(
                    $operation['client_id'],
                    $operation['class_id'],
                    $operation['options'] ?? []
                );
            } catch (\Exception $e) {
                $results[] = [
                    'success' => false,
                    'error' => $e->getMessage(),
                    'data' => $operation,
                ];
            }
        }

        return $results;
    }

    /**
     * Bulk remove clients from classes.
     */
    protected function bulkRemoveClients(array $operations): array
    {
        $results = [];

        foreach ($operations as $operation) {
            if (! isset($operation['client_id'], $operation['class_id'])) {
                $results[] = [
                    'success' => false,
                    'error' => 'client_id and class_id are required',
                    'data' => $operation,
                ];

                continue;
            }

            try {
                $results[] = $this->removeClient(
                    $operation['client_id'],
                    $operation['class_id'],
                    $operation['options'] ?? []
                );
            } catch (\Exception $e) {
                $results[] = [
                    'success' => false,
                    'error' => $e->getMessage(),
                    'data' => $operation,
                ];
            }
        }

        return $results;
    }
}
