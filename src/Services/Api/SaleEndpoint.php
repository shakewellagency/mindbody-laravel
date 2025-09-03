<?php

declare(strict_types=1);

namespace Shakewell\MindbodyLaravel\Services\Api;

use Carbon\Carbon;

/**
 * Sale endpoint for managing sales, payments, and purchases.
 */
class SaleEndpoint extends BaseEndpoint
{
    protected string $endpoint = 'sale';

    /**
     * Get all sales with optional filtering.
     */
    public function all(array $params = []): array
    {
        $params = $this->prepareSaleParams($params);

        $results = $this->getAll('sale/sales', $params);

        return $this->transformRecords($results);
    }

    /**
     * Find a specific sale by ID.
     */
    public function find(int $saleId): ?array
    {
        $response = $this->client->get('sale/sales', [
            'SaleIds' => [$saleId],
        ]);

        $sales = $this->extractResultsFromResponse($response);

        if (empty($sales)) {
            return null;
        }

        return $this->transformRecord($sales[0]);
    }

    /**
     * Find sales by multiple IDs.
     */
    public function findMany(array $saleIds): array
    {
        if (empty($saleIds)) {
            return [];
        }

        $response = $this->client->get('sale/sales', [
            'SaleIds' => $saleIds,
        ]);

        $sales = $this->extractResultsFromResponse($response);

        return $this->transformRecords($sales);
    }

    /**
     * Process a new sale/checkout.
     */
    public function checkout(array $data): array
    {
        $this->validateRequired($data, ['ClientId', 'Items']);

        $saleData = $this->validateSaleData($data);

        $response = $this->client->post('sale/checkoutshoppingcart', $saleData);

        $this->clearCache();

        if (isset($response['ShoppingSale'])) {
            return $this->transformRecord($response['ShoppingSale']);
        }

        return $response;
    }

    /**
     * Get sales for a specific date range.
     */
    public function forDateRange(Carbon $startDate, Carbon $endDate, array $params = []): array
    {
        $params = array_merge($params, [
            'StartSaleDateTime' => $this->formatDate($startDate),
            'EndSaleDateTime' => $this->formatDate($endDate),
        ]);

        return $this->all($params);
    }

    /**
     * Get today's sales.
     */
    public function today(array $params = []): array
    {
        $today = Carbon::today();

        return $this->forDateRange($today, $today->copy()->endOfDay(), $params);
    }

    /**
     * Get this week's sales.
     */
    public function thisWeek(array $params = []): array
    {
        $startOfWeek = Carbon::now()->startOfWeek();
        $endOfWeek = Carbon::now()->endOfWeek();

        return $this->forDateRange($startOfWeek, $endOfWeek, $params);
    }

    /**
     * Get sales by client.
     */
    public function byClient(string $clientId, array $params = []): array
    {
        $params['ClientId'] = $clientId;

        return $this->all($params);
    }

    /**
     * Get sales by location.
     */
    public function byLocation(int $locationId, array $params = []): array
    {
        $params['LocationId'] = $locationId;

        return $this->all($params);
    }

    /**
     * Search sales by various criteria.
     */
    public function search(array $criteria = []): array
    {
        $params = [];

        if (isset($criteria['client_id'])) {
            $params['ClientId'] = $criteria['client_id'];
        }

        if (isset($criteria['location_id'])) {
            $params['LocationId'] = $criteria['location_id'];
        }

        if (isset($criteria['payment_method'])) {
            $params['PaymentMethodId'] = $criteria['payment_method'];
        }

        if (isset($criteria['start_date'])) {
            $params['StartSaleDateTime'] = $this->formatDate($criteria['start_date']);
        }

        if (isset($criteria['end_date'])) {
            $params['EndSaleDateTime'] = $this->formatDate($criteria['end_date']);
        }

        if (isset($criteria['min_amount'])) {
            $params['MinAmount'] = $criteria['min_amount'];
        }

        if (isset($criteria['max_amount'])) {
            $params['MaxAmount'] = $criteria['max_amount'];
        }

        return $this->all($params);
    }

    /**
     * Get available products.
     */
    public function products(array $params = []): array
    {
        $params = $this->prepareParams($params);

        $response = $this->client->get('sale/products', $params);

        return $response['Products'] ?? [];
    }

    /**
     * Get available services (packages, memberships, etc.).
     */
    public function services(array $params = []): array
    {
        $params = $this->prepareParams($params);

        $response = $this->client->get('sale/services', $params);

        return $response['Services'] ?? [];
    }

    /**
     * Get available packages.
     */
    public function packages(array $params = []): array
    {
        $params = array_merge($params, ['SellOnline' => true]);

        $services = $this->services($params);

        // Filter for packages
        return array_filter($services, static function ($service) {
            return ($service['Type'] ?? '') === 'Package';
        });
    }

    /**
     * Get available memberships.
     */
    public function memberships(array $params = []): array
    {
        $params = array_merge($params, ['SellOnline' => true]);

        $services = $this->services($params);

        // Filter for memberships
        return array_filter($services, static function ($service) {
            return ($service['Type'] ?? '') === 'Membership';
        });
    }

    /**
     * Get gift cards.
     */
    public function giftCards(array $params = []): array
    {
        $params = $this->prepareParams($params);

        $response = $this->client->get('sale/giftcards', $params);

        return $response['GiftCards'] ?? [];
    }

    /**
     * Purchase a gift card.
     */
    public function purchaseGiftCard(array $data): array
    {
        $this->validateRequired($data, ['RecipientName', 'Value']);

        $response = $this->client->post('sale/purchasegiftcard', $data);

        $this->clearCache();

        return $response;
    }

    /**
     * Get contracts.
     */
    public function contracts(array $params = []): array
    {
        $params = $this->prepareParams($params);

        $response = $this->client->get('sale/contracts', $params);

        return $response['Contracts'] ?? [];
    }

    /**
     * Process a return/refund.
     */
    public function processReturn(int $saleId, array $items, string $reason = ''): array
    {
        $data = [
            'SaleId' => $saleId,
            'ReturnItems' => $items,
            'ReturnReason' => $reason,
        ];

        $response = $this->client->post('sale/returnsale', $data);

        $this->clearCache();

        return $response;
    }

    /**
     * Get payment methods.
     */
    public function paymentMethods(): array
    {
        $response = $this->client->get('sale/paymentmethods');

        return $response['PaymentMethods'] ?? [];
    }

    /**
     * Process a custom payment.
     */
    public function processCustomPayment(array $data): array
    {
        $this->validateRequired($data, ['ClientId', 'Amount', 'PaymentMethodId']);

        $response = $this->client->post('sale/custompayment', $data);

        $this->clearCache();

        return $response;
    }

    /**
     * Update a sale.
     */
    public function update(int $saleId, array $data): array
    {
        $saleData = $this->validateSaleData($data);
        $saleData['Id'] = $saleId;

        $response = $this->client->post('sale/updatesale', $saleData);

        $this->clearCache();

        if (isset($response['Sale'])) {
            return $this->transformRecord($response['Sale']);
        }

        return $response;
    }

    /**
     * Get sale summary/statistics.
     */
    public function summary(Carbon $startDate, Carbon $endDate, array $params = []): array
    {
        $params = array_merge($params, [
            'StartDate' => $this->formatDateOnly($startDate),
            'EndDate' => $this->formatDateOnly($endDate),
        ]);

        return $this->client->get('sale/salesummary', $params);
    }

    /**
     * Get transactions for a specific sale.
     */
    public function transactions(int $saleId): array
    {
        $response = $this->client->get('sale/transactions', [
            'SaleId' => $saleId,
        ]);

        return $response['Transactions'] ?? [];
    }

    /**
     * Get refunds/returns.
     */
    public function returns(array $params = []): array
    {
        $params = $this->prepareSaleParams($params);

        $response = $this->client->get('sale/returns', $params);

        return $response['Returns'] ?? [];
    }

    /**
     * Void a sale.
     */
    public function void(int $saleId, string $reason = ''): array
    {
        $response = $this->client->post('sale/voidsale', [
            'SaleId' => $saleId,
            'VoidReason' => $reason,
        ]);

        $this->clearCache();

        return $response;
    }

    /**
     * Get sales tax information.
     */
    public function taxInfo(array $params = []): array
    {
        $response = $this->client->get('sale/taxinfo', $params);

        return $response['TaxInfo'] ?? [];
    }

    /**
     * Calculate tax for items.
     */
    public function calculateTax(array $items, ?string $clientId = null): array
    {
        $data = [
            'Items' => $items,
        ];

        if ($clientId) {
            $data['ClientId'] = $clientId;
        }

        return $this->client->post('sale/calculatetax', $data);
    }

    /**
     * Get discounts.
     */
    public function discounts(array $params = []): array
    {
        $response = $this->client->get('sale/discounts', $params);

        return $response['Discounts'] ?? [];
    }

    /**
     * Apply discount to items.
     */
    public function applyDiscount(array $items, string $discountCode): array
    {
        $data = [
            'Items' => $items,
            'DiscountCode' => $discountCode,
        ];

        return $this->client->post('sale/applydiscount', $data);
    }

    /**
     * Extract results from API response.
     */
    protected function extractResultsFromResponse(array $response): array
    {
        return $response['Sales'] ?? [];
    }

    /**
     * Prepare sale-specific parameters.
     */
    protected function prepareSaleParams(array $params): array
    {
        // Set default date range if not provided
        if (! isset($params['StartSaleDateTime']) && ! isset($params['start_date'])) {
            $params['StartSaleDateTime'] = Carbon::now()->subDays(7)->toIso8601String();
        }

        if (! isset($params['EndSaleDateTime']) && ! isset($params['end_date'])) {
            $params['EndSaleDateTime'] = Carbon::now()->toIso8601String();
        }

        // Handle date aliases
        if (isset($params['start_date'])) {
            $params['StartSaleDateTime'] = $this->formatDate($params['start_date']);
            unset($params['start_date']);
        }

        if (isset($params['end_date'])) {
            $params['EndSaleDateTime'] = $this->formatDate($params['end_date']);
            unset($params['end_date']);
        }

        // Handle array parameters
        $arrayParams = ['SaleIds', 'LocationIds'];
        foreach ($arrayParams as $param) {
            if (isset($params[$param]) && ! \is_array($params[$param])) {
                $params[$param] = [$params[$param]];
            }
        }

        return $this->prepareParams($params);
    }

    /**
     * Validate and prepare sale data.
     */
    protected function validateSaleData(array $data): array
    {
        // Map common field aliases
        $fieldMappings = [
            'client_id' => 'ClientId',
            'location_id' => 'LocationId',
            'payment_method' => 'PaymentMethodId',
            'items' => 'Items',
            'discount_code' => 'DiscountCode',
            'promo_code' => 'PromoCode',
            'notes' => 'Notes',
        ];

        foreach ($fieldMappings as $alias => $field) {
            if (isset($data[$alias]) && ! isset($data[$field])) {
                $data[$field] = $data[$alias];
                unset($data[$alias]);
            }
        }

        // Validate items structure
        if (isset($data['Items']) && \is_array($data['Items'])) {
            foreach ($data['Items'] as &$item) {
                if (isset($item['service_id']) && ! isset($item['Item']['Id'])) {
                    $item['Item']['Id'] = $item['service_id'];
                    unset($item['service_id']);
                }

                if (isset($item['quantity']) && ! isset($item['Quantity'])) {
                    $item['Quantity'] = $item['quantity'];
                    unset($item['quantity']);
                }
            }
        }

        return $data;
    }

    /**
     * Get date fields specific to sales.
     */
    protected function getDateFields(): array
    {
        return array_merge(parent::getDateFields(), [
            'SaleDate',
            'SaleDateTime',
            'StartSaleDateTime',
            'EndSaleDateTime',
            'PurchaseDateTime',
            'RefundDateTime',
        ]);
    }

    /**
     * Process bulk sale operations.
     */
    protected function processBulkBatch(string $operation, array $batch): array
    {
        switch ($operation) {
            case 'checkout':
                return $this->bulkCheckout($batch);
            case 'return':
                return $this->bulkReturn($batch);
            default:
                throw new \InvalidArgumentException("Unsupported bulk operation: {$operation}");
        }
    }

    /**
     * Bulk process checkouts.
     */
    protected function bulkCheckout(array $sales): array
    {
        $results = [];

        foreach ($sales as $saleData) {
            try {
                $results[] = $this->checkout($saleData);
            } catch (\Exception $e) {
                $results[] = [
                    'success' => false,
                    'error' => $e->getMessage(),
                    'data' => $saleData,
                ];
            }
        }

        return $results;
    }

    /**
     * Bulk process returns.
     */
    protected function bulkReturn(array $returns): array
    {
        $results = [];

        foreach ($returns as $returnData) {
            if (! isset($returnData['sale_id'], $returnData['items'])) {
                $results[] = [
                    'success' => false,
                    'error' => 'sale_id and items are required',
                    'data' => $returnData,
                ];

                continue;
            }

            try {
                $results[] = $this->processReturn(
                    $returnData['sale_id'],
                    $returnData['items'],
                    $returnData['reason'] ?? ''
                );
            } catch (\Exception $e) {
                $results[] = [
                    'success' => false,
                    'error' => $e->getMessage(),
                    'data' => $returnData,
                ];
            }
        }

        return $results;
    }
}
