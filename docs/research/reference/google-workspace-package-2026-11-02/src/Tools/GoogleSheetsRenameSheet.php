<?php

namespace OpenCompany\Integrations\Google\Tools;

use OpenCompany\IntegrationCore\Contracts\Tool;
use OpenCompany\IntegrationCore\Support\ToolResult;
use OpenCompany\Integrations\Google\Services\GoogleSheetsService;

class GoogleSheetsRenameSheet implements Tool
{
    public function __construct(
        private GoogleSheetsService $service,
    ) {}

    public function name(): string
    {
        return 'google_sheets_rename_sheet';
    }

    public function description(): string
    {
        return 'Rename a sheet/tab in a Google Spreadsheet.';
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
                return ToolResult::error('sheet (current sheet name) is required.');
            }

            $title = $args['title'] ?? '';
            if (empty($title)) {
                return ToolResult::error('title (new name) is required.');
            }

            $sheetId = $this->service->resolveSheetId($spreadsheetId, $sheetName);

            $this->service->batchUpdate($spreadsheetId, [
                ['updateSheetProperties' => [
                    'properties' => [
                        'sheetId' => $sheetId,
                        'title' => $title,
                    ],
                    'fields' => 'title',
                ]],
            ]);

            return ToolResult::success("Sheet renamed from '{$sheetName}' to '{$title}'.");
        } catch (\Throwable $e) {
            return ToolResult::error($e->getMessage());
        }
    }

    public function parameters(): array
    {
        return [
            'spreadsheet_id' => ['type' => 'string', 'required' => true, 'description' => 'Spreadsheet ID (from the URL).'],
            'sheet' => ['type' => 'string', 'required' => true, 'description' => 'Current sheet/tab name.'],
            'title' => ['type' => 'string', 'required' => true, 'description' => 'New name for the sheet/tab.'],
        ];
    }
}
