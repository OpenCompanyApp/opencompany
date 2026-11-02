<?php

namespace OpenCompany\Integrations\Google\Tools;

use OpenCompany\IntegrationCore\Contracts\Tool;
use OpenCompany\IntegrationCore\Support\ToolResult;
use OpenCompany\Integrations\Google\Services\GoogleAnalyticsService;

/**
 * List GA4 metadata dimensions and metrics for a property.
 */
class GoogleAnalyticsMetadata implements Tool
{
    /**
     * @param  GoogleAnalyticsService  $service  The Google Analytics API client.
     */
    public function __construct(
        private GoogleAnalyticsService $service,
    ) {}

    public function name(): string
    {
        return 'google_analytics_metadata';
    }

    public function description(): string
    {
        return 'List all available dimensions and metrics for a GA4 property, including custom ones. Use this to discover what data can be queried in reports.';
    }

    /**
     * Execute the tool and return grouped metadata.
     *
     * @param  array<string, mixed>  $args  Tool arguments (property_id).
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

            $result = $this->service->getMetadata((string) $propertyId);

            $dimensions = $result['dimensions'] ?? [];
            $metrics = $result['metrics'] ?? [];

            // Group dimensions by category
            $dimGroups = [];
            foreach ($dimensions as $dim) {
                $category = $dim['category'] ?? 'Other';
                $name = $dim['apiName'] ?? '';
                $desc = $dim['uiName'] ?? '';
                $custom = isset($dim['customDefinition']) && $dim['customDefinition'];
                $label = $custom ? "{$name} - {$desc} [custom]" : "{$name} - {$desc}";
                $dimGroups[$category][] = $label;
            }

            // Group metrics by category
            $metricGroups = [];
            foreach ($metrics as $metric) {
                $category = $metric['category'] ?? 'Other';
                $name = $metric['apiName'] ?? '';
                $desc = $metric['uiName'] ?? '';
                $custom = isset($metric['customDefinition']) && $metric['customDefinition'];
                $label = $custom ? "{$name} - {$desc} [custom]" : "{$name} - {$desc}";
                $metricGroups[$category][] = $label;
            }

            $lines = [];
            $lines[] = count($dimensions) . ' dimensions, ' . count($metrics) . " metrics available.\n";

            $lines[] = 'DIMENSIONS:';
            foreach ($dimGroups as $category => $items) {
                $lines[] = "\n  {$category}:";
                foreach ($items as $item) {
                    $lines[] = "    {$item}";
                }
            }

            $lines[] = "\nMETRICS:";
            foreach ($metricGroups as $category => $items) {
                $lines[] = "\n  {$category}:";
                foreach ($items as $item) {
                    $lines[] = "    {$item}";
                }
            }

            return ToolResult::success(implode("\n", $lines));
        } catch (\Throwable $e) {
            return ToolResult::error($e->getMessage());
        }
    }

    public function parameters(): array
    {
        return [
            'property_id' => ['type' => 'string', 'required' => true, 'description' => 'GA4 property ID (numeric, e.g., "123456789"). Use google_analytics_list_properties to find it.'],
        ];
    }
}
