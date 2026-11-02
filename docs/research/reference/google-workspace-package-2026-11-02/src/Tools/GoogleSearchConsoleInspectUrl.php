<?php

namespace OpenCompany\Integrations\Google\Tools;

use OpenCompany\IntegrationCore\Contracts\Tool;
use OpenCompany\IntegrationCore\Support\ToolResult;
use OpenCompany\Integrations\Google\Services\GoogleSearchConsoleService;

class GoogleSearchConsoleInspectUrl implements Tool
{
    public function __construct(private GoogleSearchConsoleService $service) {}

    public function name(): string
    {
        return 'google_search_console_inspect_url';
    }

    public function description(): string
    {
        return <<<'MD'
        Check a URL's indexing status in Google Search Console. Returns: index verdict, coverage state, last crawl time, robots.txt state, indexing state, rich results, mobile usability, and AMP status.
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

            $url = $args['url'] ?? '';
            if (empty($url)) {
                return ToolResult::error('url is required (full URL to inspect).');
            }

            $result = $this->service->inspectUrl($siteUrl, $url);
            $inspection = $result['inspectionResult'] ?? $result;

            $output = [
                'url' => $url,
            ];

            // Index status
            $indexStatus = $inspection['indexStatusResult'] ?? [];
            if (! empty($indexStatus)) {
                $output['indexStatus'] = [
                    'verdict' => $indexStatus['verdict'] ?? '',
                    'coverageState' => $indexStatus['coverageState'] ?? '',
                    'robotsTxtState' => $indexStatus['robotsTxtState'] ?? '',
                    'indexingState' => $indexStatus['indexingState'] ?? '',
                    'lastCrawlTime' => $indexStatus['lastCrawlTime'] ?? '',
                    'pageFetchState' => $indexStatus['pageFetchState'] ?? '',
                    'crawledAs' => $indexStatus['crawledAs'] ?? '',
                ];

                // Remove empty values
                $output['indexStatus'] = array_filter($output['indexStatus'], fn ($v) => $v !== '');
            }

            // Mobile usability
            $mobile = $inspection['mobileUsabilityResult'] ?? [];
            if (! empty($mobile)) {
                $output['mobileUsability'] = [
                    'verdict' => $mobile['verdict'] ?? '',
                ];
                if (! empty($mobile['issues'])) {
                    $output['mobileUsability']['issues'] = $mobile['issues'];
                }
            }

            // Rich results
            $rich = $inspection['richResultsResult'] ?? [];
            if (! empty($rich)) {
                $output['richResults'] = [
                    'verdict' => $rich['verdict'] ?? '',
                ];
                $items = $rich['detectedItems'] ?? [];
                if (! empty($items)) {
                    $output['richResults']['detectedItems'] = array_map(
                        fn (array $item) => $item['richResultType'] ?? 'unknown',
                        $items
                    );
                }
            }

            // AMP
            $amp = $inspection['ampResult'] ?? [];
            if (! empty($amp)) {
                $output['amp'] = [
                    'verdict' => $amp['verdict'] ?? '',
                ];
            }

            // Link to Search Console UI
            if (isset($inspection['inspectionResultLink'])) {
                $output['inspectionLink'] = $inspection['inspectionResultLink'];
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
            'url' => ['type' => 'string', 'required' => true, 'description' => 'Full URL to inspect (e.g., "https://www.example.com/page").'],
        ];
    }
}
