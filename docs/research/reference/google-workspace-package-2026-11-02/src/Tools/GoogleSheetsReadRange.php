<?php

namespace OpenCompany\Integrations\Google\Tools;

use OpenCompany\IntegrationCore\Contracts\Tool;
use OpenCompany\IntegrationCore\Support\ToolResult;
use OpenCompany\Integrations\Google\Services\GoogleSheetsService;

class GoogleSheetsReadRange implements Tool
{
    public function __construct(
        private GoogleSheetsService $service,
    ) {}

    public function name(): string
    {
        return 'google_sheets_read_range';
    }

    public function description(): string
    {
        return <<<'MD'
        Read cell values from a Google Sheets range using A1 notation.
        A1 notation examples: `Sheet1!A1:D10` (range), `Sheet1!A:A` (whole column), `Sheet1` (entire sheet). Sheet names with spaces need quotes: `'My Sheet'!A1:B2`.
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

            $renderOption = $this->resolveRenderOption($args['render'] ?? 'formatted');

            $result = $this->service->readRange($spreadsheetId, $range, $renderOption);
            $values = $result['values'] ?? [];

            if (empty($values)) {
                return ToolResult::success([
                    'range' => $result['range'] ?? $range,
                    'rows' => 0,
                    'values' => [],
                ]);
            }

            return ToolResult::success([
                'range' => $result['range'] ?? $range,
                'rows' => count($values),
                'columns' => max(array_map('count', $values)),
                'values' => $values,
            ]);
        } catch (\Throwable $e) {
            return ToolResult::error($e->getMessage());
        }
    }

    private function resolveRenderOption(string $render): string
    {
        return match ($render) {
            'unformatted' => 'UNFORMATTED_VALUE',
            'formula' => 'FORMULA',
            default => 'FORMATTED_VALUE',
        };
    }

    public function parameters(): array
    {
        return [
            'spreadsheet_id' => ['type' => 'string', 'required' => true, 'description' => 'Spreadsheet ID (from the URL).'],
            'range' => ['type' => 'string', 'required' => true, 'description' => 'A1 notation range (e.g., "Sheet1!A1:D10", "Sheet1!A:A", "Sheet1").'],
            'render' => ['type' => 'string', 'description' => 'Value rendering: "formatted" (default, as displayed), "unformatted" (raw numbers), or "formula" (shows formulas).'],
        ];
    }
}
