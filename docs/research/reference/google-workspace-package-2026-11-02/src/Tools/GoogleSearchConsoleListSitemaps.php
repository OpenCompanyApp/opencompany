<?php

namespace OpenCompany\Integrations\Google\Tools;

use OpenCompany\IntegrationCore\Contracts\Tool;
use OpenCompany\IntegrationCore\Support\ToolResult;
use OpenCompany\Integrations\Google\Services\GoogleSearchConsoleService;

class GoogleSearchConsoleListSitemaps implements Tool
{
    public function __construct(private GoogleSearchConsoleService $service) {}

    public function name(): string
    {
        return 'google_search_console_list_sitemaps';
    }

    public function description(): string
    {
        return <<<'MD'
        List all submitted sitemaps for a Google Search Console property. Returns each sitemap's path, last submitted/downloaded dates, whether it's a sitemap index, and content type counts (submitted vs indexed URLs).
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

            $result = $this->service->listSitemaps($siteUrl);
            $sitemaps = $result['sitemap'] ?? [];

            if (empty($sitemaps)) {
                return ToolResult::success('No sitemaps found.');
            }

            $formatted = [];
            foreach ($sitemaps as $sitemap) {
                $entry = [
                    'path' => $sitemap['path'] ?? '',
                    'lastSubmitted' => $sitemap['lastSubmitted'] ?? '',
                    'lastDownloaded' => $sitemap['lastDownloaded'] ?? '',
                    'isSitemapIndex' => $sitemap['isSitemapIndex'] ?? false,
                ];

                $contents = $sitemap['contents'] ?? [];
                if (! empty($contents)) {
                    $entry['contents'] = array_map(fn (array $c) => array_filter([
                        'type' => $c['type'] ?? '',
                        'submitted' => (int) ($c['submitted'] ?? 0),
                        'indexed' => (int) ($c['indexed'] ?? 0),
                    ], fn ($v) => $v !== '' && $v !== 0), $contents);
                }

                $formatted[] = $entry;
            }

            return ToolResult::success([
                'count' => count($formatted),
                'sitemaps' => $formatted,
            ]);
        } catch (\Throwable $e) {
            return ToolResult::error($e->getMessage());
        }
    }

    public function parameters(): array
    {
        return [
            'site_url' => ['type' => 'string', 'required' => true, 'description' => 'Site property URL (e.g., "sc-domain:example.com" or "https://www.example.com/").'],
        ];
    }
}
