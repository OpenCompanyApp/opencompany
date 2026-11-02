<?php

namespace OpenCompany\Integrations\Google\Tools;

use OpenCompany\IntegrationCore\Contracts\Tool;
use OpenCompany\IntegrationCore\Support\ToolResult;
use OpenCompany\Integrations\Google\Services\GoogleSheetsService;

class GoogleSheetsClear implements Tool
{
    public function __construct(
        private GoogleSheetsService $service,
    ) {}

    public function name(): string
    {
        return 'google_sheets_clear';
    }

    public function description(): string
    {
        return 'Clear all values from a Google Sheets range (keeps formatting intact). Specify the range in A1 notation.';
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

            $range = $args['range'] ?? '';
            if (empty($range)) {
                return ToolResult::error('range is required.');
            }

            $result = $this->service->clearRange($spreadsheetId, $range);

            return ToolResult::success([
                'message' => 'Range cleared.',
                'clearedRange' => $result['clearedRange'] ?? $range,
            ]);
        } catch (\Throwable $e) {
            return ToolResult::error($e->getMessage());
        }
    }

    public function parameters(): array
    {
        return [
            'spreadsheet_id' => ['type' => 'string', 'required' => true, 'description' => 'Spreadsheet ID (from the URL).'],
            'range' => ['type' => 'string', 'required' => true, 'description' => 'A1 notation range to clear (e.g., "Sheet1!A1:D10").'],
        ];
    }
}
