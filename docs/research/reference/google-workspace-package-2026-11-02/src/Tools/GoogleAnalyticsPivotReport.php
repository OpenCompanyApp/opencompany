<?php

namespace OpenCompany\Integrations\Google\Tools;

use OpenCompany\IntegrationCore\Contracts\Tool;
use OpenCompany\IntegrationCore\Support\ToolResult;
use OpenCompany\Integrations\Google\Services\GoogleAnalyticsService;

/**
 * Run a GA4 pivot report.
 *
 * Pivot reports expose dimensions only when they are included in pivot definitions.
 */
class GoogleAnalyticsPivotReport implements Tool
{
    /**
     * @param  GoogleAnalyticsService  $service  The Google Analytics API client.
     */
    public function __construct(private GoogleAnalyticsService $service) {}

    public function name(): string
    {
        return 'google_analytics_pivot_report';
    }

    public function description(): string
    {
        return 'Run a GA4 pivot report for advanced cross-tabbed dimension and metric analysis.';
    }

    public function parameters(): array
    {
        return [
            'property_id' => ['type' => 'string', 'required' => true, 'description' => 'GA4 property ID.'],
            'metrics' => ['type' => 'array', 'required' => true, 'description' => 'Metric names to measure.'],
            'dimensions' => ['type' => 'array', 'description' => 'Dimension names used by pivots.'],
            'pivots' => ['type' => 'array', 'description' => 'Data API pivot definitions. If omitted, one pivot is built from dimensions.'],
            'start_date' => ['type' => 'string', 'description' => 'Start date. Default: 28daysAgo.'],
            'end_date' => ['type' => 'string', 'description' => 'End date. Default: yesterday.'],
            'filters' => ['type' => 'array', 'description' => 'Dimension filters.'],
            'metric_filters' => ['type' => 'array', 'description' => 'Metric filters.'],
            'limit' => ['type' => 'integer', 'description' => 'Default pivot limit when pivots are omitted.'],
        ];
    }

    /**
     * Execute the pivot report.
     *
     * @param  array<string, mixed>  $args  Tool arguments (property_id, metrics, dimensions, pivots, dates, filters).
     */
    public function execute(array $args): ToolResult
    {
        try {
            if (! $this->service->isConfigured()) {
                return ToolResult::error('Google Analytics integration is not configured.');
            }

            $propertyId = $args['property_id'] ?? '';
            if (empty($propertyId)) {
                return ToolResult::error('property_id is required.');
            }

            $metrics = $args['metrics'] ?? [];
            if (! is_array($metrics) || empty($metrics)) {
                return ToolResult::error('metrics is required.');
            }

            $body = $this->service->buildPivotReportBody([
                'metrics' => $metrics,
                'dimensions' => $args['dimensions'] ?? [],
                'pivots' => $args['pivots'] ?? [],
                'startDate' => $args['start_date'] ?? '28daysAgo',
                'endDate' => $args['end_date'] ?? 'yesterday',
                'filters' => $args['filters'] ?? [],
                'metricFilters' => $args['metric_filters'] ?? [],
                'limit' => $args['limit'] ?? 10,
            ]);

            return ToolResult::success($this->service->runPivotReport((string) $propertyId, $body));
        } catch (\Throwable $e) {
            return ToolResult::error($e->getMessage());
        }
    }
}
