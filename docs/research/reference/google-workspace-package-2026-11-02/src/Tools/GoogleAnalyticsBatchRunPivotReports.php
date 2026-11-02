<?php

namespace OpenCompany\Integrations\Google\Tools;

use OpenCompany\IntegrationCore\Contracts\Tool;
use OpenCompany\IntegrationCore\Support\ToolResult;
use OpenCompany\Integrations\Google\Services\GoogleAnalyticsService;

/**
 * Run multiple GA4 pivot reports in one Data API request.
 */
class GoogleAnalyticsBatchRunPivotReports implements Tool
{
    /**
     * @param  GoogleAnalyticsService  $service  The Google Analytics API client.
     */
    public function __construct(private GoogleAnalyticsService $service) {}

    public function name(): string
    {
        return 'google_analytics_batch_run_pivot_reports';
    }

    public function description(): string
    {
        return 'Run multiple GA4 pivot report requests in one batchRunPivotReports call.';
    }

    public function parameters(): array
    {
        return [
            'property_id' => ['type' => 'string', 'required' => true, 'description' => 'GA4 property ID.'],
            'requests' => ['type' => 'array', 'required' => true, 'description' => 'Array of Data API RunPivotReportRequest bodies.'],
        ];
    }

    /**
     * Execute a batch of pivot report requests.
     *
     * @param  array<string, mixed>  $args  Tool arguments (property_id, requests).
     */
    public function execute(array $args): ToolResult
    {
        try {
            if (! $this->service->isConfigured()) {
                return ToolResult::error('Google Analytics integration is not configured.');
            }

            $propertyId = $args['property_id'] ?? '';
            $requests = $args['requests'] ?? [];
            if (empty($propertyId)) {
                return ToolResult::error('property_id is required.');
            }
            if (! is_array($requests) || empty($requests)) {
                return ToolResult::error('requests must be a non-empty array of RunPivotReportRequest bodies.');
            }

            return ToolResult::success($this->service->batchRunPivotReports((string) $propertyId, ['requests' => array_values($requests)]));
        } catch (\Throwable $e) {
            return ToolResult::error($e->getMessage());
        }
    }
}
