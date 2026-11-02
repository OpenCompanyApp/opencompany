<?php

namespace OpenCompany\Integrations\Google\Tools;

use OpenCompany\IntegrationCore\Contracts\Tool;
use OpenCompany\IntegrationCore\Support\ToolResult;
use OpenCompany\Integrations\Google\Services\GoogleSheetsService;

class GoogleSheetsBatchRead implements Tool
{
    public function __construct(
        private GoogleSheetsService $service,
    ) {}

    public function name(): string
    {
        return 'google_sheets_batch_read';
    }

    public function description(): string
    {
        return 'Read multiple ranges from a Google Spreadsheet in one call. Provide an array of A1 notation ranges (e.g., ["Sheet1!A1:B5", "Sheet2!C1:D10"]). Returns results keyed by range.';
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

            $ranges = $args['ranges'] ?? [];
            if (! is_array($ranges) || empty($ranges)) {
                return ToolResult::error('ranges is required (array of A1 notation strings).');
            }

            $renderOption = $this->resolveRenderOption($args['render'] ?? 'formatted');

            /** @var array<int, string> $ranges */
            $result = $this->service->batchGetRanges($spreadsheetId, $ranges, $renderOption);
            $valueRanges = $result['valueRanges'] ?? [];

            $output = [];
            foreach ($valueRanges as $vr) {
                $range = $vr['range'] ?? '';
                $values = $vr['values'] ?? [];
                $output[] = [
                    'range' => $range,
                    'rows' => count($values),
                    'values' => $values,
                ];
            }

            return ToolResult::success([
                'count' => count($output),
                'results' => $output,
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
            'ranges' => ['type' => 'array', 'required' => true, 'description' => 'Array of A1 notation ranges (e.g., ["Sheet1!A1:B5", "Sheet2!C1:D10"]).'],
            'render' => ['type' => 'string', 'description' => 'Value rendering: "formatted" (default, as displayed), "unformatted" (raw numbers), or "formula" (shows formulas).'],
        ];
    }
}
