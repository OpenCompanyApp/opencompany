<?php

namespace OpenCompany\Integrations\Google\Tools;

use OpenCompany\IntegrationCore\Contracts\Tool;
use OpenCompany\IntegrationCore\Support\ToolResult;
use OpenCompany\Integrations\Google\Services\GoogleAnalyticsService;

/**
 * List GA4 account summaries and accessible properties.
 */
class GoogleAnalyticsListProperties implements Tool
{
    /**
     * @param  GoogleAnalyticsService  $service  The Google Analytics API client.
     */
    public function __construct(
        private GoogleAnalyticsService $service,
    ) {}

    public function name(): string
    {
        return 'google_analytics_list_properties';
    }

    public function description(): string
    {
        return 'List all accessible GA4 properties with their IDs and names. Use this first to discover the propertyId needed for other Analytics tools.';
    }

    /**
     * Execute the tool and return a property discovery summary.
     *
     * @param  array<string, mixed>  $args  Tool arguments; none are required.
     */
    public function execute(array $args): ToolResult
    {
        try {
            if (! $this->service->isConfigured()) {
                return ToolResult::error('Google Analytics integration is not configured.');
            }

            $allAccounts = [];
            $pageToken = '';

            // Paginate through all account summaries
            do {
                $result = $this->service->listAccountSummaries($pageToken);
                $accounts = $result['accountSummaries'] ?? [];
                foreach ($accounts as $account) {
                    $allAccounts[] = $account;
                }
                $pageToken = $result['nextPageToken'] ?? '';
            } while ($pageToken !== '');

            if (empty($allAccounts)) {
                return ToolResult::success('No GA4 properties found. Ensure the connected Google account has access to Google Analytics.');
            }

            $lines = [];
            $totalProperties = 0;

            foreach ($allAccounts as $account) {
                $accountName = $account['displayName'] ?? 'Unknown';
                $properties = $account['propertySummaries'] ?? [];

                if (empty($properties)) {
                    continue;
                }

                $lines[] = "Account: {$accountName}";

                foreach ($properties as $prop) {
                    $propName = $prop['displayName'] ?? 'Unknown';
                    // Property name format: "properties/123456789" — extract the numeric ID
                    $propResource = $prop['property'] ?? '';
                    $propId = str_replace('properties/', '', $propResource);

                    $lines[] = "  - {$propName} (propertyId: {$propId})";
                    $totalProperties++;
                }
            }

            $header = "{$totalProperties} " . ($totalProperties === 1 ? 'property' : 'properties') . " found:\n";

            return ToolResult::success($header . implode("\n", $lines));
        } catch (\Throwable $e) {
            return ToolResult::error($e->getMessage());
        }
    }

    public function parameters(): array
    {
        return [];
    }
}
