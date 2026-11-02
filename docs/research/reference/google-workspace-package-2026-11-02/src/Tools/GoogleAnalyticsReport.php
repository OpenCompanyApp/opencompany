<?php

namespace OpenCompany\Integrations\Google\Tools;

use OpenCompany\IntegrationCore\Contracts\Tool;
use OpenCompany\IntegrationCore\Support\ToolResult;
use OpenCompany\Integrations\Google\Services\GoogleAnalyticsService;

/**
 * Run a standard GA4 report and normalize tabular rows for agents.
 */
class GoogleAnalyticsReport implements Tool
{
    /**
     * @param  GoogleAnalyticsService  $service  The Google Analytics API client.
     */
    public function __construct(
        private GoogleAnalyticsService $service,
    ) {}

    public function name(): string
    {
        return 'google_analytics_report';
    }

    public function description(): string
    {
        return <<<'MD'
        Run a GA4 analytics report. Returns rows of dimension/metric data for the specified date range.

        Common dimensions: sessionSource, sessionMedium, sessionDefaultChannelGroup (traffic source); pagePath, pageTitle, landingPage (pages); country, city (geo); deviceCategory, browser, operatingSystem (device); date, dateHour, month (time); newVsReturning (user); eventName (events).

        Common metrics: sessions, totalUsers, newUsers, activeUsers (traffic); screenPageViews, bounceRate, averageSessionDuration, engagementRate, sessionsPerUser (engagement); eventCount, conversions (events); purchaseRevenue, totalRevenue (e-commerce).

        Dates: YYYY-MM-DD or relative: "today", "yesterday", "7daysAgo", "28daysAgo", "30daysAgo", "90daysAgo", "365daysAgo".
        Filter operators: exact, contains, begins_with, ends_with, regex, in_list.
        Metric filter operators: equal, less_than, greater_than, less_than_or_equal, greater_than_or_equal.
        MD;
    }

    /**
     * Execute the tool and return a normalized GA4 report.
     *
     * @param  array<string, mixed>  $args  Tool arguments (property_id, metrics, dimensions, dates, filters, ordering, pagination).
     */
    public function execute(array $args): ToolResult
    {
        try {
            if (! $this->service->isConfigured()) {
                return ToolResult::error('Google Analytics integration is not configured.');
            }

            $propertyId = $args['property_id'] ?? '';
            if (empty($propertyId)) {
                return ToolResult::error('propertyId is required. Use google_analytics_list_properties to find your GA4 property ID.');
            }

            $metrics = $args['metrics'] ?? [];
            if (! is_array($metrics) || empty($metrics)) {
                return ToolResult::error('metrics is required (array of metric names, e.g., ["sessions", "totalUsers"]).');
            }

            /** @var array<string, mixed> $params */
            $params = [
                'dimensions' => $args['dimensions'] ?? [],
                'metrics' => $metrics,
                'startDate' => $args['start_date'] ?? '28daysAgo',
                'endDate' => $args['end_date'] ?? 'yesterday',
                'compareStartDate' => $args['compare_start_date'] ?? '',
                'compareEndDate' => $args['compare_end_date'] ?? '',
                'filters' => $args['filters'] ?? [],
                'metricFilters' => $args['metric_filters'] ?? [],
                'orderBy' => $args['order_by'] ?? '',
                'orderDirection' => $args['order_direction'] ?? 'desc',
                'limit' => $args['limit'] ?? 10,
                'offset' => $args['offset'] ?? 0,
            ];

            $body = $this->service->buildReportBody($params);
            $result = $this->service->runReport((string) $propertyId, $body);

            return $this->formatReportResponse($result, $params);
        } catch (\Throwable $e) {
            return ToolResult::error($e->getMessage());
        }
    }

    /**
     * Format a report response as structured JSON.
     *
     * @param  array<string, mixed>  $result
     * @param  array<string, mixed>  $params
     */
    private function formatReportResponse(array $result, array $params): ToolResult
    {
        $rows = $result['rows'] ?? [];
        $dimensionHeaders = $result['dimensionHeaders'] ?? [];
        $metricHeaders = $result['metricHeaders'] ?? [];

        $dimNames = array_map(fn (array $h) => $h['name'] ?? '', $dimensionHeaders);
        $metricNames = array_map(fn (array $h) => $h['name'] ?? '', $metricHeaders);

        $response = [
            'dateRange' => [
                'startDate' => $params['startDate'] ?? '28daysAgo',
                'endDate' => $params['endDate'] ?? 'yesterday',
            ],
        ];

        $compareStart = $params['compareStartDate'] ?? '';
        $compareEnd = $params['compareEndDate'] ?? '';
        if ($compareStart !== '' && $compareEnd !== '') {
            $response['comparisonRange'] = [
                'startDate' => $compareStart,
                'endDate' => $compareEnd,
            ];
        }

        if (empty($rows)) {
            $response['rows'] = [];
            return ToolResult::success($response);
        }

        $data = [];
        foreach ($rows as $row) {
            $entry = [];
            $dimValues = $row['dimensionValues'] ?? [];
            foreach ($dimNames as $i => $name) {
                $entry[$name] = $dimValues[$i]['value'] ?? '';
            }
            $metricValues = $row['metricValues'] ?? [];
            foreach ($metricNames as $i => $name) {
                $raw = $metricValues[$i]['value'] ?? '0';
                $entry[$name] = is_numeric($raw) ? (str_contains($raw, '.') ? (float) $raw : (int) $raw) : $raw;
            }
            $data[] = $entry;
        }

        $response['dimensions'] = $dimNames;
        $response['metrics'] = $metricNames;
        $response['rows'] = $data;
        $response['rowCount'] = count($data);

        // Totals
        $totals = $result['totals'] ?? [];
        if (! empty($totals)) {
            $totalRow = $totals[0]['metricValues'] ?? [];
            $totalData = [];
            foreach ($metricNames as $i => $name) {
                $raw = $totalRow[$i]['value'] ?? '0';
                $totalData[$name] = is_numeric($raw) ? (str_contains($raw, '.') ? (float) $raw : (int) $raw) : $raw;
            }
            $response['totals'] = $totalData;

            // Comparison totals
            if (count($totals) >= 2) {
                $prevRow = $totals[1]['metricValues'] ?? [];
                $comparison = [];
                foreach ($metricNames as $i => $name) {
                    $current = (float) ($totalRow[$i]['value'] ?? 0);
                    $previous = (float) ($prevRow[$i]['value'] ?? 0);
                    $change = $previous > 0
                        ? round(($current - $previous) / $previous * 100, 1)
                        : ($current > 0 ? 100.0 : 0.0);
                    $comparison[$name] = [
                        'current' => $current,
                        'previous' => $previous,
                        'changePercent' => $change,
                    ];
                }
                $response['comparison'] = $comparison;
            }
        }

        return ToolResult::success($response);
    }

    public function parameters(): array
    {
        return [
            'property_id' => ['type' => 'string', 'required' => true, 'description' => 'GA4 property ID (numeric, e.g., "123456789"). Use google_analytics_list_properties to find it.'],
            'metrics' => ['type' => 'array', 'required' => true, 'description' => 'Metric names to measure (e.g., ["sessions", "totalUsers"]).'],
            'dimensions' => ['type' => 'array', 'description' => 'Dimension names to group by (e.g., ["country", "pagePath"]). Optional — omit for aggregate totals.'],
            'start_date' => ['type' => 'string', 'description' => 'Start date: YYYY-MM-DD or relative ("7daysAgo", "28daysAgo", "yesterday", "today"). Default: "28daysAgo".'],
            'end_date' => ['type' => 'string', 'description' => 'End date: YYYY-MM-DD or relative. Default: "yesterday".'],
            'compare_start_date' => ['type' => 'string', 'description' => 'Comparison period start date (for period-over-period).'],
            'compare_end_date' => ['type' => 'string', 'description' => 'Comparison period end date.'],
            'filters' => ['type' => 'array', 'description' => 'Dimension filters: [{dimension, operator, value}]. Operators: exact, contains, begins_with, ends_with, regex, in_list.'],
            'metric_filters' => ['type' => 'array', 'description' => 'Metric filters: [{metric, operator, value}]. Operators: equal, less_than, greater_than, less_than_or_equal, greater_than_or_equal.'],
            'order_by' => ['type' => 'string', 'description' => 'Dimension or metric name to sort by.'],
            'order_direction' => ['type' => 'string', 'description' => '"asc" or "desc" (default "desc").'],
            'limit' => ['type' => 'integer', 'description' => 'Max rows to return (default 10).'],
            'offset' => ['type' => 'integer', 'description' => 'Pagination offset (default 0).'],
        ];
    }
}
