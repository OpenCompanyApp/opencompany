<?php

namespace OpenCompany\Integrations\Google\Tools;

use OpenCompany\IntegrationCore\Contracts\Tool;
use OpenCompany\IntegrationCore\Support\ToolResult;
use OpenCompany\Integrations\Google\Services\GoogleSheetsService;

class GoogleSheetsDeleteSheet implements Tool
{
    public function __construct(
        private GoogleSheetsService $service,
    ) {}

    public function name(): string
    {
        return 'google_sheets_delete_sheet';
    }

    public function description(): string
    {
        return 'Delete a sheet/tab from a Google Spreadsheet.';
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

            $sheetId = $this->service->resolveSheetId($spreadsheetId, $sheetName);

            $this->service->batchUpdate($spreadsheetId, [
                ['deleteSheet' => ['sheetId' => $sheetId]],
            ]);

            return ToolResult::success("Sheet '{$sheetName}' deleted.");
        } catch (\Throwable $e) {
            return ToolResult::error($e->getMessage());
        }
    }

    public function parameters(): array
    {
        return [
            'spreadsheet_id' => ['type' => 'string', 'required' => true, 'description' => 'Spreadsheet ID (from the URL).'],
            'sheet' => ['type' => 'string', 'required' => true, 'description' => 'Sheet/tab name to delete.'],
        ];
    }
}
