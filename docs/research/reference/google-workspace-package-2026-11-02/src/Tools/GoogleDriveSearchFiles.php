<?php

namespace OpenCompany\Integrations\Google\Tools;

use OpenCompany\IntegrationCore\Contracts\Tool;
use OpenCompany\IntegrationCore\Support\ToolResult;
use OpenCompany\Integrations\Google\Services\GoogleDriveService;

class GoogleDriveSearchFiles implements Tool
{
    public function __construct(
        private GoogleDriveService $service,
    ) {}

    public function name(): string
    {
        return 'google_drive_search_files';
    }

    public function description(): string
    {
        return <<<'MD'
        Search for files in Google Drive using Drive query syntax (default: 20 results, max: 100). Trashed files are excluded by default.

        Drive query syntax examples:
        - By name: `name contains 'budget'` or `name = 'Q1 Report'`
        - By type: `mimeType = 'application/vnd.google-apps.spreadsheet'` (also: document, presentation, folder)
        - In folder: `'FOLDER_ID' in parents`
        - Recent: `modifiedTime > '2026-01-01'`
        - Shared with me: `sharedWithMe = true`
        - Starred: `starred = true`
        - By owner: `'user@example.com' in owners`
        - Combine: `name contains 'report' and mimeType = 'application/vnd.google-apps.spreadsheet'`
        MD;
    }

    public function execute(array $args): ToolResult
    {
        try {
            if (! $this->service->isConfigured()) {
                return ToolResult::error('Google Drive integration is not configured.');
            }

            $params = [];

            $query = $args['query'] ?? '';
            if ($query !== '') {
                // Auto-exclude trashed files unless the query already mentions trashed
                if (stripos($query, 'trashed') === false) {
                    $query .= ' and trashed = false';
                }
                $params['q'] = $query;
            } else {
                $params['q'] = 'trashed = false';
            }

            $pageSize = isset($args['max_results']) ? min((int) $args['max_results'], 100) : 20;
            $params['pageSize'] = (string) $pageSize;

            if (isset($args['page_token'])) {
                $params['pageToken'] = $args['page_token'];
            }

            if (isset($args['order_by'])) {
                $params['orderBy'] = $args['order_by'];
            }

            $result = $this->service->listFiles($params);
            $files = $result['files'] ?? [];

            if (empty($files)) {
                return ToolResult::success('No files found.');
            }

            $formatted = array_map(fn (array $file) => $this->formatFile($file), $files);

            $output = ['count' => count($formatted), 'files' => $formatted];
            if (isset($result['nextPageToken'])) {
                $output['nextPageToken'] = $result['nextPageToken'];
            }

            return ToolResult::success($output);
        } catch (\Throwable $e) {
            return ToolResult::error($e->getMessage());
        }
    }

    /**
     * Format a file array for output.
     *
     * @param  array<string, mixed>  $file
     * @return array<string, mixed>
     */
    private function formatFile(array $file): array
    {
        $formatted = [
            'id' => $file['id'] ?? '',
            'name' => $file['name'] ?? '',
            'mimeType' => $file['mimeType'] ?? '',
            'createdTime' => $file['createdTime'] ?? '',
            'modifiedTime' => $file['modifiedTime'] ?? '',
            'webViewLink' => $file['webViewLink'] ?? '',
        ];

        if (isset($file['size'])) {
            $formatted['size'] = GoogleDriveService::formatSize($file['size']);
        }

        if (isset($file['shared']) && $file['shared']) {
            $formatted['shared'] = true;
        }

        if (isset($file['starred']) && $file['starred']) {
            $formatted['starred'] = true;
        }

        if (isset($file['parents'])) {
            $formatted['parents'] = $file['parents'];
        }

        if (isset($file['owners']) && is_array($file['owners'])) {
            $formatted['owner'] = $file['owners'][0]['emailAddress'] ?? $file['owners'][0]['displayName'] ?? '';
        }

        return array_filter($formatted, fn ($v) => $v !== '');
    }

    public function parameters(): array
    {
        return [
            'query' => [
                'type' => 'string',
                'description' => 'Drive search query (e.g., "name contains \'report\' and mimeType = \'application/vnd.google-apps.folder\'").',
            ],
            'max_results' => ['type' => 'integer', 'description' => 'Max results per page (default: 20, max: 100).'],
            'page_token' => ['type' => 'string', 'description' => 'Pagination token from previous response.'],
            'order_by' => ['type' => 'string', 'description' => 'Sort order (e.g., "modifiedTime desc", "name").'],
        ];
    }
}
