<?php

namespace OpenCompany\Integrations\Google\Tools;

use OpenCompany\IntegrationCore\Contracts\Tool;
use OpenCompany\IntegrationCore\Support\ToolResult;
use OpenCompany\Integrations\Google\Services\GoogleSheetsService;

class GoogleSheetsFind implements Tool
{
    public function __construct(
        private GoogleSheetsService $service,
    ) {}

    public function name(): string
    {
        return 'google_sheets_find';
    }

    public function description(): string
    {
        return 'Search for text within a Google Spreadsheet. Searches all sheets by default, or specify a sheet name to narrow the search. Returns match count and number of sheets containing matches.';
    }

    public function execute(array $args): ToolResult
    {
        try {
            if (! $this->service->isConfigured()) {
                return ToolResult::error('Google Sheets integration is not configured.');
            }

            $spreadsheetId = $args['spreadsheet_id'] ?? '';
            if (empty($spreadsheetId)) {
                return ToolResult::error('spreadsheetId is required.');
            }

            $query = $args['query'] ?? '';
            if (empty($query)) {
                return ToolResult::error('query is required for find.');
            }

            $findRequest = [
                'find' => $query,
                'replacement' => $query, // Replace with itself = no-op, but returns match count
                'matchCase' => (bool) ($args['match_case'] ?? false),
                'matchEntireCell' => (bool) ($args['match_entire_cell'] ?? false),
                'searchByRegex' => false,
                'includeFormulas' => false,
            ];

            $sheetName = $args['sheet'] ?? '';
            if ($sheetName !== '' && is_string($sheetName)) {
                $sheetId = $this->service->resolveSheetId($spreadsheetId, $sheetName);
                $findRequest['sheetId'] = $sheetId;
            } else {
                $findRequest['allSheets'] = true;
            }

            $result = $this->service->batchUpdate($spreadsheetId, [
                ['findReplace' => $findRequest],
            ]);

            $replies = $result['replies'] ?? [];
            $findResult = $replies[0]['findReplace'] ?? [];

            return ToolResult::success([
                'query' => $query,
                'occurrencesChanged' => (int) ($findResult['occurrencesChanged'] ?? 0),
                'sheetsChanged' => (int) ($findResult['sheetsChanged'] ?? 0),
            ]);
        } catch (\Throwable $e) {
            return ToolResult::error($e->getMessage());
        }
    }

    public function parameters(): array
    {
        return [
            'spreadsheet_id' => ['type' => 'string', 'required' => true, 'description' => 'Spreadsheet ID (from the URL).'],
            'query' => ['type' => 'string', 'required' => true, 'description' => 'Text to search for.'],
            'sheet' => ['type' => 'string', 'description' => 'Sheet name to search in. Omit to search all sheets.'],
            'match_case' => ['type' => 'boolean', 'description' => 'Case-sensitive search. Default false.'],
            'match_entire_cell' => ['type' => 'boolean', 'description' => 'Match entire cell content only. Default false.'],
        ];
    }
}
