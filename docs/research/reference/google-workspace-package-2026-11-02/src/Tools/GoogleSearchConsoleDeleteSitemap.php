<?php

namespace OpenCompany\Integrations\Google\Tools;

use OpenCompany\IntegrationCore\Contracts\Tool;
use OpenCompany\IntegrationCore\Support\ToolResult;
use OpenCompany\Integrations\Google\Services\GoogleSearchConsoleService;

class GoogleSearchConsoleDeleteSitemap implements Tool
{
    public function __construct(
        private GoogleSearchConsoleService $service,
    ) {}

    public function name(): string
    {
        return 'google_search_console_delete_sitemap';
    }

    public function description(): string
    {
        return 'Remove a sitemap from Google Search Console.';
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

            $sitemapUrl = $args['sitemap_url'] ?? '';
            if (empty($sitemapUrl)) {
                return ToolResult::error('sitemapUrl is required.');
            }

            $this->service->deleteSitemap($siteUrl, $sitemapUrl);

            return ToolResult::success("Sitemap deleted: {$sitemapUrl}");
        } catch (\Throwable $e) {
            return ToolResult::error($e->getMessage());
        }
    }

    public function parameters(): array
    {
        return [
            'site_url' => ['type' => 'string', 'required' => true, 'description' => 'Site property URL (e.g., "sc-domain:example.com" or "https://www.example.com/").'],
            'sitemap_url' => ['type' => 'string', 'required' => true, 'description' => 'Full sitemap URL to remove.'],
        ];
    }
}
