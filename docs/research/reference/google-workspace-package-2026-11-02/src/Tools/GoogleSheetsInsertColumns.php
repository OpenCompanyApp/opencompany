<?php

namespace OpenCompany\Integrations\Google\Tools;

use OpenCompany\IntegrationCore\Contracts\Tool;
use OpenCompany\IntegrationCore\Support\ToolResult;
use OpenCompany\Integrations\Google\Services\GoogleSheetsService;

class GoogleSheetsInsertColumns implements Tool
{
    public function __construct(
        private GoogleSheetsService $service,
    ) {}

    public function name(): string
    {
        return 'google_sheets_insert_columns';
    }

    public function description(): string
    {
        return 'Insert blank columns into a Google Sheets sheet/tab. Uses 0-based indexing.';
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

            $sheetName = $args['sheet'] ?? '';
            if (empty($sheetName)) {
                return ToolResult::error('sheet (sheet name) is required.');
            }

            $startIndex = (int) ($args['start_index'] ?? 0);
            $count = max(1, (int) ($args['count'] ?? 1));

            $sheetId = $this->service->resolveSheetId($spreadsheetId, $sheetName);

            $this->service->batchUpdate($spreadsheetId, [
                ['insertDimension' => [
                    'range' => [
                        'sheetId' => $sheetId,
                        'dimension' => 'COLUMNS',
                        'startIndex' => $startIndex,
                        'endIndex' => $startIndex + $count,
                    ],
                    'inheritFromBefore' => $startIndex > 0,
                ]],
            ]);

            return ToolResult::success("{$count} column(s) inserted at index {$startIndex} in '{$sheetName}'.");
        } catch (\Throwable $e) {
            return ToolResult::error($e->getMessage());
        }
    }

    public function parameters(): array
    {
        return [
            'spreadsheet_id' => ['type' => 'string', 'required' => true, 'description' => 'Spreadsheet ID (from the URL).'],
            'sheet' => ['type' => 'string', 'required' => true, 'description' => 'Sheet/tab name.'],
            'start_index' => ['type' => 'integer', 'required' => true, 'description' => '0-based column index to insert at.'],
            'count' => ['type' => 'integer', 'description' => 'Number of columns to insert (default 1).'],
        ];
    }
}
