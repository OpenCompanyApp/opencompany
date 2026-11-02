<?php

namespace OpenCompany\Integrations\Google\Tools;

use OpenCompany\IntegrationCore\Contracts\Tool;
use OpenCompany\IntegrationCore\Support\ToolResult;
use OpenCompany\Integrations\Google\Services\GoogleSearchConsoleService;

class GoogleSearchConsoleGetSitemap implements Tool
{
    public function __construct(private GoogleSearchConsoleService $service) {}

    public function name(): string
    {
        return 'google_search_console_get_sitemap';
    }

    public function description(): string
    {
        return <<<'MD'
        Get details of a specific sitemap in Google Search Console. Returns the sitemap's path, last submitted/downloaded dates, whether it's a sitemap index, and content type breakdown with submitted vs indexed URL counts.
        MD;
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

            $result = $this->service->getSitemap($siteUrl, $sitemapUrl);

            $output = [
                'path' => $result['path'] ?? $sitemapUrl,
                'lastSubmitted' => $result['lastSubmitted'] ?? '',
                'lastDownloaded' => $result['lastDownloaded'] ?? '',
                'isSitemapIndex' => $result['isSitemapIndex'] ?? false,
            ];

            $contents = $result['contents'] ?? [];
            if (! empty($contents)) {
                $output['contents'] = array_map(fn (array $c) => [
                    'type' => $c['type'] ?? '',
                    'submitted' => (int) ($c['submitted'] ?? 0),
                    'indexed' => (int) ($c['indexed'] ?? 0),
                ], $contents);
            }

            return ToolResult::success($output);
        } catch (\Throwable $e) {
            return ToolResult::error($e->getMessage());
        }
    }

    public function parameters(): array
    {
        return [
            'site_url' => ['type' => 'string', 'required' => true, 'description' => 'Site property URL (e.g., "sc-domain:example.com" or "https://www.example.com/").'],
            'sitemap_url' => ['type' => 'string', 'required' => true, 'description' => 'Full URL of the sitemap (e.g., "https://www.example.com/sitemap.xml").'],
        ];
    }
}
