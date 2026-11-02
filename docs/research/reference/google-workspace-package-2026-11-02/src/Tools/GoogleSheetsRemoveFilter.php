<?php

namespace OpenCompany\Integrations\Google\Tools;

use OpenCompany\IntegrationCore\Contracts\Tool;
use OpenCompany\IntegrationCore\Support\ToolResult;
use OpenCompany\Integrations\Google\Services\GoogleSheetsService;

class GoogleSheetsRemoveFilter implements Tool
{
    public function __construct(
        private GoogleSheetsService $service,
    ) {}

    public function name(): string
    {
        return 'google_sheets_remove_filter';
    }

    public function description(): string
    {
        return 'Remove the filter from a Google Sheets sheet/tab.';
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
                ['clearBasicFilter' => ['sheetId' => $sheetId]],
            ]);

            return ToolResult::success("Filter removed from sheet '{$sheetName}'.");
        } catch (\Throwable $e) {
            return ToolResult::error($e->getMessage());
        }
    }

    public function parameters(): array
    {
        return [
            'spreadsheet_id' => ['type' => 'string', 'required' => true, 'description' => 'Spreadsheet ID (from the URL).'],
            'sheet' => ['type' => 'string', 'required' => true, 'description' => 'Sheet/tab name to remove the filter from.'],
        ];
    }
}
