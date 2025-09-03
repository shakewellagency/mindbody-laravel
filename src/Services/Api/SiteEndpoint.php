<?php

declare(strict_types=1);

namespace Shakewell\MindbodyLaravel\Services\Api;

/**
 * Site endpoint for managing site information, locations, programs, and resources.
 */
class SiteEndpoint extends BaseEndpoint
{
    protected string $endpoint = 'site';

    /**
     * Get all sites accessible to the current API key.
     */
    public function all(array $params = []): array
    {
        $params = $this->prepareParams($params);

        $results = $this->getAll('site/sites', $params);

        return $this->transformRecords($results);
    }

    /**
     * Find a specific site by ID.
     */
    public function find(int $siteId): ?array
    {
        $response = $this->client->get('site/sites', [
            'SiteIds' => [$siteId],
        ]);

        $sites = $this->extractResultsFromResponse($response);

        if (empty($sites)) {
            return null;
        }

        return $this->transformRecord($sites[0]);
    }

    /**
     * Get current site information.
     */
    public function current(): array
    {
        $siteId = $this->client->getConfig('api.site_id');

        if (! $siteId) {
            throw new \InvalidArgumentException('Site ID not configured');
        }

        $site = $this->find((int) $siteId);

        return $site ?? [];
    }

    /**
     * Get all locations for the site.
     */
    public function locations(array $params = []): array
    {
        $params = $this->prepareParams($params);

        $response = $this->client->get('site/locations', $params);

        return $response['Locations'] ?? [];
    }

    /**
     * Find a specific location by ID.
     */
    public function findLocation(int $locationId): ?array
    {
        $response = $this->client->get('site/locations', [
            'LocationIds' => [$locationId],
        ]);

        $locations = $response['Locations'] ?? [];

        if (empty($locations)) {
            return null;
        }

        return $locations[0];
    }

    /**
     * Get locations by various criteria.
     */
    public function searchLocations(array $criteria = []): array
    {
        $params = [];

        if (isset($criteria['name'])) {
            // Note: API may not support name filtering directly
            $allLocations = $this->locations();

            return array_filter($allLocations, static function ($location) use ($criteria) {
                return stripos($location['Name'] ?? '', $criteria['name']) !== false;
            });
        }

        if (isset($criteria['is_active'])) {
            $allLocations = $this->locations();

            return array_filter($allLocations, static function ($location) use ($criteria) {
                return ($location['Active'] ?? true) === $criteria['is_active'];
            });
        }

        return $this->locations($params);
    }

    /**
     * Get active locations only.
     */
    public function activeLocations(): array
    {
        return $this->searchLocations(['is_active' => true]);
    }

    /**
     * Get all programs.
     */
    public function programs(array $params = []): array
    {
        $params = $this->prepareParams($params);

        $response = $this->client->get('site/programs', $params);

        return $response['Programs'] ?? [];
    }

    /**
     * Find a specific program by ID.
     */
    public function findProgram(int $programId): ?array
    {
        $response = $this->client->get('site/programs', [
            'ProgramIds' => [$programId],
        ]);

        $programs = $response['Programs'] ?? [];

        if (empty($programs)) {
            return null;
        }

        return $programs[0];
    }

    /**
     * Get session types (services offered).
     */
    public function sessionTypes(array $params = []): array
    {
        $params = $this->prepareParams($params);

        $response = $this->client->get('site/sessiontypes', $params);

        return $response['SessionTypes'] ?? [];
    }

    /**
     * Find a specific session type by ID.
     */
    public function findSessionType(int $sessionTypeId): ?array
    {
        $response = $this->client->get('site/sessiontypes', [
            'SessionTypeIds' => [$sessionTypeId],
        ]);

        $sessionTypes = $response['SessionTypes'] ?? [];

        if (empty($sessionTypes)) {
            return null;
        }

        return $sessionTypes[0];
    }

    /**
     * Get session types by program.
     */
    public function sessionTypesByProgram(int $programId): array
    {
        $response = $this->client->get('site/sessiontypes', [
            'ProgramIds' => [$programId],
        ]);

        return $response['SessionTypes'] ?? [];
    }

    /**
     * Get resources (rooms, equipment, etc.).
     */
    public function resources(array $params = []): array
    {
        $params = $this->prepareParams($params);

        $response = $this->client->get('site/resources', $params);

        return $response['Resources'] ?? [];
    }

    /**
     * Find a specific resource by ID.
     */
    public function findResource(int $resourceId): ?array
    {
        $response = $this->client->get('site/resources', [
            'ResourceIds' => [$resourceId],
        ]);

        $resources = $response['Resources'] ?? [];

        if (empty($resources)) {
            return null;
        }

        return $resources[0];
    }

    /**
     * Get resources by location.
     */
    public function resourcesByLocation(int $locationId): array
    {
        $response = $this->client->get('site/resources', [
            'LocationId' => $locationId,
        ]);

        return $response['Resources'] ?? [];
    }

    /**
     * Get activation code (for mobile apps or integrations).
     */
    public function activationCode(): array
    {
        return $this->client->get('site/activationcode');
    }

    /**
     * Get site membership plans/types.
     */
    public function membershipTypes(array $params = []): array
    {
        // This typically comes from the sale/services endpoint
        $services = $this->client->sale->services($params);

        // Filter for membership types
        return array_filter($services, static function ($service) {
            return ($service['Type'] ?? '') === 'Membership';
        });
    }

    /**
     * Get site package types.
     */
    public function packageTypes(array $params = []): array
    {
        // This typically comes from the sale/services endpoint
        $services = $this->client->sale->services($params);

        // Filter for package types
        return array_filter($services, static function ($service) {
            return ($service['Type'] ?? '') === 'Package';
        });
    }

    /**
     * Get class descriptions/types offered by the site.
     */
    public function classDescriptions(array $params = []): array
    {
        return $this->client->class->descriptions($params);
    }

    /**
     * Get site policies and settings.
     */
    public function policies(): array
    {
        // This information is typically embedded in other responses
        // or requires specific endpoints that may not be publicly available
        $siteInfo = $this->current();

        return [
            'cancellation_policy' => $siteInfo['CancellationPolicy'] ?? '',
            'late_cancel_hours' => $siteInfo['LateCancelHours'] ?? 24,
            'booking_window_hours' => $siteInfo['BookingWindowHours'] ?? 24,
            'pricing_levels' => $siteInfo['PricingLevels'] ?? [],
            'accept_visa' => $siteInfo['AcceptVisa'] ?? false,
            'accept_discover' => $siteInfo['AcceptDiscover'] ?? false,
            'accept_mastercard' => $siteInfo['AcceptMasterCard'] ?? false,
            'accept_amex' => $siteInfo['AcceptAmex'] ?? false,
        ];
    }

    /**
     * Get site contact information.
     */
    public function contactInfo(): array
    {
        $siteInfo = $this->current();

        return [
            'name' => $siteInfo['Name'] ?? '',
            'description' => $siteInfo['Description'] ?? '',
            'logo_url' => $siteInfo['LogoURL'] ?? '',
            'page_color1' => $siteInfo['PageColor1'] ?? '',
            'page_color2' => $siteInfo['PageColor2'] ?? '',
            'page_color3' => $siteInfo['PageColor3'] ?? '',
            'page_color4' => $siteInfo['PageColor4'] ?? '',
            'contact_email' => $siteInfo['ContactEmail'] ?? '',
            'phone_number' => $siteInfo['PhoneNumber'] ?? '',
        ];
    }

    /**
     * Get site business hours.
     */
    public function businessHours(): array
    {
        $locations = $this->locations();

        $businessHours = [];

        foreach ($locations as $location) {
            if (isset($location['BusinessHours'])) {
                $businessHours[$location['Id']] = [
                    'location_name' => $location['Name'] ?? '',
                    'hours' => $location['BusinessHours'],
                ];
            }
        }

        return $businessHours;
    }

    /**
     * Get pricing levels.
     */
    public function pricingLevels(): array
    {
        $siteInfo = $this->current();

        return $siteInfo['PricingLevels'] ?? [];
    }

    /**
     * Get payment methods accepted by the site.
     */
    public function paymentMethods(): array
    {
        return $this->client->sale->paymentMethods();
    }

    /**
     * Get site statistics/summary.
     */
    public function statistics(): array
    {
        // This would aggregate information from various endpoints
        $locations = $this->locations();
        $programs = $this->programs();
        $sessionTypes = $this->sessionTypes();
        $resources = $this->resources();

        return [
            'total_locations' => \count($locations),
            'active_locations' => \count($this->activeLocations()),
            'total_programs' => \count($programs),
            'total_session_types' => \count($sessionTypes),
            'total_resources' => \count($resources),
            'last_updated' => now()->toIso8601String(),
        ];
    }

    /**
     * Test if site is properly configured and accessible.
     */
    public function healthCheck(): array
    {
        $checks = [];

        try {
            $siteInfo = $this->current();
            $checks['site_info'] = ['status' => 'ok', 'message' => 'Site information retrieved'];
        } catch (\Exception $e) {
            $checks['site_info'] = ['status' => 'error', 'message' => $e->getMessage()];
        }

        try {
            $locations = $this->locations();
            $checks['locations'] = ['status' => 'ok', 'count' => \count($locations)];
        } catch (\Exception $e) {
            $checks['locations'] = ['status' => 'error', 'message' => $e->getMessage()];
        }

        try {
            $programs = $this->programs();
            $checks['programs'] = ['status' => 'ok', 'count' => \count($programs)];
        } catch (\Exception $e) {
            $checks['programs'] = ['status' => 'error', 'message' => $e->getMessage()];
        }

        $overallStatus = array_reduce($checks, static function ($carry, $check) {
            return $carry && ($check['status'] === 'ok');
        }, true);

        return [
            'overall_status' => $overallStatus ? 'healthy' : 'unhealthy',
            'timestamp' => now()->toIso8601String(),
            'checks' => $checks,
        ];
    }

    /**
     * Extract results from API response.
     */
    protected function extractResultsFromResponse(array $response): array
    {
        return $response['Sites'] ?? [];
    }

    /**
     * Get date fields specific to sites.
     */
    protected function getDateFields(): array
    {
        return array_merge(parent::getDateFields(), [
            'CreationDate',
            'LastModifiedDateTime',
        ]);
    }

    /**
     * Site endpoint doesn't typically support bulk operations.
     */
    protected function processBulkBatch(string $operation, array $batch): array
    {
        throw new \BadMethodCallException('Bulk operations are not supported for the Site endpoint');
    }
}
