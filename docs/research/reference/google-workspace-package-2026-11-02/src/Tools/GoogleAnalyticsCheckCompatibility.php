<?php

namespace OpenCompany\Integrations\Google\Tools;

use OpenCompany\IntegrationCore\Contracts\Tool;
use OpenCompany\IntegrationCore\Support\ToolResult;
use OpenCompany\Integrations\Google\Services\GoogleAnalyticsService;

/**
 * Check GA4 dimension and metric compatibility.
 *
 * Calls the Data API checkCompatibility method before an agent runs a report.
 */
class GoogleAnalyticsCheckCompatibility implements Tool
{
    /**
     * @param  GoogleAnalyticsService  $service  The Google Analytics API client.
     */
    public function __construct(private GoogleAnalyticsService $service) {}

    public function name(): string
    {
        return 'google_analytics_check_compatibility';
    }

    public function description(): string
    {
        return 'Check whether GA4 dimensions and metrics can be combined in a report request.';
    }

    public function parameters(): array
    {
        return [
            'property_id' => ['type' => 'string', 'required' => true, 'description' => 'GA4 property ID.'],
            'metrics' => ['type' => 'array', 'description' => 'Metric names to check.'],
            'dimensions' => ['type' => 'array', 'description' => 'Dimension names to check.'],
            'filters' => ['type' => 'array', 'description' => 'Dimension filters matching the report request.'],
            'metric_filters' => ['type' => 'array', 'description' => 'Metric filters matching the report request.'],
            'compatibility_filter' => ['type' => 'string', 'description' => 'Optional compatibility filter accepted by the Data API.'],
        ];
    }

    /**
     * Execute the compatibility check.
     *
     * @param  array<string, mixed>  $args  Tool arguments (property_id, metrics, dimensions, filters, metric_filters, compatibility_filter).
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

            $body = $this->service->buildCompatibilityBody([
                'metrics' => $args['metrics'] ?? [],
                'dimensions' => $args['dimensions'] ?? [],
                'filters' => $args['filters'] ?? [],
                'metricFilters' => $args['metric_filters'] ?? [],
                'compatibilityFilter' => $args['compatibility_filter'] ?? '',
            ]);

            return ToolResult::success($this->service->checkCompatibility((string) $propertyId, $body));
        } catch (\Throwable $e) {
            return ToolResult::error($e->getMessage());
        }
    }
}
