<?php

namespace OpenCompany\Integrations\Google\Tools;

use OpenCompany\IntegrationCore\Contracts\Tool;
use OpenCompany\IntegrationCore\Support\ToolResult;
use OpenCompany\Integrations\Google\Services\GoogleSearchConsoleService;

class GoogleSearchConsolePerformance implements Tool
{
    public function __construct(private GoogleSearchConsoleService $service) {}

    public function name(): string
    {
        return 'google_search_console_performance';
    }

    public function description(): string
    {
        return <<<'MD'
        Query Google Search Console search performance data (clicks, impressions, CTR, position). Common queries: "top pages by clicks" → dimensions=["page"]. "Top search queries" → dimensions=["query"]. "Traffic trend" → dimensions=["date"]. "Mobile vs desktop" → dimensions=["device"]. "Blog section" → dimensions=["page"], filters=[{dimension:"page", operator:"contains", value:"/blog/"}]. Combine dimensions: dimensions=["query","device"] for queries by device.
        MD;
    }

    public function execute(array $args): ToolResult
    {
        try {
            if (! $this->service->isConfigured()) {
                return ToolResult::error('Google Search Console integration is not configured.');
            }

            $siteUrl = $args['site_url'] ?? '';
            if (empty($siteUrl)) {
                return ToolResult::error('siteUrl is required. Use google_search_console_list_sites to find your property URL.');
            }

            // Default date range: last 28 days (30 days ago to 3 days ago for data freshness)
            $endDate = $args['end_date'] ?? date('Y-m-d', strtotime('-3 days'));
            $startDate = $args['start_date'] ?? date('Y-m-d', strtotime('-30 days'));

            $body = [
                'startDate' => $startDate,
                'endDate' => $endDate,
            ];

            // Dimensions
            $dimensions = $args['dimensions'] ?? [];
            if (is_array($dimensions) && ! empty($dimensions)) {
                $body['dimensions'] = array_values($dimensions);
            }

            // Filters
            $filters = $args['filters'] ?? [];
            if (is_array($filters) && ! empty($filters)) {
                $filterList = [];
                foreach ($filters as $filter) {
                    if (is_array($filter) && isset($filter['dimension'], $filter['value'])) {
                        $filterList[] = [
                            'dimension' => $filter['dimension'],
                            'operator' => $filter['operator'] ?? 'contains',
                            'expression' => $filter['value'],
                        ];
                    }
                }
                if (! empty($filterList)) {
                    $body['dimensionFilterGroups'] = [
                        ['filters' => $filterList],
                    ];
                }
            }

            // Row limit and offset
            $limit = isset($args['limit']) ? min((int) $args['limit'], 25000) : 1000;
            $body['rowLimit'] = $limit;

            $offset = (int) ($args['offset'] ?? 0);
            if ($offset > 0) {
                $body['startRow'] = $offset;
            }

            // Search type
            $type = $args['type'] ?? '';
            if ($type !== '' && is_string($type)) {
                $body['type'] = $type;
            }

            // Data state
            $dataState = $args['data_state'] ?? '';
            if ($dataState !== '' && is_string($dataState)) {
                $body['dataState'] = $dataState;
            }

            // Aggregation type
            $aggregationType = $args['aggregation_type'] ?? '';
            if ($aggregationType !== '' && is_string($aggregationType)) {
                $body['aggregationType'] = $aggregationType;
            }

            $result = $this->service->queryAnalytics($siteUrl, $body);
            $rows = $result['rows'] ?? [];

            if (empty($rows)) {
                return ToolResult::success([
                    'dateRange' => "{$startDate} to {$endDate}",
                    'rows' => 0,
                    'data' => [],
                ]);
            }

            $formatted = [];
            foreach ($rows as $row) {
                $entry = [];

                // Keys correspond to dimensions in order
                $keys = $row['keys'] ?? [];
                $dims = is_array($dimensions) ? array_values($dimensions) : [];
                foreach ($keys as $i => $key) {
                    $dimName = $dims[$i] ?? "dimension_{$i}";
                    $entry[$dimName] = $key;
                }

                $entry['clicks'] = (int) ($row['clicks'] ?? 0);
                $entry['impressions'] = (int) ($row['impressions'] ?? 0);
                $entry['ctr'] = round((float) ($row['ctr'] ?? 0), 4);
                $entry['position'] = round((float) ($row['position'] ?? 0), 1);

                $formatted[] = $entry;
            }

            $output = [
                'dateRange' => "{$startDate} to {$endDate}",
                'rows' => count($formatted),
                'data' => $formatted,
            ];

            if (isset($result['responseAggregationType'])) {
                $output['aggregationType'] = $result['responseAggregationType'];
            }

            return ToolResult::success($output);
        } catch (\Throwable $e) {
            return ToolResult::error($e->getMessage());
        }
    }

    public function parameters(): array
    {
        return [
            'site_url' => ['type' => 'string', 'required' => true, 'description' => 'Site property URL (e.g., "sc-domain:example.com" or "https://www.example.com/"). Use google_search_console_list_sites to find it.'],
            'start_date' => ['type' => 'string', 'description' => 'Start date (YYYY-MM-DD). Default: 30 days ago.'],
            'end_date' => ['type' => 'string', 'description' => 'End date (YYYY-MM-DD). Default: 3 days ago.'],
            'dimensions' => ['type' => 'array', 'description' => 'Dimensions to group by (array). Options: "query", "page", "country", "device", "date", "searchAppearance". Combine multiple.'],
            'filters' => ['type' => 'array', 'description' => 'Filters: array of {dimension, operator, value}. Operators: "contains", "equals", "notContains", "notEquals".'],
            'limit' => ['type' => 'integer', 'description' => 'Max rows (default 1000, max 25000).'],
            'offset' => ['type' => 'integer', 'description' => 'Pagination offset (default 0).'],
            'type' => ['type' => 'string', 'description' => 'Search type: "web" (default), "discover", "image", "video", "news", "googleNews".'],
            'data_state' => ['type' => 'string', 'description' => 'Data state: "final" (default, reliable) or "all" (includes fresh/unprocessed).'],
            'aggregation_type' => ['type' => 'string', 'description' => 'Aggregation: "auto" (default), "byPage", or "byProperty".'],
        ];
    }
}
