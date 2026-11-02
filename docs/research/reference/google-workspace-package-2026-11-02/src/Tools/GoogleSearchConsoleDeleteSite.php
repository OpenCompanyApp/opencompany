<?php

namespace OpenCompany\Integrations\Google\Tools;

use OpenCompany\IntegrationCore\Contracts\Tool;
use OpenCompany\IntegrationCore\Support\ToolResult;
use OpenCompany\Integrations\Google\Services\GoogleSearchConsoleService;

class GoogleSearchConsoleDeleteSite implements Tool
{
    public function __construct(
        private GoogleSearchConsoleService $service,
    ) {}

    public function name(): string
    {
        return 'google_search_console_delete_site';
    }

    public function description(): string
    {
        return 'Remove a site property from Google Search Console.';
    }

    public function execute(array $args): ToolResult
    {
        try {
            if (! $this->service->isConfigured()) {
                return ToolResult::error('Google Search Console integration is not configured.');
            }

            $siteUrl = $args['site_url'] ?? '';
            if (empty($siteUrl)) {
                return ToolResult::error('siteUrl is required.');
            }

            $this->service->deleteSite($siteUrl);

            return ToolResult::success("Site property removed: {$siteUrl}");
        } catch (\Throwable $e) {
            return ToolResult::error($e->getMessage());
        }
    }

    public function parameters(): array
    {
        return [
            'site_url' => ['type' => 'string', 'required' => true, 'description' => 'Site property URL to remove.'],
        ];
    }
}
