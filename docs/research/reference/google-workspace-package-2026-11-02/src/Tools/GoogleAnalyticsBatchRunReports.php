<?php

namespace OpenCompany\Integrations\Google\Tools;

use OpenCompany\IntegrationCore\Contracts\Tool;
use OpenCompany\IntegrationCore\Support\ToolResult;
use OpenCompany\Integrations\Google\Services\GoogleAnalyticsService;

/**
 * Run multiple GA4 standard reports in one Data API request.
 */
class GoogleAnalyticsBatchRunReports implements Tool
{
    /**
     * @param  GoogleAnalyticsService  $service  The Google Analytics API client.
     */
    public function __construct(private GoogleAnalyticsService $service) {}

    public function name(): string
    {
        return 'google_analytics_batch_run_reports';
    }

    public function description(): string
    {
        return 'Run multiple GA4 standard report requests in one batchRunReports call.';
    }

    public function parameters(): array
    {
        return [
            'property_id' => ['type' => 'string', 'required' => true, 'description' => 'GA4 property ID.'],
            'requests' => ['type' => 'array', 'required' => true, 'description' => 'Array of Data API RunReportRequest bodies.'],
        ];
    }

    /**
     * Execute a batch of standard report requests.
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
                return ToolResult::error('requests must be a non-empty array of RunReportRequest bodies.');
            }

            return ToolResult::success($this->service->batchRunReports((string) $propertyId, ['requests' => array_values($requests)]));
        } catch (\Throwable $e) {
            return ToolResult::error($e->getMessage());
        }
    }
}
