<?php

declare(strict_types=1);

namespace Shakewell\MindbodyLaravel\Services\Api;

use Carbon\Carbon;

/**
 * Client endpoint for managing clients/customers
 */
class ClientEndpoint extends BaseEndpoint
{
    protected string $endpoint = 'client';

    /**
     * Get all clients with optional filtering
     */
    public function all(array $params = []): array
    {
        $params = $this->prepareParams($params);
        
        $results = $this->getAll('client/clients', $params);
        
        return $this->transformRecords($results);
    }

    /**
     * Find a specific client by ID
     */
    public function find(string $clientId): ?array
    {
        $response = $this->client->get('client/clients', [
            'ClientIds' => [$clientId]
        ]);

        $clients = $this->extractResultsFromResponse($response);
        
        if (empty($clients)) {
            return null;
        }

        return $this->transformRecord($clients[0]);
    }

    /**
     * Find clients by multiple IDs
     */
    public function findMany(array $clientIds): array
    {
        if (empty($clientIds)) {
            return [];
        }

        $response = $this->client->get('client/clients', [
            'ClientIds' => $clientIds
        ]);

        $clients = $this->extractResultsFromResponse($response);
        
        return $this->transformRecords($clients);
    }

    /**
     * Search for clients by various criteria
     */
    public function search(array $criteria = []): array
    {
        $params = [];

        // Map search criteria to API parameters
        if (isset($criteria['email'])) {
            $params['SearchText'] = $criteria['email'];
        }
        
        if (isset($criteria['first_name'])) {
            $params['FirstName'] = $criteria['first_name'];
        }
        
        if (isset($criteria['last_name'])) {
            $params['LastName'] = $criteria['last_name'];
        }
        
        if (isset($criteria['phone'])) {
            $params['SearchText'] = $criteria['phone'];
        }

        if (isset($criteria['is_active'])) {
            $params['IsActive'] = $criteria['is_active'];
        }

        if (isset($criteria['created_after'])) {
            $params['CreationDateFrom'] = $this->formatDate($criteria['created_after']);
        }

        if (isset($criteria['created_before'])) {
            $params['CreationDateTo'] = $this->formatDate($criteria['created_before']);
        }

        $params = $this->prepareParams($params);
        
        $results = $this->getAll('client/clients', $params);
        
        return $this->transformRecords($results);
    }

    /**
     * Create a new client
     */
    public function create(array $data): array
    {
        $this->validateRequired($data, ['FirstName', 'LastName']);
        
        $clientData = $this->validateClientData($data);
        
        $response = $this->client->post('client/addclient', [
            'Client' => $clientData,
            'SendAccountEmails' => $data['SendAccountEmails'] ?? true,
            'SendAccountTexts' => $data['SendAccountTexts'] ?? false,
            'SendPromotionalEmails' => $data['SendPromotionalEmails'] ?? true,
            'SendPromotionalTexts' => $data['SendPromotionalTexts'] ?? false,
            'SendScheduleEmails' => $data['SendScheduleEmails'] ?? true,
            'SendScheduleTexts' => $data['SendScheduleTexts'] ?? false,
        ]);

        if (isset($response['Client'])) {
            return $this->transformRecord($response['Client']);
        }

        return $response;
    }

    /**
     * Update an existing client
     */
    public function update(string $clientId, array $data): array
    {
        $clientData = $this->validateClientData($data);
        $clientData['Id'] = $clientId;

        $response = $this->client->post('client/updateclient', [
            'Client' => $clientData,
        ]);

        if (isset($response['Client'])) {
            $this->clearCache();
            return $this->transformRecord($response['Client']);
        }

        return $response;
    }

    /**
     * Get client purchases (packages, memberships, services)
     */
    public function purchases(string $clientId, array $params = []): array
    {
        $params = array_merge([
            'ClientId' => $clientId,
        ], $this->prepareParams($params));

        if (isset($params['start_date'])) {
            $params['StartDate'] = $this->formatDateOnly($params['start_date']);
            unset($params['start_date']);
        }

        if (isset($params['end_date'])) {
            $params['EndDate'] = $this->formatDateOnly($params['end_date']);
            unset($params['end_date']);
        }

        $response = $this->client->get('client/clientpurchases', $params);

        return $response['Purchases'] ?? [];
    }

    /**
     * Get client services (active memberships, packages, etc.)
     */
    public function services(string $clientId, array $params = []): array
    {
        $params = array_merge([
            'ClientId' => $clientId,
        ], $this->prepareParams($params));

        $response = $this->client->get('client/clientservices', $params);

        return $response['ClientServices'] ?? [];
    }

    /**
     * Get client visit history
     */
    public function visits(string $clientId, array $params = []): array
    {
        $params = array_merge([
            'ClientId' => $clientId,
        ], $this->prepareParams($params));

        if (isset($params['start_date'])) {
            $params['StartDate'] = $this->formatDateOnly($params['start_date']);
            unset($params['start_date']);
        }

        if (isset($params['end_date'])) {
            $params['EndDate'] = $this->formatDateOnly($params['end_date']);
            unset($params['end_date']);
        }

        $response = $this->client->get('client/clientvisits', $params);

        return $response['Visits'] ?? [];
    }

    /**
     * Get client schedule (upcoming appointments and classes)
     */
    public function schedule(string $clientId, array $params = []): array
    {
        $params = array_merge([
            'ClientId' => $clientId,
        ], $this->prepareParams($params));

        if (isset($params['start_date'])) {
            $params['StartDate'] = $this->formatDateOnly($params['start_date']);
            unset($params['start_date']);
        }

        if (isset($params['end_date'])) {
            $params['EndDate'] = $this->formatDateOnly($params['end_date']);
            unset($params['end_date']);
        }

        $response = $this->client->get('client/clientschedule', $params);

        return [
            'Appointments' => $response['Appointments'] ?? [],
            'Classes' => $response['Classes'] ?? [],
        ];
    }

    /**
     * Get required client fields for the site
     */
    public function requiredFields(): array
    {
        $response = $this->client->get('client/requiredclientfields');

        return $response['RequiredClientFields'] ?? [];
    }

    /**
     * Get active client memberships
     */
    public function memberships(string $clientId): array
    {
        $response = $this->client->get('client/clientservices', [
            'ClientId' => $clientId,
            'ServiceType' => 'Membership',
        ]);

        return $response['ClientServices'] ?? [];
    }

    /**
     * Get client contracts
     */
    public function contracts(string $clientId, array $params = []): array
    {
        $params = array_merge([
            'ClientId' => $clientId,
        ], $this->prepareParams($params));

        $response = $this->client->get('client/clientcontracts', $params);

        return $response['Contracts'] ?? [];
    }

    /**
     * Add client to a class or service
     */
    public function addToClass(string $clientId, int $classId, array $options = []): array
    {
        $data = array_merge([
            'ClientId' => $clientId,
            'ClassId' => $classId,
            'RequirePayment' => false,
            'Waitlist' => false,
        ], $options);

        return $this->client->post('class/addclienttoclass', $data);
    }

    /**
     * Remove client from a class
     */
    public function removeFromClass(string $clientId, int $classId, bool $sendEmail = true): array
    {
        return $this->client->post('class/removeclientfromclass', [
            'ClientId' => $clientId,
            'ClassId' => $classId,
            'SendEmail' => $sendEmail,
        ]);
    }

    /**
     * Get client's current account balance
     */
    public function balance(string $clientId): array
    {
        $response = $this->client->get('client/clientaccountbalances', [
            'ClientIds' => [$clientId],
        ]);

        $balances = $response['ClientAccountBalances'] ?? [];
        
        return $balances[0] ?? [];
    }

    /**
     * Deactivate a client
     */
    public function deactivate(string $clientId): array
    {
        return $this->update($clientId, ['Active' => false]);
    }

    /**
     * Reactivate a client
     */
    public function reactivate(string $clientId): array
    {
        return $this->update($clientId, ['Active' => true]);
    }

    /**
     * Upload a profile photo for the client
     */
    public function uploadPhoto(string $clientId, string $photoData): array
    {
        return $this->client->post('client/uploadclientphoto', [
            'ClientId' => $clientId,
            'Photo' => $photoData,
        ]);
    }

    /**
     * Extract results from response
     */
    protected function extractResultsFromResponse(array $response): array
    {
        return $response['Clients'] ?? [];
    }

    /**
     * Validate and prepare client data
     */
    protected function validateClientData(array $data): array
    {
        $validationRules = [
            'Email' => ['type' => 'email'],
            'MobilePhone' => ['type' => 'phone'],
            'HomePhone' => ['type' => 'phone'],
            'WorkPhone' => ['type' => 'phone'],
            'FirstName' => ['type' => 'length', 'options' => ['min' => 1, 'max' => 40]],
            'LastName' => ['type' => 'length', 'options' => ['min' => 1, 'max' => 40]],
        ];

        $data = $this->validateData($data, $validationRules);

        // Handle date fields
        if (isset($data['BirthDate'])) {
            $data['BirthDate'] = $this->formatDateOnly($data['BirthDate']);
        }

        // Map common field aliases
        $fieldMappings = [
            'first_name' => 'FirstName',
            'last_name' => 'LastName',
            'email' => 'Email',
            'phone' => 'MobilePhone',
            'mobile_phone' => 'MobilePhone',
            'home_phone' => 'HomePhone',
            'work_phone' => 'WorkPhone',
            'birth_date' => 'BirthDate',
            'address' => 'AddressLine1',
            'address_2' => 'AddressLine2',
            'city' => 'City',
            'state' => 'State',
            'postal_code' => 'PostalCode',
            'country' => 'Country',
            'gender' => 'Gender',
            'is_active' => 'Active',
        ];

        foreach ($fieldMappings as $alias => $field) {
            if (isset($data[$alias]) && !isset($data[$field])) {
                $data[$field] = $data[$alias];
                unset($data[$alias]);
            }
        }

        return $data;
    }

    /**
     * Get date fields specific to clients
     */
    protected function getDateFields(): array
    {
        return array_merge(parent::getDateFields(), [
            'BirthDate',
            'CreationDate',
            'LastModifiedDateTime',
            'FirstAppointmentDate',
            'LatestAgreementDate',
        ]);
    }

    /**
     * Process bulk client operations
     */
    protected function processBulkBatch(string $operation, array $batch): array
    {
        switch ($operation) {
            case 'create':
                return $this->bulkCreate($batch);
            case 'update':
                return $this->bulkUpdate($batch);
            default:
                throw new \InvalidArgumentException("Unsupported bulk operation: {$operation}");
        }
    }

    /**
     * Bulk create clients
     */
    protected function bulkCreate(array $clients): array
    {
        $results = [];
        
        foreach ($clients as $clientData) {
            try {
                $results[] = $this->create($clientData);
            } catch (\Exception $e) {
                $results[] = [
                    'error' => $e->getMessage(),
                    'data' => $clientData,
                ];
            }
        }

        return $results;
    }

    /**
     * Bulk update clients
     */
    protected function bulkUpdate(array $updates): array
    {
        $results = [];
        
        foreach ($updates as $update) {
            if (!isset($update['Id'])) {
                $results[] = [
                    'error' => 'Client ID is required for updates',
                    'data' => $update,
                ];
                continue;
            }

            try {
                $clientId = $update['Id'];
                unset($update['Id']);
                $results[] = $this->update($clientId, $update);
            } catch (\Exception $e) {
                $results[] = [
                    'error' => $e->getMessage(),
                    'data' => $update,
                ];
            }
        }

        return $results;
    }
}