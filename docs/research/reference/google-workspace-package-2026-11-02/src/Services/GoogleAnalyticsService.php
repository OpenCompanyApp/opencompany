<?php

namespace OpenCompany\Integrations\Google\Services;

use OpenCompany\Integrations\Google\GoogleClient;

/**
 * HTTP client wrapper for Google Analytics Admin and Data APIs.
 *
 * Builds GA4 report request bodies and delegates authenticated HTTP calls to GoogleClient.
 */
class GoogleAnalyticsService
{
    private string $dataApi;

    private string $adminApi;

    /** @var array<string, string> Operator mapping for dimension string filters */
    private const STRING_OPERATORS = [
        'exact' => 'EXACT',
        'contains' => 'CONTAINS',
        'begins_with' => 'BEGINS_WITH',
        'ends_with' => 'ENDS_WITH',
        'regex' => 'FULL_REGEXP',
    ];

    /** @var array<string, string> Operator mapping for metric numeric filters */
    private const NUMERIC_OPERATORS = [
        'equal' => 'EQUAL',
        'less_than' => 'LESS_THAN',
        'less_than_or_equal' => 'LESS_THAN_OR_EQUAL',
        'greater_than' => 'GREATER_THAN',
        'greater_than_or_equal' => 'GREATER_THAN_OR_EQUAL',
    ];

    /**
     * @param  GoogleClient  $client  Shared Google OAuth API client.
     * @param  string  $dataApi  Google Analytics Data API base URL.
     * @param  string  $adminApi  Google Analytics Admin API base URL.
     */
    public function __construct(
        private GoogleClient $client,
        string $dataApi = 'https://analyticsdata.googleapis.com/v1beta',
        string $adminApi = 'https://analyticsadmin.googleapis.com/v1beta',
    ) {
        $this->dataApi = rtrim($dataApi, '/');
        $this->adminApi = rtrim($adminApi, '/');
    }

    /**
     * Determine whether OAuth credentials are available.
     */
    public function isConfigured(): bool
    {
        return $this->client->isConfigured();
    }

    // ─── Admin API ───

    /**
     * List all accessible GA4 account summaries (accounts + properties in one call).
     *
     * @return array<string, mixed>
     */
    public function listAccountSummaries(string $pageToken = ''): array
    {
        $query = ['pageSize' => '200'];
        if ($pageToken !== '') {
            $query['pageToken'] = $pageToken;
        }

        return $this->client->get($this->adminApi . '/accountSummaries', $query);
    }

    // ─── Data API ───

    /**
     * Run a standard analytics report.
     *
     * @param  array<string, mixed>  $body
     * @return array<string, mixed>
     */
    public function runReport(string $propertyId, array $body): array
    {
        return $this->client->post(
            $this->dataApi . '/properties/' . rawurlencode($propertyId) . ':runReport',
            $body
        );
    }

    /**
     * Run multiple standard analytics reports in one request.
     *
     * @param  array<string, mixed>  $body
     * @return array<string, mixed>
     */
    public function batchRunReports(string $propertyId, array $body): array
    {
        return $this->client->post(
            $this->dataApi . '/properties/' . rawurlencode($propertyId) . ':batchRunReports',
            $body
        );
    }

    /**
     * Run a GA4 pivot report.
     *
     * @param  array<string, mixed>  $body
     * @return array<string, mixed>
     */
    public function runPivotReport(string $propertyId, array $body): array
    {
        return $this->client->post(
            $this->dataApi . '/properties/' . rawurlencode($propertyId) . ':runPivotReport',
            $body
        );
    }

    /**
     * Run multiple GA4 pivot reports in one request.
     *
     * @param  array<string, mixed>  $body
     * @return array<string, mixed>
     */
    public function batchRunPivotReports(string $propertyId, array $body): array
    {
        return $this->client->post(
            $this->dataApi . '/properties/' . rawurlencode($propertyId) . ':batchRunPivotReports',
            $body
        );
    }

    /**
     * Run a realtime report.
     *
     * @param  array<string, mixed>  $body
     * @return array<string, mixed>
     */
    public function runRealtimeReport(string $propertyId, array $body): array
    {
        return $this->client->post(
            $this->dataApi . '/properties/' . rawurlencode($propertyId) . ':runRealtimeReport',
            $body
        );
    }

    /**
     * Check dimension and metric compatibility for a GA4 report request.
     *
     * @param  array<string, mixed>  $body
     * @return array<string, mixed>
     */
    public function checkCompatibility(string $propertyId, array $body): array
    {
        return $this->client->post(
            $this->dataApi . '/properties/' . rawurlencode($propertyId) . ':checkCompatibility',
            $body
        );
    }

    /**
     * Get available dimensions and metrics for a property.
     *
     * @return array<string, mixed>
     */
    public function getMetadata(string $propertyId): array
    {
        return $this->client->get(
            $this->dataApi . '/properties/' . rawurlencode($propertyId) . '/metadata'
        );
    }

    // ─── Request Body Builders ───

    /**
     * Build a report request body from tool parameters.
     *
     * @param  array<string, mixed>  $params
     * @return array<string, mixed>
     */
    public function buildReportBody(array $params): array
    {
        $body = [];

        // Dimensions
        $dimensions = $params['dimensions'] ?? [];
        if (is_array($dimensions) && ! empty($dimensions)) {
            $body['dimensions'] = array_map(
                fn (string $name) => ['name' => $name],
                array_values($dimensions)
            );
        }

        // Metrics (required)
        $metrics = $params['metrics'] ?? [];
        if (is_array($metrics) && ! empty($metrics)) {
            $body['metrics'] = array_map(
                fn (string $name) => ['name' => $name],
                array_values($metrics)
            );
        }

        // Date ranges
        $startDate = $params['startDate'] ?? '28daysAgo';
        $endDate = $params['endDate'] ?? 'yesterday';
        $dateRanges = [['startDate' => $startDate, 'endDate' => $endDate]];

        // Comparison period
        $compareStart = $params['compareStartDate'] ?? '';
        $compareEnd = $params['compareEndDate'] ?? '';
        if ($compareStart !== '' && $compareEnd !== '') {
            $dateRanges[] = ['startDate' => $compareStart, 'endDate' => $compareEnd];
        }

        $body['dateRanges'] = $dateRanges;

        // Dimension filters
        $filters = $params['filters'] ?? [];
        if (is_array($filters) && ! empty($filters)) {
            $body['dimensionFilter'] = $this->buildDimensionFilter($filters);
        }

        // Metric filters
        $metricFilters = $params['metricFilters'] ?? [];
        if (is_array($metricFilters) && ! empty($metricFilters)) {
            $body['metricFilter'] = $this->buildMetricFilter($metricFilters);
        }

        // Ordering
        $orderBy = $params['orderBy'] ?? '';
        if ($orderBy !== '' && is_string($orderBy)) {
            $desc = ($params['orderDirection'] ?? 'desc') === 'desc';
            $isMetric = is_array($metrics) && in_array($orderBy, $metrics, true);

            $orderSpec = ['desc' => $desc];
            if ($isMetric) {
                $orderSpec['metric'] = ['metricName' => $orderBy];
            } else {
                $orderSpec['dimension'] = ['dimensionName' => $orderBy];
            }
            $body['orderBys'] = [$orderSpec];
        }

        // Pagination
        $limit = isset($params['limit']) ? min((int) $params['limit'], 250000) : 10;
        $body['limit'] = $limit;

        $offset = (int) ($params['offset'] ?? 0);
        if ($offset > 0) {
            $body['offset'] = $offset;
        }

        return $body;
    }

    /**
     * Build a realtime report request body.
     *
     * @param  array<string, mixed>  $params
     * @return array<string, mixed>
     */
    public function buildRealtimeBody(array $params): array
    {
        $body = [];

        $dimensions = $params['dimensions'] ?? [];
        if (is_array($dimensions) && ! empty($dimensions)) {
            $body['dimensions'] = array_map(
                fn (string $name) => ['name' => $name],
                array_values($dimensions)
            );
        }

        $metrics = $params['metrics'] ?? ['activeUsers'];
        if (is_array($metrics) && ! empty($metrics)) {
            $body['metrics'] = array_map(
                fn (string $name) => ['name' => $name],
                array_values($metrics)
            );
        }

        $limit = isset($params['limit']) ? min((int) $params['limit'], 250000) : 10;
        $body['limit'] = $limit;

        return $body;
    }

    /**
     * Build a pivot report request body from tool parameters.
     *
     * @param  array<string, mixed>  $params
     * @return array<string, mixed>
     */
    public function buildPivotReportBody(array $params): array
    {
        $body = $this->buildReportBody($params);
        $dimensions = $params['dimensions'] ?? [];
        $pivots = $params['pivots'] ?? [];

        if (is_array($pivots) && ! empty($pivots)) {
            $body['pivots'] = $pivots;
        } elseif (is_array($dimensions) && ! empty($dimensions)) {
            $body['pivots'] = [[
                'fieldNames' => array_values($dimensions),
                'limit' => isset($params['limit']) ? min((int) $params['limit'], 250000) : 10,
            ]];
        }

        unset($body['limit'], $body['offset']);

        return $body;
    }

    /**
     * Build a compatibility request body from tool parameters.
     *
     * @param  array<string, mixed>  $params
     * @return array<string, mixed>
     */
    public function buildCompatibilityBody(array $params): array
    {
        $body = [];

        $dimensions = $params['dimensions'] ?? [];
        if (is_array($dimensions) && ! empty($dimensions)) {
            $body['dimensions'] = array_map(
                fn (string $name) => ['name' => $name],
                array_values($dimensions)
            );
        }

        $metrics = $params['metrics'] ?? [];
        if (is_array($metrics) && ! empty($metrics)) {
            $body['metrics'] = array_map(
                fn (string $name) => ['name' => $name],
                array_values($metrics)
            );
        }

        $filters = $params['filters'] ?? [];
        if (is_array($filters) && ! empty($filters)) {
            $body['dimensionFilter'] = $this->buildDimensionFilter($filters);
        }

        $metricFilters = $params['metricFilters'] ?? [];
        if (is_array($metricFilters) && ! empty($metricFilters)) {
            $body['metricFilter'] = $this->buildMetricFilter($metricFilters);
        }

        $compatibilityFilter = $params['compatibilityFilter'] ?? '';
        if (is_string($compatibilityFilter) && $compatibilityFilter !== '') {
            $body['compatibilityFilter'] = $compatibilityFilter;
        }

        return $body;
    }

    /**
     * Build a dimension filter expression (AND group).
     *
     * @param  array<int, mixed>  $filters
     * @return array<string, mixed>
     */
    private function buildDimensionFilter(array $filters): array
    {
        $expressions = [];

        foreach ($filters as $filter) {
            if (! is_array($filter) || ! isset($filter['dimension'], $filter['value'])) {
                continue;
            }

            $dimension = (string) $filter['dimension'];
            $operator = (string) ($filter['operator'] ?? 'exact');
            $value = $filter['value'];

            // Handle in_list operator separately
            if ($operator === 'in_list') {
                $values = is_array($value) ? $value : explode(',', (string) $value);
                $expressions[] = [
                    'filter' => [
                        'fieldName' => $dimension,
                        'inListFilter' => [
                            'values' => array_map('trim', $values),
                        ],
                    ],
                ];

                continue;
            }

            $matchType = self::STRING_OPERATORS[$operator] ?? 'EXACT';
            $expressions[] = [
                'filter' => [
                    'fieldName' => $dimension,
                    'stringFilter' => [
                        'matchType' => $matchType,
                        'value' => (string) $value,
                    ],
                ],
            ];
        }

        if (count($expressions) === 1) {
            return $expressions[0];
        }

        return ['andGroup' => ['expressions' => $expressions]];
    }

    /**
     * Build a metric filter expression (AND group).
     *
     * @param  array<int, mixed>  $filters
     * @return array<string, mixed>
     */
    private function buildMetricFilter(array $filters): array
    {
        $expressions = [];

        foreach ($filters as $filter) {
            if (! is_array($filter) || ! isset($filter['metric'], $filter['value'])) {
                continue;
            }

            $metric = (string) $filter['metric'];
            $operator = (string) ($filter['operator'] ?? 'greater_than');
            $numericValue = $filter['value'];

            $operation = self::NUMERIC_OPERATORS[$operator] ?? 'GREATER_THAN';

            // Determine value type
            $valueSpec = is_float($numericValue) || (is_string($numericValue) && str_contains($numericValue, '.'))
                ? ['doubleValue' => (float) $numericValue]
                : ['int64Value' => (string) (int) $numericValue];

            $expressions[] = [
                'filter' => [
                    'fieldName' => $metric,
                    'numericFilter' => [
                        'operation' => $operation,
                        'value' => $valueSpec,
                    ],
                ],
            ];
        }

        if (count($expressions) === 1) {
            return $expressions[0];
        }

        return ['andGroup' => ['expressions' => $expressions]];
    }
}
