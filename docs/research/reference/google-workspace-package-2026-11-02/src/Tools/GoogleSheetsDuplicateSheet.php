<?php

namespace OpenCompany\Integrations\Google\Tools;

use OpenCompany\IntegrationCore\Contracts\Tool;
use OpenCompany\IntegrationCore\Support\ToolResult;
use OpenCompany\Integrations\Google\Services\GoogleSheetsService;

class GoogleSheetsDuplicateSheet implements Tool
{
    public function __construct(
        private GoogleSheetsService $service,
    ) {}

    public function name(): string
    {
        return 'google_sheets_duplicate_sheet';
    }

    public function description(): string
    {
        return 'Copy a sheet/tab within the same Google Spreadsheet.';
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
                return ToolResult::error('sheet (source sheet name) is required.');
            }

            $sheetId = $this->service->resolveSheetId($spreadsheetId, $sheetName);

            $dupRequest = ['sourceSheetId' => $sheetId];

            $newTitle = $args['title'] ?? '';
            if ($newTitle !== '' && is_string($newTitle)) {
                $dupRequest['newSheetName'] = $newTitle;
            }

            $result = $this->service->batchUpdate($spreadsheetId, [
                ['duplicateSheet' => $dupRequest],
            ]);

            $newSheet = $result['replies'][0]['duplicateSheet']['properties'] ?? [];
            $finalTitle = $newSheet['title'] ?? $newTitle;

            return ToolResult::success([
                'message' => "Sheet '{$sheetName}' duplicated as '{$finalTitle}'.",
                'sheetId' => (int) ($newSheet['sheetId'] ?? 0),
                'title' => $finalTitle,
            ]);
        } catch (\Throwable $e) {
            return ToolResult::error($e->getMessage());
        }
    }

    public function parameters(): array
    {
        return [
            'spreadsheet_id' => ['type' => 'string', 'required' => true, 'description' => 'Spreadsheet ID (from the URL).'],
            'sheet' => ['type' => 'string', 'required' => true, 'description' => 'Source sheet/tab name to duplicate.'],
            'title' => ['type' => 'string', 'description' => 'Name for the copy (defaults to "Copy of {name}").'],
        ];
    }
}
