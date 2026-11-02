<?php

namespace OpenCompany\Integrations\Google\Tools;

use OpenCompany\IntegrationCore\Contracts\Tool;
use OpenCompany\IntegrationCore\Support\ToolResult;
use OpenCompany\Integrations\Google\Services\GoogleSearchConsoleService;

class GoogleSearchConsoleListSites implements Tool
{
    public function __construct(private GoogleSearchConsoleService $service) {}

    public function name(): string
    {
        return 'google_search_console_list_sites';
    }

    public function description(): string
    {
        return <<<'MD'
        List all verified Google Search Console sites/properties with their permission levels. Use this first to discover available properties before querying performance data or inspecting URLs. Returns each site's URL (e.g., "sc-domain:example.com" or "https://www.example.com/") and your permission level.
        MD;
    }

    public function execute(array $args): ToolResult
    {
        try {
            if (! $this->service->isConfigured()) {
                return ToolResult::error('Google Search Console integration is not configured.');
            }

            $result = $this->service->listSites();
            $sites = $result['siteEntry'] ?? [];

            if (empty($sites)) {
                return ToolResult::success('No verified sites found.');
            }

            $formatted = array_map(fn (array $site) => [
                'siteUrl' => $site['siteUrl'] ?? '',
                'permissionLevel' => $site['permissionLevel'] ?? '',
            ], $sites);

            return ToolResult::success([
                'count' => count($formatted),
                'sites' => $formatted,
            ]);
        } catch (\Throwable $e) {
            return ToolResult::error($e->getMessage());
        }
    }

    public function parameters(): array
    {
        return [];
    }
}
