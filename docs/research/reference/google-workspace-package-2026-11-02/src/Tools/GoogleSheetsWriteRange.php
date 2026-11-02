<?php

namespace OpenCompany\Integrations\Google\Tools;

use OpenCompany\IntegrationCore\Contracts\Tool;
use OpenCompany\IntegrationCore\Support\ToolResult;
use OpenCompany\Integrations\Google\Services\GoogleSheetsService;

class GoogleSheetsWriteRange implements Tool
{
    public function __construct(
        private GoogleSheetsService $service,
    ) {}

    public function name(): string
    {
        return 'google_sheets_write_range';
    }

    public function description(): string
    {
        return <<<'MD'
        Write values to a Google Sheets range. Values format: `[["Name", "Age"], ["Alice", 30]]` — each inner array is one row.
        Formulas work with user_entered input mode (default): `[["=SUM(A1:A10)"]]`.
        MD;
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
                return ToolResult::error('range is required (e.g., "Sheet1!A1:D10").');
            }

            $values = $args['values'] ?? [];
            if (! is_array($values) || empty($values)) {
                return ToolResult::error('values is required (2D array of rows).');
            }

            $inputOption = $this->resolveInputOption($args['input'] ?? 'user_entered');

            /** @var array<int, array<int, mixed>> $values */
            $result = $this->service->writeRange($spreadsheetId, $range, $values, $inputOption);

            return ToolResult::success([
                'message' => 'Values written.',
                'updatedRange' => $result['updatedRange'] ?? $range,
                'updatedRows' => (int) ($result['updatedRows'] ?? 0),
                'updatedColumns' => (int) ($result['updatedColumns'] ?? 0),
                'updatedCells' => (int) ($result['updatedCells'] ?? 0),
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
            'range' => ['type' => 'string', 'required' => true, 'description' => 'A1 notation range (e.g., "Sheet1!A1:D10").'],
            'values' => ['type' => 'array', 'required' => true, 'description' => '2D array of values. Each inner array is a row (e.g., [["Name", "Age"], ["Alice", 30]]).'],
            'input' => ['type' => 'string', 'description' => 'Input mode: "user_entered" (default, parses formulas/dates) or "raw" (literal strings).'],
        ];
    }
}
