<?php

namespace App\Agents\Tools\Plausible;

use App\Models\User;
use App\Services\PlausibleService;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Tools\Request;

class PlausibleQueryStats implements Tool
{
    public function __construct(
        private User $agent,
    ) {}

    public function description(): string
    {
        return 'Query website analytics from Plausible. Supports aggregate stats, timeseries, and breakdowns by dimension. Use dimensions to group results (e.g., by country, source, page). Omit dimensions for simple aggregate totals.';
    }

    public function handle(Request $request): string
    {
        try {
            $plausible = app(PlausibleService::class);
            if (!$plausible->isConfigured()) {
                return 'Error: Plausible integration is not configured.';
            }

            $body = [
                'site_id' => $request['siteId'],
                'metrics' => $request['metrics'],
                'date_range' => $request['dateRange'],
            ];

            if (isset($request['dimensions'])) {
                $body['dimensions'] = $request['dimensions'];
            }

            if (isset($request['filters'])) {
                $body['filters'] = $request['filters'];
            }

            if ($request['dateRange'] === 'custom') {
                if (isset($request['dateFrom']) && isset($request['dateTo'])) {
                    $body['date_range'] = [$request['dateFrom'], $request['dateTo']];
                } else {
                    return 'Error: dateFrom and dateTo are required when dateRange is "custom".';
                }
            }

            if (isset($request['orderBy'])) {
                $body['order_by'] = $request['orderBy'];
            }

            if (isset($request['limit'])) {
                $body['limit'] = (int) $request['limit'];
            }

            $result = $plausible->query($body);

            return json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
        } catch (\Throwable $e) {
            return "Error querying Plausible stats: {$e->getMessage()}";
        }
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'siteId' => $schema
                ->string()
                ->description('The site domain (e.g., "example.com").')
                ->required(),
            'metrics' => $schema
                ->array()
                ->description('Metrics to retrieve: visitors, pageviews, visits, bounce_rate, visit_duration, views_per_visit, events, conversion_rate.')
                ->required(),
            'dateRange' => $schema
                ->string()
                ->description('Time period: "7d", "28d", "30d", "month", "3mo", "6mo", "12mo", or "custom" (requires dateFrom/dateTo).')
                ->required(),
            'dimensions' => $schema
                ->array()
                ->description('Dimensions to group by: visit:source, visit:country, visit:city, visit:device, visit:browser, visit:os, event:page, event:name, time:day, time:month, etc.'),
            'filters' => $schema
                ->array()
                ->description('Filter expressions as nested arrays, e.g., [["is", "visit:country", ["NL"]]].'),
            'dateFrom' => $schema
                ->string()
                ->description('Start date (ISO 8601, e.g., "2025-01-01") when dateRange is "custom".'),
            'dateTo' => $schema
                ->string()
                ->description('End date (ISO 8601, e.g., "2025-01-31") when dateRange is "custom".'),
            'orderBy' => $schema
                ->array()
                ->description('Order results, e.g., [["visitors", "desc"]].'),
            'limit' => $schema
                ->integer()
                ->description('Maximum number of results to return (default: 10000).'),
        ];
    }
}
