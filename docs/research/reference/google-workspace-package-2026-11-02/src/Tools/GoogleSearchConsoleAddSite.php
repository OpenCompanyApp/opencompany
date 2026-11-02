<?php

namespace OpenCompany\Integrations\Google\Tools;

use OpenCompany\IntegrationCore\Contracts\Tool;
use OpenCompany\IntegrationCore\Support\ToolResult;
use OpenCompany\Integrations\Google\Services\GoogleSearchConsoleService;

class GoogleSearchConsoleAddSite implements Tool
{
    public function __construct(
        private GoogleSearchConsoleService $service,
    ) {}

    public function name(): string
    {
        return 'google_search_console_add_site';
    }

    public function description(): string
    {
        return 'Add a new site property to Google Search Console.';
    }

    public function execute(array $args): ToolResult
    {
        try {
            if (! $this->service->isConfigured()) {
                return ToolResult::error('Google Search Console integration is not configured.');
            }

            $siteUrl = $args['site_url'] ?? '';
            if (empty($siteUrl)) {
                return ToolResult::error('siteUrl is required (e.g., "https://example.com/" or "sc-domain:example.com").');
            }

            $this->service->addSite($siteUrl);

            return ToolResult::success("Site property added: {$siteUrl}");
        } catch (\Throwable $e) {
            return ToolResult::error($e->getMessage());
        }
    }

    public function parameters(): array
    {
        return [
            'site_url' => ['type' => 'string', 'required' => true, 'description' => 'Site property URL (e.g., "https://example.com/" for URL-prefix or "sc-domain:example.com" for domain property).'],
        ];
    }
}
