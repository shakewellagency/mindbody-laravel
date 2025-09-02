<?php

declare(strict_types=1);
namespace Shakewell\MindbodyLaravel\Services\Api;

use Carbon\Carbon;

/**
 * Staff endpoint for managing staff members, instructors, and their schedules.
 */
class StaffEndpoint extends BaseEndpoint
{
    protected string $endpoint = 'staff';

    /**
     * Get all staff members with optional filtering.
     */
    public function all(array $params = []): array
    {
        $params = $this->prepareStaffParams($params);

        $results = $this->getAll('staff/staff', $params);

        return $this->transformRecords($results);
    }

    /**
     * Find a specific staff member by ID.
     */
    public function find(int $staffId): ?array
    {
        $response = $this->client->get('staff/staff', [
            'StaffIds' => [$staffId],
        ]);

        $staff = $this->extractResultsFromResponse($response);

        if (empty($staff)) {
            return null;
        }

        return $this->transformRecord($staff[0]);
    }

    /**
     * Find staff members by multiple IDs.
     */
    public function findMany(array $staffIds): array
    {
        if (empty($staffIds)) {
            return [];
        }

        $response = $this->client->get('staff/staff', [
            'StaffIds' => $staffIds,
        ]);

        $staff = $this->extractResultsFromResponse($response);

        return $this->transformRecords($staff);
    }

    /**
     * Search staff by various criteria.
     */
    public function search(array $criteria = []): array
    {
        $params = [];

        if (isset($criteria['location_id'])) {
            $params['LocationIds'] = [$criteria['location_id']];
        }

        if (isset($criteria['filters'])) {
            $params['Filters'] = $criteria['filters'];
        }

        if (isset($criteria['session_type_id'])) {
            $params['SessionTypeIds'] = [$criteria['session_type_id']];
        }

        if (isset($criteria['is_active'])) {
            // Note: API typically filters active staff by default
            $params['Filters'] = $criteria['is_active'] ? null : ['InactiveOnly'];
        }

        return $this->all($params);
    }

    /**
     * Get active staff members only.
     */
    public function active(array $params = []): array
    {
        // Active staff are returned by default, no special filtering needed
        return $this->all($params);
    }

    /**
     * Get inactive staff members.
     */
    public function inactive(array $params = []): array
    {
        $params['Filters'] = ['InactiveOnly'];

        return $this->all($params);
    }

    /**
     * Get staff by location.
     */
    public function byLocation(int $locationId, array $params = []): array
    {
        $params['LocationIds'] = [$locationId];

        return $this->all($params);
    }

    /**
     * Get instructors (staff who can teach classes).
     */
    public function instructors(array $params = []): array
    {
        $allStaff = $this->all($params);

        // Filter for staff who are instructors
        return array_filter($allStaff, static function ($staff) {
            return ($staff['IsInstructor'] ?? false) === true;
        });
    }

    /**
     * Get staff availability.
     */
    public function availability(int $staffId, Carbon $startDate, Carbon $endDate, array $params = []): array
    {
        $params = array_merge([
            'StaffId' => $staffId,
            'StartDate' => $this->formatDateOnly($startDate),
            'EndDate' => $this->formatDateOnly($endDate),
        ], $this->prepareParams($params));

        $response = $this->client->get('staff/staffavailability', $params);

        return $response['AvailableTimes'] ?? [];
    }

    /**
     * Get staff permissions.
     */
    public function permissions(int $staffId): array
    {
        $response = $this->client->get('staff/staffpermissions', [
            'StaffId' => $staffId,
        ]);

        return $response['StaffPermissions'] ?? [];
    }

    /**
     * Get staff schedule/appointments.
     */
    public function schedule(int $staffId, Carbon $startDate, Carbon $endDate, array $params = []): array
    {
        $params = array_merge([
            'StaffIds' => [$staffId],
            'StartDate' => $this->formatDateOnly($startDate),
            'EndDate' => $this->formatDateOnly($endDate),
        ], $this->prepareParams($params));

        // Get appointments for this staff member
        $appointments = $this->client->appointment->search([
            'staff_id' => $staffId,
            'start_date' => $startDate,
            'end_date' => $endDate,
        ]);

        // Get classes for this staff member
        $classes = $this->client->class->byInstructor($staffId, [
            'StartDateTime' => $this->formatDate($startDate),
            'EndDateTime' => $this->formatDate($endDate),
        ]);

        return [
            'Appointments' => $appointments,
            'Classes' => $classes,
        ];
    }

    /**
     * Get staff work hours/shifts.
     */
    public function workHours(int $staffId, array $params = []): array
    {
        $params = array_merge([
            'StaffId' => $staffId,
        ], $this->prepareParams($params));

        $response = $this->client->get('staff/staffhours', $params);

        return $response['StaffHours'] ?? [];
    }

    /**
     * Update staff information.
     */
    public function update(int $staffId, array $data): array
    {
        $staffData = $this->validateStaffData($data);
        $staffData['Id'] = $staffId;

        $response = $this->client->post('staff/updatestaff', [
            'Staff' => $staffData,
        ]);

        $this->clearCache();

        if (isset($response['Staff'])) {
            return $this->transformRecord($response['Staff']);
        }

        return $response;
    }

    /**
     * Add new staff member.
     */
    public function create(array $data): array
    {
        $this->validateRequired($data, ['FirstName', 'LastName']);

        $staffData = $this->validateStaffData($data);

        $response = $this->client->post('staff/addstaff', [
            'Staff' => $staffData,
        ]);

        $this->clearCache();

        if (isset($response['Staff'])) {
            return $this->transformRecord($response['Staff']);
        }

        return $response;
    }

    /**
     * Deactivate a staff member.
     */
    public function deactivate(int $staffId): array
    {
        return $this->update($staffId, ['Active' => false]);
    }

    /**
     * Reactivate a staff member.
     */
    public function reactivate(int $staffId): array
    {
        return $this->update($staffId, ['Active' => true]);
    }

    /**
     * Get staff bio/profile information.
     */
    public function profile(int $staffId): array
    {
        $staff = $this->find($staffId);

        if (! $staff) {
            return [];
        }

        // Return profile-specific information
        return [
            'Id' => $staff['Id'] ?? null,
            'FirstName' => $staff['FirstName'] ?? '',
            'LastName' => $staff['LastName'] ?? '',
            'Biography' => $staff['Biography'] ?? '',
            'Education' => $staff['Education'] ?? '',
            'Experience' => $staff['Experience'] ?? '',
            'Certifications' => $staff['Certifications'] ?? [],
            'ImageUrl' => $staff['ImageUrl'] ?? '',
            'IsInstructor' => $staff['IsInstructor'] ?? false,
        ];
    }

    /**
     * Get staff by session type (who can provide specific services).
     */
    public function bySessionType(int $sessionTypeId, array $params = []): array
    {
        $params['SessionTypeIds'] = [$sessionTypeId];

        return $this->all($params);
    }

    /**
     * Get staff certifications.
     */
    public function certifications(int $staffId): array
    {
        $staff = $this->find($staffId);

        return $staff['Certifications'] ?? [];
    }

    /**
     * Get staff pay rates (if accessible).
     */
    public function payRates(int $staffId): array
    {
        $response = $this->client->get('staff/staffpayrates', [
            'StaffId' => $staffId,
        ]);

        return $response['PayRates'] ?? [];
    }

    /**
     * Set staff availability.
     */
    public function setAvailability(int $staffId, array $availability): array
    {
        $response = $this->client->post('staff/setstaffavailability', [
            'StaffId' => $staffId,
            'Availability' => $availability,
        ]);

        $this->clearCache();

        return $response;
    }

    /**
     * Get staff time off/unavailable periods.
     */
    public function timeOff(int $staffId, array $params = []): array
    {
        $params = array_merge([
            'StaffId' => $staffId,
        ], $this->prepareParams($params));

        $response = $this->client->get('staff/stafftimeoff', $params);

        return $response['TimeOff'] ?? [];
    }

    /**
     * Add time off for staff member.
     */
    public function addTimeOff(int $staffId, Carbon $startDate, Carbon $endDate, string $reason = ''): array
    {
        $response = $this->client->post('staff/addstafftimeoff', [
            'StaffId' => $staffId,
            'StartDate' => $this->formatDateOnly($startDate),
            'EndDate' => $this->formatDateOnly($endDate),
            'Reason' => $reason,
        ]);

        $this->clearCache();

        return $response;
    }

    /**
     * Get staff sales performance.
     */
    public function salesPerformance(int $staffId, Carbon $startDate, Carbon $endDate): array
    {
        // This would require accessing sale data filtered by staff
        $sales = $this->client->sale->search([
            'start_date' => $startDate,
            'end_date' => $endDate,
        ]);

        // Filter sales by staff member if that information is available
        $staffSales = array_filter($sales, static function ($sale) use ($staffId) {
            return ($sale['StaffId'] ?? null) === $staffId;
        });

        // Calculate basic metrics
        $totalSales = \count($staffSales);
        $totalRevenue = array_sum(array_column($staffSales, 'SaleTotal'));

        return [
            'staff_id' => $staffId,
            'period' => [
                'start' => $startDate->format('Y-m-d'),
                'end' => $endDate->format('Y-m-d'),
            ],
            'total_sales' => $totalSales,
            'total_revenue' => $totalRevenue,
            'average_sale' => $totalSales > 0 ? $totalRevenue / $totalSales : 0,
            'sales' => $staffSales,
        ];
    }

    /**
     * Extract results from API response.
     */
    protected function extractResultsFromResponse(array $response): array
    {
        return $response['StaffMembers'] ?? [];
    }

    /**
     * Prepare staff-specific parameters.
     */
    protected function prepareStaffParams(array $params): array
    {
        // Handle array parameters
        $arrayParams = ['StaffIds', 'LocationIds', 'SessionTypeIds', 'Filters'];
        foreach ($arrayParams as $param) {
            if (isset($params[$param]) && ! \is_array($params[$param])) {
                $params[$param] = [$params[$param]];
            }
        }

        return $this->prepareParams($params);
    }

    /**
     * Validate and prepare staff data.
     */
    protected function validateStaffData(array $data): array
    {
        $validationRules = [
            'Email' => ['type' => 'email'],
            'MobilePhone' => ['type' => 'phone'],
            'FirstName' => ['type' => 'length', 'options' => ['min' => 1, 'max' => 40]],
            'LastName' => ['type' => 'length', 'options' => ['min' => 1, 'max' => 40]],
        ];

        $data = $this->validateData($data, $validationRules);

        // Map common field aliases
        $fieldMappings = [
            'first_name' => 'FirstName',
            'last_name' => 'LastName',
            'email' => 'Email',
            'phone' => 'MobilePhone',
            'mobile_phone' => 'MobilePhone',
            'is_active' => 'Active',
            'is_instructor' => 'IsInstructor',
            'biography' => 'Biography',
            'education' => 'Education',
            'experience' => 'Experience',
            'image_url' => 'ImageUrl',
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
     * Get date fields specific to staff.
     */
    protected function getDateFields(): array
    {
        return array_merge(parent::getDateFields(), [
            'HireDate',
            'TerminationDate',
            'LastModifiedDateTime',
            'CreationDate',
        ]);
    }

    /**
     * Process bulk staff operations.
     */
    protected function processBulkBatch(string $operation, array $batch): array
    {
        switch ($operation) {
            case 'create':
                return $this->bulkCreate($batch);
            case 'update':
                return $this->bulkUpdate($batch);
            case 'deactivate':
                return $this->bulkDeactivate($batch);
            default:
                throw new \InvalidArgumentException("Unsupported bulk operation: {$operation}");
        }
    }

    /**
     * Bulk create staff members.
     */
    protected function bulkCreate(array $staffMembers): array
    {
        $results = [];

        foreach ($staffMembers as $staffData) {
            try {
                $results[] = $this->create($staffData);
            } catch (\Exception $e) {
                $results[] = [
                    'success' => false,
                    'error' => $e->getMessage(),
                    'data' => $staffData,
                ];
            }
        }

        return $results;
    }

    /**
     * Bulk update staff members.
     */
    protected function bulkUpdate(array $updates): array
    {
        $results = [];

        foreach ($updates as $update) {
            if (! isset($update['Id'])) {
                $results[] = [
                    'success' => false,
                    'error' => 'Staff ID is required for updates',
                    'data' => $update,
                ];
                continue;
            }

            try {
                $staffId = $update['Id'];
                unset($update['Id']);
                $results[] = $this->update($staffId, $update);
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

    /**
     * Bulk deactivate staff members.
     */
    protected function bulkDeactivate(array $staffIds): array
    {
        $results = [];

        foreach ($staffIds as $staffId) {
            try {
                $results[] = $this->deactivate($staffId);
            } catch (\Exception $e) {
                $results[] = [
                    'success' => false,
                    'error' => $e->getMessage(),
                    'staff_id' => $staffId,
                ];
            }
        }

        return $results;
    }
}
