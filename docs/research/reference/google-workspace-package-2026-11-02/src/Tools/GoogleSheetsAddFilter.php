<?php

namespace OpenCompany\Integrations\Google\Tools;

use OpenCompany\IntegrationCore\Contracts\Tool;
use OpenCompany\IntegrationCore\Support\ToolResult;
use OpenCompany\Integrations\Google\Services\GoogleSheetsService;

class GoogleSheetsAddFilter implements Tool
{
    public function __construct(
        private GoogleSheetsService $service,
    ) {}

    public function name(): string
    {
        return 'google_sheets_add_filter';
    }

    public function description(): string
    {
        return 'Apply filter dropdowns to a range in a Google Sheets sheet/tab.';
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
                return ToolResult::error('range is required (A1 notation including header row).');
            }

            $gridRange = $this->parseA1ToGridRange($spreadsheetId, $range);

            $this->service->batchUpdate($spreadsheetId, [
                ['setBasicFilter' => [
                    'filter' => ['range' => $gridRange],
                ]],
            ]);

            return ToolResult::success("Filter applied to range '{$range}'.");
        } catch (\Throwable $e) {
            return ToolResult::error($e->getMessage());
        }
    }

    /**
     * @return array<string, mixed>
     */
    private function parseA1ToGridRange(string $spreadsheetId, string $range): array
    {
        $sheetName = '';
        $cellRange = $range;

        if (str_contains($range, '!')) {
            $parts = explode('!', $range, 2);
            $sheetName = trim($parts[0], "'");
            $cellRange = $parts[1];
        }

        if ($sheetName === '') {
            throw new \RuntimeException('Range must include sheet name (e.g., "Sheet1!A1:D10").');
        }

        $sheetId = $this->service->resolveSheetId($spreadsheetId, $sheetName);

        $gridRange = ['sheetId' => $sheetId];

        $rangeParts = explode(':', $cellRange, 2);
        $start = $this->parseCellReference($rangeParts[0]);

        $gridRange['startColumnIndex'] = $start['col'];
        if ($start['row'] !== null) {
            $gridRange['startRowIndex'] = $start['row'];
        }

        if (isset($rangeParts[1])) {
            $end = $this->parseCellReference($rangeParts[1]);
            $gridRange['endColumnIndex'] = $end['col'] + 1;
            if ($end['row'] !== null) {
                $gridRange['endRowIndex'] = $end['row'] + 1;
            }
        } else {
            $gridRange['endColumnIndex'] = $start['col'] + 1;
            if ($start['row'] !== null) {
                $gridRange['endRowIndex'] = $start['row'] + 1;
            }
        }

        return $gridRange;
    }

    /**
     * @return array{col: int, row: int|null}
     */
    private function parseCellReference(string $ref): array
    {
        $ref = strtoupper(trim($ref));

        preg_match('/^([A-Z]+)(\d*)$/', $ref, $matches);
        if (empty($matches)) {
            throw new \RuntimeException("Invalid cell reference: '{$ref}'.");
        }

        $colLetters = $matches[1];
        $rowStr = $matches[2];

        $colIndex = 0;
        $len = strlen($colLetters);
        for ($i = 0; $i < $len; $i++) {
            $colIndex = $colIndex * 26 + (ord($colLetters[$i]) - ord('A') + 1);
        }
        $colIndex--;

        $rowIndex = $rowStr !== '' ? ((int) $rowStr - 1) : null;

        return ['col' => $colIndex, 'row' => $rowIndex];
    }

    public function parameters(): array
    {
        return [
            'spreadsheet_id' => ['type' => 'string', 'required' => true, 'description' => 'Spreadsheet ID (from the URL).'],
            'range' => ['type' => 'string', 'required' => true, 'description' => 'A1 notation range including header row (e.g., "Sheet1!A1:D10").'],
        ];
    }
}
