<?php

namespace OpenCompany\Integrations\Google\Tools;

use OpenCompany\IntegrationCore\Contracts\Tool;
use OpenCompany\IntegrationCore\Support\ToolResult;
use OpenCompany\Integrations\Google\Services\GoogleSheetsService;

class GoogleSheetsAppend implements Tool
{
    public function __construct(
        private GoogleSheetsService $service,
    ) {}

    public function name(): string
    {
        return 'google_sheets_append';
    }

    public function description(): string
    {
        return 'Append rows after the last data row in a Google Spreadsheet. Auto-detects the table boundary. Provide the range (e.g., "Sheet1" or "Sheet1!A:D") and a 2D array of rows to append.';
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
                return ToolResult::error('range is required (e.g., "Sheet1" or "Sheet1!A:D").');
            }

            $values = $args['values'] ?? [];
            if (! is_array($values) || empty($values)) {
                return ToolResult::error('values is required (2D array of rows to append).');
            }

            $inputOption = $this->resolveInputOption($args['input'] ?? 'user_entered');

            /** @var array<int, array<int, mixed>> $values */
            $result = $this->service->appendRows($spreadsheetId, $range, $values, $inputOption);

            $updates = $result['updates'] ?? [];

            return ToolResult::success([
                'message' => 'Rows appended.',
                'updatedRange' => $updates['updatedRange'] ?? '',
                'updatedRows' => (int) ($updates['updatedRows'] ?? 0),
                'updatedCells' => (int) ($updates['updatedCells'] ?? 0),
            ]);
        } catch (\Throwable $e) {
            return ToolResult::error($e->getMessage());
        }
    }

    private function resolveInputOption(string $input): string
    {
        return match ($input) {
            'raw' => 'RAW',
            default => 'USER_ENTERED',
        };
    }

    public function parameters(): array
    {
        return [
            'spreadsheet_id' => ['type' => 'string', 'required' => true, 'description' => 'Spreadsheet ID (from the URL).'],
            'range' => ['type' => 'string', 'required' => true, 'description' => 'A1 notation range (e.g., "Sheet1" or "Sheet1!A:D").'],
            'values' => ['type' => 'array', 'required' => true, 'description' => '2D array of rows to append (e.g., [["Alice", 30], ["Bob", 25]]).'],
            'input' => ['type' => 'string', 'description' => 'Input mode: "user_entered" (default, parses formulas/dates) or "raw" (literal strings).'],
        ];
    }
}
