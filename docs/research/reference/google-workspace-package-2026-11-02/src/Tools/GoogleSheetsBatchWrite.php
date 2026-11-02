<?php

namespace OpenCompany\Integrations\Google\Tools;

use OpenCompany\IntegrationCore\Contracts\Tool;
use OpenCompany\IntegrationCore\Support\ToolResult;
use OpenCompany\Integrations\Google\Services\GoogleSheetsService;

class GoogleSheetsBatchWrite implements Tool
{
    public function __construct(
        private GoogleSheetsService $service,
    ) {}

    public function name(): string
    {
        return 'google_sheets_batch_write';
    }

    public function description(): string
    {
        return 'Write to multiple ranges in a Google Spreadsheet in one call. Provide an array of {range, values} objects to update several areas at once.';
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

            $data = $args['data'] ?? [];
            if (! is_array($data) || empty($data)) {
                return ToolResult::error('data is required (array of {range, values} objects).');
            }

            $inputOption = $this->resolveInputOption($args['input'] ?? 'user_entered');

            /** @var array<int, array{range: string, values: array<int, array<int, mixed>>}> $data */
            $result = $this->service->batchUpdateValues($spreadsheetId, $data, $inputOption);

            return ToolResult::success([
                'message' => 'Batch write complete.',
                'totalUpdatedRows' => (int) ($result['totalUpdatedRows'] ?? 0),
                'totalUpdatedColumns' => (int) ($result['totalUpdatedColumns'] ?? 0),
                'totalUpdatedCells' => (int) ($result['totalUpdatedCells'] ?? 0),
                'totalUpdatedSheets' => (int) ($result['totalUpdatedSheets'] ?? 0),
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
            'data' => ['type' => 'array', 'required' => true, 'description' => 'Array of {range, values} objects (e.g., [{"range": "Sheet1!A1:B2", "values": [["a", "b"]]}]).'],
            'input' => ['type' => 'string', 'description' => 'Input mode: "user_entered" (default, parses formulas/dates) or "raw" (literal strings).'],
        ];
    }
}
